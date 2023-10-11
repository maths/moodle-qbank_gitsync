# Updating a repo with the latest version of questions in Moodle

## Prerequisites
- Set up the webserver on the Moodle instance.
- Set up your local machine.
- Import a repo into Moodle using `importrepo.php` or create a repo from Moodle using `createrepo.php`.

## Exporting
- From the commandline in the `cli` folder run `exportrepo.php`. There are a number of options you can input. List them all with `php exportrepo.php -h`. You can use -shortname or --longname on the command line followed by a space and the value.

|Short|Long|Required value|
|-|-|-|
|i|moodleinstance|Key of Moodle instance in $moodleinstances to use. Should match end of instance URL.|
|r|rootdirectory|Directory on user's computer containing repos.|
|f|manifestpath|Filepath of manifest file relative to root directory including leading slash.|
|t|token|Security token for webservice.|
|h|help|

Examples:

`php exportrepo.php -r "C:\question_repos" -f "\source_1\edmundlocal_system_question_manifest.json"`