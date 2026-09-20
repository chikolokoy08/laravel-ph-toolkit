# Updating the PSGC dataset

For maintainers. The PSA republishes the PSGC quarterly. This is how to pull a
new release into the package.

The current dataset is built from **PSGC 2Q 2026, publication date June 30,
2026 (per the PSA datafile), announced by PSA on July 13, 2026**.

## Steps

1. Download the PSGC publication datafile from the PSA. Look for the
   publication datafile, the `.xlsx` with one row per geographic unit, not the
   summary tables.

2. Put it in `data/raw/`. Leave exactly one `.xlsx` there. The directory is
   gitignored, so the workbook is never committed or published.

   ```bash
   ls data/raw/
   # PSGC-2Q-2026-Publication-Datafile.xlsx
   ```

3. Set the release date. Open `bin/build-psgc.php` and update
   `PSGC_RELEASE_DATE` to the publication date, as `YYYY-MM-DD`. It is on the
   workbook's Metadata sheet, in the `Publication date:` row.

   ```php
   const PSGC_RELEASE_DATE = '2026-06-30';
   ```

   This value becomes `Address::psgcVersion()`. The script refuses to run if it
   is unset, and stops if it disagrees with the workbook's Metadata sheet. Only
   that sheet is trusted: other sheets carry an "As of" heading that is not
   always updated.

4. Build.

   ```bash
   composer build:psgc
   ```

   It rewrites `data/psgc/*.php`, which are committed.

5. Read the output. It reports the counts and lists every row it had to make a
   judgement about:

   ```
   Read PSGC-2Q-2026-Publication-Datafile.xlsx, sheet "PSGC", 43768 rows.
   Wrote 18 regions, 84 provinces, 1656 cities and municipalities, 42010 barangays.
   14 of those are sub-municipalities linked to a parent city.

   2 province-level rows have a blank Geographic Level. They ship as provinces with isProvince false:
     row 35444  0990100000  City of Isabela (Not a Province)
     row 43698  1999900000  Special Geographic Area
   Check these against the PSA notes when updating the dataset.

   Peak memory while reading the workbook: 80 MB.
   Address::psgcVersion() is now 2026-06-30.
   ```

   Check the flagged rows against the workbook's Notes sheet. If a new one
   appears, decide whether shipping it as a province with `isProvince` false is
   right for it before releasing.

6. Run the tests.

   ```bash
   composer test
   ```

   Several tests assert exact counts and specific places, so they will fail
   when the data legitimately changes. That is deliberate: it is the signal
   that the dataset moved. Read each failure, confirm the new value against the
   workbook, and update the test. A count that moved by a few is normal. A
   count that moved by hundreds usually means the script misread something.

7. Update the counts and the release date in `README.md`, in
   `docs/address-selector.md`, and in this file.

8. Add a `CHANGELOG.md` entry naming the new release date. A dataset update is
   a minor version bump, because codes and names can change.

9. Keep the [ph-toolkit](https://github.com/chikolokoy08/ph-toolkit) npm
   package on the same PSGC release. The two are meant to agree, and a form
   validated in the browser against one release and on the server against
   another will disagree about which barangays exist.

## What the script checks

It stops rather than writing partial or wrong data when:

- `PSGC_RELEASE_DATE` is unset, malformed, or disagrees with the workbook.
- No `.xlsx` is in `data/raw/`, or more than one is.
- No sheet has a header row with a PSGC code, a name, and a geographic level
  column. The error lists every sheet and its first rows so you can see what
  changed.
- A row has a code or a name missing, or a non-numeric code.
- A row has a geographic level the script does not recognise. Add it to
  `LEVELS` once you know which level it belongs to.
- A province, city, or barangay points at a parent that does not exist.
- A child's code does not share a prefix with its parent's code.
- A duplicate PSGC code appears.
- A row with a blank geographic level has nothing under it, which would mean it
  is not the province-level entry the script assumed.
- A sub-municipality's derived parent city is not the city listed above it.

## How the hierarchy is worked out

The publication file lists one row per geographic unit, ordered so a region is
followed by its provinces, each province by its cities and municipalities, and
each of those by its barangays. The script walks the rows in order and uses the
Geographic Level column, rather than slicing digits out of the code.

Two things need more than that:

**Independent cities.** City of Cebu, City of Baguio, City of Zamboanga, and
the cities of NCR hold a province-level code and sit directly under their
region. Walking the rows naively attaches them to whichever province came
before, which put Baguio in Benguet. The script measures the province code
segment from the rows the file labels `Prov`, and clears the current province
when a city occupies that segment. 34 cities and municipalities end up with a
null `provinceCode`, as do the 14 sub-municipalities.

**Sub-municipalities.** The 14 districts of the City of Manila have their own
codes. Their parent is derived by zeroing the city segment of their code, and
the result is checked against the city listed above them in the file. A
disagreement stops the build.

Both rules are measured from the file, not hardcoded, so a change to the PSA's
coding structure shows up as a validation failure rather than as quietly wrong
parents.

## Why the workbook is read in chunks

PhpSpreadsheet holds every cell it reads as an object. Loading the PSGC sheet
whole costs about 274 MB, over PHP's default `memory_limit` of 128 MB. Reading
only the three columns the script needs brings that to 121 MB, still close
enough to the limit to fail on a future release with more rows.

So the script reads 10,000 rows at a time and discards each chunk, which holds
the peak around 66 MB and takes a few seconds longer. It runs once a quarter;
the trade is worth it, and it means the script works without anyone having to
raise a memory limit first.

The columns are found by name in a first pass over the top of each sheet, so
extra columns and reordering in a future file are fine.

## Output format

The script writes PHP files that `return` an array of tuples, one file per
level plus `meta.php`. Arrays rather than JSON so opcache can hold them, and
one file per level so the lookups can read a level only when something asks for
it.

Output is deterministic: rebuilding from an unchanged workbook produces
byte-identical files, so a diff shows only what the PSA actually changed.

## If the format changes

The script targets the structure of the 2026 publication file: a sheet with a
`10-digit PSGC` column, a `Name` column, and a `Geographic Level` column. The
header row is searched for rather than assumed, and columns are matched by
name.

If the PSA changes something more fundamental, the script will stop with the
row number and the offending value. Fix the script, do not loosen the checks.
The point of them is that a silent misread produces an address dataset that
looks right and is not.
