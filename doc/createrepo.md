# Creating a repo from questions in Moodle

## Prerequisites

1. Install the plugin on Moodle and [set up the webservice](webservicesetup.md).
2. Set up Git, PHP and the plugin scripts [on your local computer](localsetup.md).

## Setup a local file system by exporting questions from Moodle

- To setup a local file system by exporting questions from Moodle, use the commandline `createrepo.php` script in the `cli` folder. There are a number of options. List them all with `php createrepo.php -h`. You can use -shortname or --longname on the command line followed by a space and the value.

|Short|Long|Required value|
|-|-|-|
|i|moodleinstance|Key of Moodle instance in $moodleinstances to use (see config.php). Should match end of instance URL. Defaults to $instance in config.php.|
|r|rootdirectory|Directory on user's computer containing repos.|
|d|directory|Directory of repo on user's computer containing "top" folder, relative to root directory.|
|s|subcategory|Relative subcategory of repo to actually export.|
|l|contextlevel|Context from which to extract questions. Set to system, coursecategory, course or module
|c|coursename|Unique course name for course or module context.
|m|modulename|Unique (within course) module name for module context.
|g|coursecategory|Unique course category name for coursecategory context.
|q|questioncategoryid|Numerical id of subcategory to actually export.
|n|instanceid|Numerical id of the course, module of course category.
|t|token|Security token for webservice.
|h|help|

### Example 1:

Assume you have correct information in config.php, i.e. the Moodle URL in `$moodleinstances`, and the webservice token stored in `$token`, the default moodle instance in `$instance` and the local root directory for your question files in `$rootdirectory`.

Assume you have a course called "Scratch" in Moodle.  You would like all questions from the "top" level, and all sub-categories, to become files in a sub-directory "gitsync-loc" of your local `$rootdirectory` directory.  Assume you have (1) created the local directory and (2) run `git init` to initialise an empty git repository.

`php createrepo.php -l course -c "Scratch" -d "gitsync-loc" `

### Example 2:

Assume Moodle is exactly as in Example 1.

You would like all questions from the sub-category "gitsync-test", to become files in a sub-directory "gitsync-sub" of your local `$rootdirectory` directory.  Assume you have (1) created the local directory and (2) run `git init` to initialise an empty git repository.

Navigate to the question bank, and select the sub-category you are interested in 
Imagine the URL of your Moodle site is `http://localhost/m402/question/edit.php?courseid=2&cat=273%2C17`.
From this we extract the Moodle `courseid=2` and category `cat=273`.

`php createrepo.php -l course -n=2 -q=273 -d "gitsync-sub"`

TODO: note explaining directory structure.

### Example 3:

`php createrepo.php -t 4ec7cd3f62e08f595df5e9c90ea7cfcd -i edmundlocal -r "C:\question_repos" -d "source_1" -l system`

You will need to specify the context of the questions you want to export from Moodle.
- Context level - system, coursecategory, course or module - must be supplied.
- (i) coursecategory (ii) coursename or (iii) coursename and modulename can then be supplied to identify the context instance.
- Alternatively, the instanceid of the context can be supplied. This is available from the URL while browsing the context in Moodle ('?id=XXX').

If you only want to export a certain question category (and its subcategories) within the context you will need to supply the category's name relative to the 'top' category e.g. 'category 1/subcategory 2'. Alternatively you can supply the questioncategoryid which is available in the URL ('&category=XXX') when browsing the category in the question bank. If you want to keep working only with this subcategory, then you will need to specify it (or the matching subdirectory of your repo) when performing each task - the repo itself will be created for the entire context. You will also need to be careful if you try importing a wider section of your repo than you've previously exported as some parent directories may not have required category files due to a bug with Moodle (MDL-80256) - you will need to create the files or run exportrepofrommoodle.php on the wider section and discard any exported questions you don't want.

A manifest file which links the questions in your repo to the questions in Moodle will be created at the top level of your directory. This manifest file will be ignored by Git, however. The manifest file will be backed up to a local folder before changes are made to it.

If Git is being used, the destination directory must be an empty Git repo and the exported questions will be committed to the current branch.

### On failure
- If the script fails, clean out the question folders and manifest files from the directory and run it again once the issue is sorted.
