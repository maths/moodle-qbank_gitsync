# Phase 1 TODO list (in no particular order)

## Make webservice setup script run on plugin install
- Make it possible to run the script multiple times.
- Set the file upload flag on every verion update as it resets by default.

## Command line arguments
- Allow use of course/question category/course category/module id rather than names. These are obtainable by the user from the URL (at least the course and question category are) are definitely unique, unlike the names currently used.
- Look into defaults to streamline the user experience, particulary the necessity of '/top' in subdirectory/subcategory settings.
- Sort out the issues with slashes in Windows. Double-check what's happening when categories are split and slashes are replaced, particularly with categories/courses with slashes in the name.
- Refactor the code to avoid having to add/remove slashes at the start of directories (or at least make it clearer what's going on).
- Validate command line arguments and return useful error messages. Some of this can be done on the client, some will need to be done on the server e.g. more gracefully dealing with non-existent courses, etc.
- Have option to take context from manifest file.

## Config
- Flag whether Git is being used. Can then skip sections of CLIs if not.

## Exception handling
- (Done) Make sure there is error handling around all webserver access and exit/continue as appropriate.
- Add try/catch around file access.
- Add try/catch around XML/String conversion.
- Investigate what's needed around DB calls.

## Crash recovery
- Check what currently happens if there is an error or network failure.
- Use temporary file to ensure manifest is synced properly. (Can fresh run just add to existing temp file or should it be dealt with and emptied first?)
- Do we need to read the temp file to prevent any actions being done a second time?
- What can be left 'as is' to be corrected on a second run?

## Guidance
- Further instructions for config setup, defaults and setting command line arguments.
- Security of web-service and permission options.
- Instructions for day-to-day Git use.

## Deletion or overwrite of questions in Moodle
- Separate out deletion steps from import script.
- Consider what could result in data loss during import/delete and mitigate.

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