# Creating a repo from questions in Moodle

## Prerequisites
- Set up the webserver on the Moodle instance.
- Set up your local machine.

## Exporting
- From the commandline in the `cli` folder run `createrepo.php`. There are a number of options you can input. List them all with `php exportrepo.php -h`. You can use -shortname or --longname on the command line followed by a space and the value.

|Short|Long|Required value|
|-|-|-|
|i|moodleinstance|Key of Moodle instance in  moodleinstances to use. Should match end of instance URL.|
|r|rootdirectory|Directory on user's computer containing repos.|
|d|directory|Directory of repo on users computer, containing "top" folder, relative to root directory and including leading slash.|
|s|subdirectory|Relative subdirectory of repo to actually import.|
|l|contextlevel|Context from which to extract questions. Set to system, coursecategory, course or module
|c|coursename|Unique course name for course or module context.
|m|modulename|Unique (within course) module name for module context.
|g|coursecategory|Unique course category name for coursecategory context.
|t|token|Security token for webservice.
|h|help|

Examples:

`php createrepo.php -t 4ec7cd3f62e08f595df5e9c90ea7cfcd -i edmundlocal -r "C:\question_repos" -d "\source_1" --contextlevel system`

On failure:
- If the script fails, it can be safely run again once the issue has been dealt with.
