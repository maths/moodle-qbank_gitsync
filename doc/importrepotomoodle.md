# Importing a repo into Moodle

## Prerequisites
- Set up the [webserver](webservicesetup.md) on the Moodle instance.
- Set up your [local machine](localsetup.md).

## Importing
- Create a repository of questions with a folder hierarchy akin to question categories i.e. top/category/subcategory or use `createrepo.php` to create a repo from existing questions on Moodle.
- Each category below top should have a `gitsync_category.xml` file containing a 'category question' with the details of the category.
See the `testrepo` folder in this repository for an example. If you don't have these files, in most cases Gitsync will create categories
based on the directory names. (If you are importing a subdirectory you will need your own category file.)
Due to a bug with Moodle (MDL-80256), nested categories without questions may not all have category files after export.
On import, these files will be created but should be checked before committing. 
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
|o|defaultfile|Name of file containing custom question defaults.|
|s|subdirectory|Relative subdirectory of repo to actually import.|
|k|targetcategory|Category to import a subdirectory into.
|a|targetcategoryname|Category to import a subdirectory into.
|l|contextlevel|Context in which to place questions. Set to system, coursecategory, course or module
|c|coursename|Unique course name for course or module context.
|m|modulename|Unique (within course) module name for module context.
|g|coursecategory|Unique course category name for coursecategory context.
|n|instanceid|Numerical id of the course, module of course category.
|t|token|Security token for webservice.
|h|help|
|u|usegit|Is the repo controlled using Git?
|x|ignorecat|Regex of categories to ignore - add an extra leading / for Windows.
|z|forceimport|Force import of all questions, even if current commit previously imported.
|y|useyaml|Export questions as YAML?|
|b|usefragments|Export questions as difference files?|
|q|batchsize|Number of questions to import per request (default 20). See [Batches](#batches).|

## Scenario 1: Importing questions into Moodle from an existing repo

e.g. You have exported questions from one Moodle instance to create the repo and you want to import them into a different instance

Importing will create a manifest file specific to the Moodle instance and context in the root of the repo.
This links files in the repo to specific questionbankentries in the Moodle instance.

You will need to specify the context you want to import the questions into. You will need to supply:
- Context level - system, coursecategory, course or module - must be supplied.
- (i) coursecategory (ii) coursename or (iii) coursename and modulename can then be supplied to identify the context instance.
- Alternatively, the instanceid of the context can be supplied. This is available from the URL while browsing the context in Moodle ('?id=XXX').

If you only want to import a certain question category (and its subcategories) within the context you will need to supply the path
of the corresponding folder within your repo relative to the 'top' category e.g. 'category-1/subcategory-2'.

On the first run when using Git the process will ask you to commit changes in the repo and try again as a .gitignore file will be created.

Examples:

`php importrepotomoodle.php -t 4ec7cd3f62e08f595df5e9c90ea7cfcd -i edmundlocal -r "C:\question_repos" -d "source_1" --contextlevel system`

This sets the token, the Moodle instance and the location of the repository to upload. Categories will be created at the system level.

`php importrepotomoodle.php -l module -c "Course 1" -m "Test 1"`

This will use the default token, instance and location values in `importrepotomoodle.php` but create the categories in quiz 'Test 1' of 'Course 1'.

`php importrepotomoodle.php -i edmundlocal -r "C:\question_repos" -d "source_1" -s "top/My-course/My-category" --contextlevel system`

This will only import the questions in the 'My-category' folder and below but will do so with full category hierarchy
(i.e. 'top/My course/My category). If you want to import a subdirectory of questions (or the full repo) into a specific question category
you will also need to specify `--targetcategory` or `--targetcategoryname`. See the [README file](../README.md#Using-subsets-of-materials)
for more information on subselections and targeting.

## Scenario 2: Re-importing questions into Moodle when the manifest file already exists

You should specify the manifest file path and context will be extracted from that.
You can still enter a subdirectory to only re-import some of the questions.

`php importrepotomoodle.php -f 'course1/instance1_course_course-1_question_manifest.json'`

Import will only be possible if there are not updates to the questions in Moodle which haven't been exported.

Only questions that have changed in the repo since the last import will be imported to Moodle
(to avoid creating a new version in Moodle when nothing has changed).

If the manifest file was created using targeting, import will always use the same subcategory and subdirectory.
If the manifest was created using a subdirectory but not targeted, then import will use this by default
but it can be overridden by specifying `subdirectory` when running the script.

## YAML

If your STACK questions are [YAML difference files](useyaml.md), set `useyaml` flag `-y`. You will need a defaults file. You can specify a file as an argument (`--defaultfile` or `-o`). If you do not specify a defaults file, the one recorded in the manifest file on repo creation will be used, or the default file. Normally Gitsync skips importing questions that have not changed in the repo since the last import so if you want to update them using different defaults you will need to use `-z` to force import of questions that have not changed themselves.

`php importrepotomoodle.php -f 'course1/instance1_course_course-1_question_manifest.json' -y 'true' -o 'questiondefaultscomma.yml' -z`

## Batches

Questions are sent to Moodle in batches of `--batchsize` (`-q`, default 20). A batch is also closed when its question files reach 4 MB in total, so that a request stays below PHP's default `post_max_size` of 8M. A question file larger than 4 MB is sent on its own. Each question is imported separately on the server, so a question that fails does not stop the rest of its batch.

A request must also fit the server's PHP limits:
- Each question adds 11 request variables. With the default `max_input_vars` of 1000, a batch can hold about 90 questions.
- If `post_max_size` is below about 5M, a batch can be too large. Moodle then ignores the whole request and answers `Invalid token - token not found`, although other requests work. Nothing from that batch is imported. Run the import again with a smaller `--batchsize`.

## Deletion

A check will be run to see if there are questions in the context/category in the manifest that do not have a file in the repo.
These will be listed.

A check will be run to see if there are questions in the context in Moodle that are not in the manifest. These will be listed.

To delete the questions from Moodle and tidy the manifest, run deletefrommoodle.php

### On failure
- If the script fails, it can be safely run again once the issue has been dealt with. Pending updates to the manifest file are stored in a temporary file in the root directory and these will be picked up at the start of the new run, avoiding multiple new versions of a question being created in Moodle.
- If Moodle does not answer an import request properly, the script stops, because Moodle may still have imported some or all questions of that batch. Run the import again. It first checks those questions in Moodle and records the ones that were imported:
  - an existing question, if its version in Moodle has gone up;
  - a new question, if exactly one question with its name in its category is not linked to the repository yet.

  Questions it cannot match are imported again. If a new question then exists twice in Moodle, the copy that is not linked is listed at the end of the run and can be removed with [`deletefrommoodle.php`](deletefrommoodle.md).

  The check relies on a temporary file (`*_pending.tmp`) next to the manifest. Without that file the next import cannot tell what happened. It then stops with `Export questions from Moodle before proceeding` if existing questions got a new version: run [`exportrepofrommoodle.php`](exportrepofrommoodle.md) first. New questions are imported again, as above.
