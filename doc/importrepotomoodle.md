# Importing a repo into Moodle

## Prerequisites
- Set up the [webserver](webservicesetup.md) on the Moodle instance.
- Set up your [local machine](localsetup.md).

## Importing
- Create a repository of questions with a folder hierarchy akin to question categories i.e. top/category/subcategory or use `createrepo.php` to create a repo from existing questions on Moodle.
- Each category below top should have a `gitsync_category.xml` file containing a 'category question' with the details of he category. See the `testrepo` folder in this repository for an example.
- If you have created a repository (or copied one) make sure you have a `.gitignore` file and it ignores gitsync's manifest and temporary files.  
`touch .gitignore`  
`printf '%s\n' '**/*_question_manifest.json' '**/*_manifest_update.tmp' >> .gitignore`  
Commit this update.  
(This will be done automatically when using `createrepo.php`.)
- From the commandline in the `cli` folder run `importrepotomoodle.php`. There are a number of options you can input. List them all with `php importrepotomoodle.php -h`. You can use -shortname or --longname on the command line followed by a space and the value.

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
|x|ignorecat|Regex of categories to ignore - add an extra leading / for Windows.

Examples:

`php importrepotomoodle.php -t 4ec7cd3f62e08f595df5e9c90ea7cfcd -i edmundlocal -r "C:\question_repos" -d "source_1" --contextlevel system`

This sets the token, the Moodle instance and the location of the repository to upload. Categories will be created at the system level.

`php importrepotomoodle.php -l module -c "Course 1" -m "Test 1"`

This will use the default token, instance and location values in `importrepotomoodle.php` but create the categories in quiz 'Test 1' of 'Course 1'.

`php importrepotomoodle.php -i edmundlocal -r "C:\question_repos" -d "source_1" -s "top/My-course/My-category" --contextlevel system`

This will only import the questions in the 'My-category' folder and below.

## Scenario 1: Importing questions into Moodle from an existing repo

e.g. You have exported questions from one Moodle instance to create the repo and you want to import them into a different instance

Importing will create a manifest file specific to the Moodle instance and context in the root of the repo. This links files in the repo to specific questionbankentries in the Moodle instance.

You will need to specify the context you want to import the questions into. You will need to supply:
- Context level - system, coursecategory, course or module - must be supplied.
- (i) coursecategory (ii) coursename or (iii) coursename and modulename can then be supplied to identify the context instance.
- Alternatively, the instanceid of the context can be supplied. This is available from the URL while browsing the context in Moodle ('?id=XXX').

If you only want to import a certain question category (and its subcategories) within the context you will need to supply the path of the corresponding folder within your repo relative to the 'top' category e.g. 'category-1/subcategory-2'.

On the first run when using Git the process will ask you to commit changes in the repo and try again as a .gitignore file will be created.

## Scenario 2: Re-importing questions into Moodle when the manifest file already exists

You should specify the manifest file path and context will be extracted from that. You can still enter a subdirectory to only re-import some of the questions.

Import will only be possible if there are not updates to the questions in Moodle which haven't been exported.

Only questions that have changed in the repo since the last import will be imported to Moodle (to avoid creating a new version in Moodle when nothing has changed).

## Deletion

A check will be run to see if there are questions in the context/category in the manifest that do not have a file in the repo. These will be listed.

A check will be run to see if there are questions in the context in Moodle that are not in the manifest. These will be listed.

To delete the questions from Moodle and tidy the manifest, run deletefrommoodle.php

### On failure
- If the script fails, it can be safely run again once the issue has been dealt with. Pending updates to the manifest file are stored in a temporary file in the root directory and these will be picked up at the start of the new run, avoiding multiple new versions of a question being created in Moodle.
