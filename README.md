# Maine Ranked-Choice-Voting Ballot Counter

A console script to count ranked-choice ballots using [CakePHP](https://cakephp.org) 3.x.

## Intended Use

The goal here is to use the "raw" 2018 Congressional District 2 ballot data, available on the [Maine Secretary of State website](https://www.maine.gov/sos/cec/elec/results/results18.html), to reproduce the Secretary's certified election results.

Reproducing the results is non-trivial, because (a) there are many more possible voting scenarios on a ranked-choice ballot and (b) an instant run-off must be conducted using the ranking mechanism when no candidate reaches the 50% + 1 vote count threshold. The applicable logic rules are well-documented, however, and are also available on the [Secretary's website](https://www.maine.gov/sos/cec/rules/29/250/250c535.docx).

## Methodology

I used a CakePHP console shell script only because I'm familiar with its use and it allowed for expedited handling of the libraries I used to directly ingest the MS Excel-based data.

This is a quick-and-dirty implementatation ... definitely not the most elegant or rugged example. Nevertheless, it worked as intended with minimal tweaking right out of the box. My code for loading and counting the data is in the [RcvShell.php file](https://github.com/thumbtech/me_cd2_rcv_counter/blob/master/src/Shell/RcvShell.php).

## Results

After loading the spreadsheets into a directory and configuring for use of that directory, the script is triggered in the console with:

```bash
bin/cake rcv
```

The follow results are returned:

```bash
RCV COUNT
---------------------------------------------------------------
Examining spreadsheets in folder c:/Users/Jeff/Desktop/CD2_RCV
Reading AUXCVRProofedCVR95RepCD2.xlsx ...
Reading Nov18CVRExportFINAL1.xlsx ...
Reading Nov18CVRExportFINAL2.xlsx ...
Reading Nov18CVRExportFINAL3.xlsx ...
Reading RepCD2-8final.xlsx ...
Reading UOCAVA-AUX-CVRRepCD2.xlsx ...
Reading UOCAVA-FINALRepCD2.xlsx ...
Reading UOCAVA2CVRRepCD2.xlsx ...
Collected 296,077 ballots ...
---------------------------------------------------------------
Counting ballots!
---------------------------------------------------------------
Round #1 ...
Ballots counted                    296,077
Undervotes                           6,018
Overvotes                              435
Ballots remaining                  289,624
Needed to win                      144,813
REP Poliquin, Bruce                134,184 -  46.33%
DEM Golden, Jared F.               132,013 -  45.58%
Bond, Tiffany L.                    16,552 -   5.71%
Hoar, William R.S.                   6,875 -   2.37%
---------------------------------------------------------------
No winner ... eliminiating Hoar, William R.S.
Round #2 ...
Ballots counted                    296,077
Undervotes                           8,159
Overvotes                              456
Ballots remaining                  287,462
Needed to win                      143,732
REP Poliquin, Bruce                135,073 -  46.99%
DEM Golden, Jared F.               133,216 -  46.34%
Bond, Tiffany L.                    19,173 -   6.67%
---------------------------------------------------------------
No winner ... eliminiating Bond, Tiffany L.
Round #3 ...
Ballots counted                    296,077
Undervotes                          14,173
Overvotes                              533
Ballots remaining                  281,371
Needed to win                      140,686
DEM Golden, Jared F.               142,440 -  50.62%
REP Poliquin, Bruce                138,931 -  49.38%
---------------------------------------------------------------
DEM Golden, Jared F. WINS!
```

The final results match the certified results. The sub-counts deliver slightly since (a) I did not conduct a batch elimination and (b) I do not distinguish between undervotes and exhausted ballots in later rounds. It would be easy to add these features!