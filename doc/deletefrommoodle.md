# Deleting orphaned questions fromo Moodle

## Prerequisites
- Set up the [webserver](webservicesetup.md) on the Moodle instance.
- Set up your [local machine](localsetup.md).
- Use `createrepo.php`, `exportrepo.php` and `importrepo.php` to manage your repository.

## Deleting
- After running `importrepo.php` you may be informed of questions in Moodle that are no longer linked to your repository - either there is no manifest entry (i.e. the question has been added to Moodle after export) or there is no question file in the repo (i.e. the question has been deleted from the repo).
- From the commandline in the `cli` folder run `deletefrommoodle.php`. There are a number of options you can input. List them all with `php deletefrommoodle.php -h`. You can use -shortname or --longname on the command line followed by a space and the value.
- You will be given the option to delete the questions from Moodle one at a time.

|Short|Long|Required value|
|-|-|-|
|i|moodleinstance|Key of Moodle instance in  moodleinstances to use. Should match end of instance URL.|
|r|rootdirectory|Directory on user's computer containing repos.|
|f|manifestpath|Filepath of manifest file relative to root directory.|
|d|directory|Directory of repo on users computer containing "top" folder, relative to root directory.|
|s|subdirectory|Relative subdirectory of repo to actually import.|
|l|contextlevel|Context in which to place questions. Set to system, coursecategory, course or module
|c|coursename|Unique course name for course or module context.
|m|modulename|Unique (within course) module name for module context.
|g|coursecategory|Unique course category name for coursecategory context.
|n|instanceid|Numerical id of the course, module of course category.
|t|token|Security token for webservice.
|h|help|

Examples:

`php deletefrommoodle.php -t 4ec7cd3f62e08f595df5e9c90ea7cfcd -i edmundlocal -r "C:\question_repos" -d "source_1" --contextlevel system`

The arguments are the same as for importrepo and the questions are identified in the same way as explained there. This task is just to separate out the deletion process from the import process as a safeguard. 

On failure:
- If the script fails, it can be safely run again once the issue has been dealt with. Questions that have already been deleted will be removed from the manifest file and you will not need to approve deletion again.
