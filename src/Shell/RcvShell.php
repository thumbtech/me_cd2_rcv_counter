<?php
namespace App\Shell;

use Cake\Console\Shell;

use Cake\Filesystem\Folder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RcvShell extends Shell
{

	// set the directory where the Excel Spreadsheets from ME Sec of State website live ..
	private $data_dir = 'c:/Users/Jeff/Desktop/CD2_RCV';
	// columns in the spreadsheets where the candidate rankings are displayed
	private $choice_cols = [4, 5, 6, 7, 8];
	// sometimes same candidate referred to differently .... ?
	// create a mapping to normalize the candidates.
	private $data_map = [
		'REP Poliquin, Bruce (5931)' => 'REP Poliquin, Bruce',
		'DEM Golden, Jared F. (5471)' => 'DEM Golden, Jared F.',
	];

	// this is the main procedure ...
    public function main()
    {
        $this->out('RCV COUNT');
        $this->hr();

        // get the spreadsheets from the directory
        $dir = new Folder($this->data_dir);
        $this->out('Examining spreadsheets in folder ' . $this->data_dir);
        $data_files = $dir->find('.*\.xlsx');

		$reader = IOFactory::createReader('Xlsx');
		$reader->setReadDataOnly(TRUE);

		// $ballots array is going to contain all the data pulled from the spreadsheets!
		$ballots = [];
		// now read 'em
        foreach ($data_files as $df)
        {
        	$this->out('Reading ' . $df . ' ...');
        	$spreadsheet = $reader->load($this->data_dir . DS . $df);
			$worksheet = $spreadsheet->getActiveSheet();
			$highestRow = $worksheet->getHighestRow();

			for ($row = 2; $row <= $highestRow; ++$row)
			{
				$ranks = [];
				foreach ($this->choice_cols as $col)
				{
					// need to trim here ... sometimes there are trailing spaces on the data!
					$data = trim((string) $worksheet->getCellByColumnAndRow($col, $row)->getValue());
					if (isset($this->data_map[$data])) $data = $this->data_map[$data];
					$ranks[] = $data;
				}
				// normalize the $ranks and then add to $ballots ...
				$ballots[] = $this->ballot_clean($ranks);
			}
        }
        
        $this->out('Collected ' . number_format(count($ballots)) . ' ballots ...');
        $this->hr();
        
        $this->out('Counting ballots!');
        $this->hr();
        
        $round = 1;
        // I'm just going to count until I get a majority winner!
        while (true)
        {
        	$this->out('Round #' . $round . ' ...');

        	// initialize the totals array, with over & undervote set to 0
	        $totals = ['undervote' => 0, 'overvote' => 0];

	        // count 'em!
	        foreach ($ballots as $ballot)
	        {
	        	// I cleaned out 'undervote', so in my world, an undervote is a ballot with *no* votes
	        	// check for no votes, and record as undervote here:
	        	if (!isset($ballot[0]))
	        	{
	        		$totals['undervote']++;
	        		continue;
	        	}
	        	// total 'em
	        	if (!isset($totals[$ballot[0]])) $totals[$ballot[0]] = 0;
	        	$totals[$ballot[0]]++;
	        }

	        // Summarize the data:
	        $this->out($this->show_count('Ballots counted', array_sum($totals)));
	        $this->out($this->show_count('Undervotes', $totals['undervote']));
	        $this->out($this->show_count('Overvotes', $totals['overvote']));

	        // Also get the actual valid candidate votes ...
	        $total_votes = $totals;
	        // ... remove over & undervote ballots
	        unset($total_votes['undervote']);
	        unset($total_votes['overvote']);
	        // sort from high to low vote getters
	        arsort($total_votes);

	        $total_votes_k = array_keys($total_votes);
	        $total_votes_v = array_values($total_votes);
	        $total_votes_count = array_sum($total_votes);

	        // More summarizing
	        $this->out($this->show_count('Ballots remaining', $total_votes_count));
	        $needed_to_win = floor($total_votes_count / 2) + 1;
	        $this->out($this->show_count('Needed to win', $needed_to_win));

	        // display candidate totals
	        foreach ($total_votes as $tvk => $tvv)
	        {
	        	$this->out($this->show_count_and_percent($tvk, $tvv, $total_votes_count));
	        }
	        $this->hr();

	        // check for winner & exit if found
	        if ($total_votes_v[0] >= $needed_to_win)
	        {
	        	$this->out($total_votes_k[0] . ' WINS!');
	        	break;
	        }
       	
        	// still here? on to next round ...
        	$round++;
        	$last_place = array_pop($total_votes_k);
        	$this->out('No winner ... eliminiating ' . $last_place);
        	$ballots = $this->reallocate($ballots, $last_place);
        }
    }

    // just remove $last_place votes from the $ballots!
    // other entries will just slide up ...
    private function reallocate($old_ballots, $last_place)
    {
    	$new_ballots = [];
    	foreach ($old_ballots as $ob)
    	{
	    	$clean = [];
	    	foreach ($ob as $v)
	    	{
    			// if overvote, add it and ignore rest of ballot
	    		if ($v == 'overvote')
	    		{
	    			$clean[] = 'overvote';
	    			break;
	    		}
	    		// skip if entry was $last_place
	    		if ($v == $last_place) continue;
	    		// somebody other than $last_place, I guess! ...
	    		$clean[] = $v;
	    	}
	    	$new_ballots[] = $clean;
	    }
	    // $new_ballots will not contain any votes for $last_place now ...
	    return $new_ballots;
    }

    // this normalizes what was entered on the ballot into a "clean" ballot
    // rules available on ME Sec of State Website
    // https://www.maine.gov/sos/cec/rules/29/250/250c535.docx
    private function ballot_clean($raw)
    {
    	$clean = [];
    	$undervotes = 0;
    	foreach ($raw as $v)
    	{
    		if (empty($v) or $v == 'undervote')
    		{
    			$undervotes++;
    			// if 2 or more undervotes, ignore rest of ballot ...
    			if ($undervotes >= 2)
    			{
    				return $clean;
    			}
    			continue;
    		}
    		// they ranked something ... so reset the undervote counter!
    		$undervotes = 0;
    		// if overvote, add it and ignore rest of ballot
    		if ($v == 'overvote')
    		{
    			$clean[] = 'overvote';
    			return $clean;
    		}
    		// if they already voted for the candidate specified, skip this entry
    		if (in_array($v, $clean)) continue;
    		// OK, add their entry!
    		$clean[] = $v;
    	}
    	return $clean;
    }

    // function to display values
    private function show_count($label, $value)
    {
    	return str_pad($label, 30) . str_pad(number_format($value), 12, ' ', STR_PAD_LEFT);
    }

    // function to display values + percent
    private function show_count_and_percent($label, $value, $total)
    {
    	return $this->show_count($label, $value) . ' - ' . str_pad(number_format($value / $total * 100, 2) . '%', 7, ' ', STR_PAD_LEFT);
    }
}