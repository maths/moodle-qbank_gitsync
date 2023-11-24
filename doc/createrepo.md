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
|d|directory|Directory of repo on users computer containing "top" folder, relative to root directory.|
|s|subcategory|Relative subcategory of repo to actually export.|
|l|contextlevel|Context from which to extract questions. Set to system, coursecategory, course or module
|c|coursename|Unique course name for course or module context.
|m|modulename|Unique (within course) module name for module context.
|g|coursecategory|Unique course category name for coursecategory context.
|q|questioncategoryid|Numerical id of subcategory to actually export.
|n|instanceid|Numerical id of the course, module of course category.
|t|token|Security token for webservice.
|h|help|

Examples:

`php createrepo.php -t 4ec7cd3f62e08f595df5e9c90ea7cfcd -i edmundlocal -r "C:\question_repos" -d "source_1" --contextlevel system`

You will need to specify the context of the questions you want to export from Moodle.
- Context level - system, coursecategory, course or module - must be supplied.
- (i) coursecategory (ii) coursename or (iii) coursename and modulename can then be supplied to identify the context instance.
- Alternatively, the instanceid of the context can be supplied. This is available from the URL while browsing the context in Moodle.

If you only want to export a certain question category (and its subcategories) within the context you will need to supply the category's name relative to the 'top' category e.g. 'category 1/subcategory 2'. Alternatively you can supply the questioncategoryid which is available in the URL ('&cat=XXX') when browsing the category in the question bank. If you want to keep working only with this subcategory, then you will need to specify it (or the matching subdirectory of your repo) when performing each task - the repo itself will be created for the entire context.

A manifest file which links the questions in your repo to the questions in Moodle will be created at the top level of your directory. This manifest file will be ignored by Git, however. [TODO - The philosophy of this needs explained in an overview document.] The manifest file will be backed up to a local folder before changes are made to it.

If Git is being used, the destination directory must be an empty Git repo and the exported questions will be committed to the current branch.

On failure:
- If the script fails, it can be safely run again once the issue has been dealt with. (You may get instructions to delete a manifest file. If so, delete the file and run again.)
