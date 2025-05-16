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
|p|nonquizmanifestpath|Quiz export: Filepath of non-quiz manifest file relative to root directory.|
|t|token|Security token for webservice.
|h|help|
|x|ignorecat|Regex of categories to ignore - add an extra leading / for Windows.

For Moodle 5+, there are no longer course, course category or system context question banks. Questions are contained
in module level question banks. This makes things simpler. Where command line parameters for
`contextlevel` and `instanceid` are required you will always need to use `module`
and the value of `cmid` from the URL of the question bank.

e.g. `php createrepo.php -l module -n 2 -d "master"`

### Example 1:

Assume you have correct information in config.php, i.e. the Moodle URL in `$moodleinstances`,
and the webservice token stored in `$token`, the default moodle instance in `$instance` and
the local root directory for your question files in `$rootdirectory`.

Assume you have a course called "Scratch" in Moodle.  You would like all questions from the "top"
level, and all sub-categories, to become files in a sub-directory "gitsync-loc"
of your local `$rootdirectory` directory.  Assume you have (1) created the
local directory and (2) run `git init` to initialise an empty git repository.

`php createrepo.php -l course -c "Scratch" -d "gitsync-loc" `

### Example 2:

Assume Moodle is exactly as in Example 1.

You would like all questions from the sub-category "gitsync-test", to become files in a sub-directory
"gitsync-sub" of your local `$rootdirectory` directory.  Assume you have (1) created the local directory
and (2) run `git init` to initialise an empty git repository.

Navigate to the question bank, and select the sub-category you are interested in 
Imagine the URL of your Moodle site is `http://localhost/m402/question/edit.php?courseid=2&cat=273%2C17`.
From this we extract the Moodle `courseid=2` and category `cat=273`.

`php createrepo.php -l course -n=2 -q=273 -d "gitsync-sub"`

TODO: note explaining directory structure.

### Example 3:

Assume Moodle is exactly as in Example 1.

You have a quiz within your course, and you have populated the _quiz_ question bank, rather than the course question bank. 
You would like all questions from the quiz question bank, and all sub-categories, to become files in a sub-directory
"gitsync-loc" of your local `$rootdirectory` directory.

Navigate to the view quiz, to get the id of the quiz itself from the URL, e.g. `mod/quiz/view.php?id=547`.  

`php createrepo.php -l module -n=547 -d "gitsync-sub"`

### Example 4:

`php createrepo.php -t 4ec7cd3f62e08f595df5e9c90ea7cfcd -i edmundlocal -r "C:\question_repos" -d "source_1" -l system`

You will need to specify the context of the questions you want to export from Moodle.
- Context level - system, coursecategory, course or module - must be supplied.
- (i) coursecategory (ii) coursename or (iii) coursename and modulename can then be supplied to identify the context instance.
- Alternatively, the instanceid of the context can be supplied. This is available from the URL
while browsing the context in Moodle ('?id=XXX').

A manifest file which links the questions in your repo to the questions in Moodle will be created at the top level of your directory.
This manifest file will be ignored by Git, however. The manifest file will be backed up to a local folder before changes are made to it.

If you only want to export a certain question category (and its subcategories) within the context you
will need to supply the category's name relative to the 'top' category e.g. 'category 1/subcategory 2'.
Alternatively you can supply the questioncategoryid which is available in the URL ('&category=XXX') when browsing the category
in the question bank. When performing future tasks involving the manifest file, they will default to using this subdirectory but you
can override this by specifying a different subcategory.
The repo itself will be created for the entire context.
You will need to be careful if you try importing a wider section of your
repo than you've previously exported as you may not have required category files. You can do one of the following:
- create the files from scratch.
- run exportrepofrommoodle.php on the wider section first and discard any exported questions you don't want. (Recommended if importing
into existing categories you have not previously exported.)
- let Gitsync create the files automatically. (It's worth checking the results both in the repo and in Moodle.)

If you really just want a given subcategory, set the `istargeted` flag `-k`. This will export the supplied subcategory as if it were
the top level category. When performing future tasks involving the manifest file, you will not be able to override the selected subcategory.

If Git is being used, the destination directory must be an empty Git repo and the exported questions will be committed to the current branch.

### On failure
- If the script fails, clean out the question folders and manifest files from the directory and run it again once the issue is sorted.
