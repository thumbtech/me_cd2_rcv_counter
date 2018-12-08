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
        foreach ($data_files as $dfk => $dfv)
        {
        	$this->out("Reading {$dfv} (#{$dfk}) ...");
        	$spreadsheet = $reader->load($this->data_dir . DS . $dfv);
			$worksheet = $spreadsheet->getActiveSheet();
			$highestRow = $worksheet->getHighestRow();

			// a little hackey: just read the hard-coded columns above from 2nd row thru end ...
			for ($row = 2; $row <= $highestRow; ++$row)
			{
				$raw = [];
				foreach ($this->choice_cols as $col) $raw[] = (string) $worksheet->getCellByColumnAndRow($col, $row)->getValue();
				// record ballot source and raw rankings
				$ballots[] = [
					'source' => [
						'file_index' => $dfk,
						'row' => $row,
					],
					'raw' => $raw,
				];
			} // end each row on the spreadsheet

			// clean up for memory!
    		$spreadsheet->disconnectWorksheets();
    		unset($spreadsheet);
        } // end each spreadsheet

	    $this->out('Collected ' . number_format(count($ballots)) . ' ballots ...');
        $this->hr();

        $this->out('Normalizing ' . number_format(count($ballots)) . ' ballots ...');
        $this->hr();

        // now I have the raw ballots ... but we need to normalize per rules
	    // rules available on ME Sec of State Website
	    // https://www.maine.gov/sos/cec/rules/29/250/250c535.docx
        foreach ($ballots as $bk => $bv)
        {
    		$ranks = [];
	    	$undervotes = 0;
	    	foreach ($bv['raw'] as $rv)
	    	{
	    		// first trim and "map" the raw data
	    		$rv = trim($rv);
	    		if (isset($this->data_map[$rv])) $rv = $this->data_map[$rv];
	    		if (empty($rv) or $rv == 'undervote')
	    		{
	    			$undervotes++;
	    			// if 2 or more undervotes, ignore rest of ballot ...
	    			if ($undervotes >= 2)
	    			{
	    				break;
	    			}
	    			continue;
	    		}
	    		// they ranked something ... so reset the undervote counter!
	    		$undervotes = 0;
	    		// if overvote, add it and ignore rest of ballot
	    		if ($rv == 'overvote')
	    		{
	    			$ranks[] = 'overvote';
	    			break;
	    		}
	    		// if they already voted for the candidate specified, skip this entry
	    		if (in_array($rv, $ranks)) continue;
	    		// OK, add their entry!
	    		$ranks[] = $rv;
	    	}
	    	$ballots[$bk]['ranks'] = $ranks;
        } // end each ballot

        // now each ballot contains a normalize array of the voters rankings
        // the ranks array is like a stack of cards in order of preference,
        // with either the candidate name or "overvote"
        
        // I can begin the count now!
        $this->out('Counting ballots!');
       
        $round = 1;
        // I'm going to count as long as I need to in order to determine the winner!
        while (true)
        {
       		$this->hr();
         	$this->out('Round #' . $round . ' ...');

        	// initialize the totals array, with over & undervote set to 0
	        $totals = ['undervote' => 0, 'overvote' => 0, 'exhausted' => 0];

	        // count 'em!
	        foreach ($ballots as $bv)
	        {
	        	// I cleaned out 'undervote', so in my world, an undervote is a ballot with *no* votes
	        	// check for no votes, and record as undervote here:
	        	if (!isset($bv['ranks'][0]))
	        	{
	        		if (array_search('undervote', $bv['raw']) === false and array_search('overvote', $bv['raw']) === false )
	        		{
	        			$totals['exhausted']++;
	        		}
	        		else
	        		{
	        			$totals['undervote']++;
	        		}
	        		continue;
	        	}
	        	// total 'em
	        	if (!isset($totals[$bv['ranks'][0]])) $totals[$bv['ranks'][0]] = 0;
	        	$totals[$bv['ranks'][0]]++;
	        } // end each ballot

	        // Summarize the data:
	        $this->out($this->show_count('Ballots counted', array_sum($totals)));
	        $this->out($this->show_count('Overvotes', $totals['overvote']));
	        $this->out($this->show_count('Undervotes', $totals['undervote']));
	        $this->out($this->show_count('Exhausted', $totals['exhausted']));

	        // Also get the actual valid candidate votes ...
	        $total_votes = $totals;
	        // ... remove over & undervote & exhausted ballots, which are not used in count
	        unset($total_votes['overvote']);
	        unset($total_votes['undervote']);
	        unset($total_votes['exhausted']);

	        // sort candidates from high to low vote totals
	        arsort($total_votes);

	        // extract the candidates
	        $total_votes_k = array_keys($total_votes);
	        // extract the vote totals
	        $total_votes_v = array_values($total_votes);
	        // summarize total votes
	        $total_votes_count = array_sum($total_votes);

	        // More summarizing
	        $this->out($this->show_count('Ballots remaining', $total_votes_count));
	        $needed_to_win = floor($total_votes_count / 2) + 1;
	        $this->out($this->show_count('Needed to win', $needed_to_win));

	        // display candidate vote totals with percentage
	        foreach ($total_votes as $tvk => $tvv) $this->out($this->show_count_and_percent($tvk, $tvv, $total_votes_count));
	        $this->hr();

	        // only two candidates left? Woohoo! We are done!
	        if (count($total_votes) <= 2)
	        {
	        	// check for tie!
	        	if ($total_votes_v[0] == $total_votes_v[1])
	        	{
	        		$this->out(implode(' & ', $total_votes_k) . ' TIED!');
	        	}
	        	else
	        	{
	        		$this->out($total_votes_k[0] . ' WINS!');
	        	}
	        	break;
	        }

	        // check for winner in round 1 & exit if found
	        if ($round == 1)
	        {
	        	if ($total_votes_v[0] >= $needed_to_win)
	        	{
	        		$this->out($total_votes_k[0] . ' WINS IN FIRST ROUND!');
	        		break;
	        	}
	        	$this->out('No winner in Round #1 ... conduct RCV count!');
	        }

       	
        	// still here? on to next round ...
        	$round++;

        	// eliminate everybody who is mathematically ruled out
        	// AKA "Batch Elimination" per rules
        	$eliminated = [];
        	$eliminated_votes = 0;
        	while (count($total_votes_k) > 2)
        	{
	       		$last_place = array_pop($total_votes_k);
	       		$last_place_votes = array_pop($total_votes_v);
	       		// don't eliminate this candidate if they can still win or tie!
	       		if ($last_place_votes + $eliminated_votes >= $total_votes_v[0]) break;
	       		// OK elimate this candidate
	       		$eliminated[] = $last_place;
	       		$eliminated_votes += $last_place_votes;
	       	} // end while eliminating ...

        	// go through ballots removing all the elminated candidates, leaving remaining votes
         	$this->out('Eliminating ' . implode(' & ', $eliminated) . ' ...');
	        foreach ($ballots as $bk => $bv)
	        {
	        	$ranks = [];
	        	foreach ($bv['ranks'] as $v)
	        	{
	        		if (in_array($v, $eliminated)) continue;
	        		$ranks[] = $v;
	        	}
	        	$ballots[$bk]['ranks'] = $ranks;
	   	    } // end each ballot
	   	    // now the same $ballots can be recounted ...

        } // end each round

        // finished!
        $this->hr();
        $this->out('That was easy.');
        $this->out('Thank you for playing!');
    } // end main procedure

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