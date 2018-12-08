# Maine Ranked-Choice-Voting Ballot Counter

A console script to count ranked-choice ballots using [CakePHP](https://cakephp.org) 3.x.

## Intended Use

The goal here is to use the "raw" 2018 Congressional District 2 ballot data, available on the [Maine Secretary of State website](https://www.maine.gov/sos/cec/elec/results/results18.html), to reproduce the Secretary's certified election results.

Reproducing the results is non-trivial, because (a) there are many more possible voting scenarios on a ranked-choice ballot and (b) an instant run-off must be conducted using the ranking mechanism when no candidate reaches the 50% + 1 vote count threshold. The applicable logic rules are well-documented, however, and are also available on the [Secretary's website](https://www.maine.gov/sos/cec/rules/29/250/250c535.docx).

With the raw ballot data and documented rules, transparency would dictate that anybody can reproduce the certified results!

## Methodology

I used a CakePHP console shell script only because I'm familiar with its use and it allowed for expedited handling of the libraries I used to directly ingest the MS Excel-based data.

This is a quick-and-dirty implementatation ... definitely not the most elegant or rugged example. Nevertheless, it worked as intended with minimal tweaking right out of the box! My code for loading and counting the data is in the [src/Shell/RcvShell.php file](https://github.com/thumbtech/me_cd2_rcv_counter/blob/master/src/Shell/RcvShell.php).

Note: These data spreadsheets are quite large and the [PHPOffice/PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) library is not particularly efficient. This required increasing the PHP memory allocation to 1024M in order to avoid an out-of-memory error.

In an ideal world, the Secretary or State would publish the raw data in a single CSV file... Still, it is very cool that the data is made available! The Secretary of State and his staff, along with all CD2 local election officials, deserve kudos for the professionalism and transparency with which they conducted this, the first-ever use of an instant run-off vote for federal office in the US!

## Results

After loading the spreadsheets into a directory and configuring for use of that directory, the script is triggered in the console with:

```bash
bin/cake rcv
```

The following results are returned:

```bash
RCV COUNT
---------------------------------------------------------------
Examining spreadsheets in folder c:/Users/Jeff/Desktop/CD2_RCV
Reading AUXCVRProofedCVR95RepCD2.xlsx (#0) ...
Reading Nov18CVRExportFINAL1.xlsx (#1) ...
Reading Nov18CVRExportFINAL2.xlsx (#2) ...
Reading Nov18CVRExportFINAL3.xlsx (#3) ...
Reading RepCD2-8final.xlsx (#4) ...
Reading UOCAVA-AUX-CVRRepCD2.xlsx (#5) ...
Reading UOCAVA-FINALRepCD2.xlsx (#6) ...
Reading UOCAVA2CVRRepCD2.xlsx (#7) ...
Collected 296,077 ballots ...
---------------------------------------------------------------
Normalizing 296,077 ballots ...
---------------------------------------------------------------
Counting ballots!
---------------------------------------------------------------
Round #1 ...
Ballots counted                    296,077
Overvotes                              435
Undervotes                           6,018
Exhausted                                0
Ballots remaining                  289,624
Needed to win                      144,813
REP Poliquin, Bruce                134,184 -  46.33%
DEM Golden, Jared F.               132,013 -  45.58%
Bond, Tiffany L.                    16,552 -   5.71%
Hoar, William R.S.                   6,875 -   2.37%
---------------------------------------------------------------
No winner in Round #1 ... conduct RCV count!
Eliminating Hoar, William R.S. & Bond, Tiffany L. ...
---------------------------------------------------------------
Round #2 ...
Ballots counted                    296,077
Overvotes                              533
Undervotes                          13,838
Exhausted                              335
Ballots remaining                  281,371
Needed to win                      140,686
DEM Golden, Jared F.               142,440 -  50.62%
REP Poliquin, Bruce                138,931 -  49.38%
---------------------------------------------------------------
DEM Golden, Jared F. WINS!
---------------------------------------------------------------
That was easy.
Thank you for playing!
```

The final results match the certified results!