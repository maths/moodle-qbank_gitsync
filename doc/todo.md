# Phase 1 TODO list (in no particular order)

## Make webservice setup script run on plugin install
- (Won't do) Make it possible to run the script multiple times.
- (Won't do) Set the file upload flag on every version update as it resets by default.
Played around with this. Getting the contents of the script to be run as part of install/upgrade is do-able via db/install.php and db/upgrade.php but is probably asking for trouble as any problems lead to install fail which is never good. Also the upgrde script runs before the upload flag is reset so doesn't do any good anyway. Everything the script does can be done manually so we just need to give detailed instructions.

## Command line arguments
- (Done) Allow use of course/question category/course category/module id rather than names. These are obtainable by the user from the URL (at least the course and question category are) are definitely unique, unlike the names currently used.
- (Done) Look into defaults to streamline the user experience, particulary the necessity of '/top' in subdirectory/subcategory settings.
- (Done) Sort out the issues with slashes in Windows.
- (Done) Double-check what's happening when categories are split and slashes are replaced, particularly with categories/courses with slashes in the name.
- (Done) Refactor the code to avoid having to add/remove slashes at the start of directories (or at least make it clearer what's going on).
- (Done) Validate command line arguments and return useful error messages. Some of this can be done on the client...
- (Duplicate) ... some will need to be done on the server e.g. more gracefully dealing with non-existent courses, etc.
- (Done) Have option to take context from manifest file.

## Creating the manifest on an import
We're currently set up for extracting question from Moodle as the first step. What about importing an existing repo?
- (Done) This may require more manual steps e.g. adding/committing manifest and temp file to .gitignore. Attempting to do it entirely automatically to a mature repo would be problematic.
- (Done) What about commit hashes? Will need to set Moodle and current hashes after first run. Update: We absolutely have to get the commit hash at time of import and add to temp file for this to work and be resilient for recovery purposes. `usegit` config/argument added to CLIs to trigger the (minimal) git specific code in classes.
- (Done) What about future runs? User could be importing subdirectory - can we determine which manifest entries have been imported on a run in order to add hashes? (In manifest but no hashes and no exportedversion?) Update: see above.
- (Done) If the top subdirectory category doesn't exist, import currently fails. Should be created instead (with warning and chance to abort). This is an issue with the question version checking code, not the import itself.

## Config
- (Done) Flag whether Git is being used. Can then skip sections of CLIs if not.

## Exception handling
- (Done) Make sure there is error handling around all webserver access and exit/continue as appropriate.
- (Done) Add try/catch around file access.
- (Done) Add try/catch around XML/String conversion. Update: It's not errors that are thrown - have to check for functions returning false.
- (Done) Investigate what's needed around DB calls. Update: An error is thrown which is then passed to the front end and handled. Debug info is being displayed which may be too much but fine for now.

## Crash recovery
- (Done) Check what currently happens if there is an error or network failure.
- (Done) Use temporary file to ensure manifest is synced properly. (Can fresh run just add to existing temp file or should it be dealt with and emptied first?)
- (Done) Do we need to read the temp file to prevent any actions being done a second time?
- (Done) What can be left 'as is' to be corrected on a second run?  
See markup files for each CLI for re-run instructions.

## Guidance
- Further instructions for config setup, defaults and setting command line arguments.
- Security of web-service and permission options.
- Instructions for day-to-day Git use.

## Deletion or overwrite of questions in Moodle
- (Done) Separate out deletion steps from import script.
- (Started) Consider what could result in data loss during import/delete and mitigate.

## Concurrency
- What are the scenarios?
- Which cause a problem?
- How do we mitigate?

## Testing
- Try different scenarios and see how well they work.
 - Single repo.
 - Double repo.
 - Multiple users.
 - How do we effectively handle deletion of questions from Moodle source but not the master branch?
- Test exception recovery.
- Thorough testing of different context levels.
- Thorough testing of using subdirectories.
- Is there any directory structure weirdness thrown up by using subdirectories?

## Other
- Additional metadata in manifest.
- Category files. Categories are not versioned and currently gitsync is not updating existing categories on either import or export. What are the options here?