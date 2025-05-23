# Importing a course and all its quizzes into Moodle

## Prerequisites
- Set up the [webserver](webservicesetup.md) on the Moodle instance.
- Set up your [local machine](localsetup.md).

## Importing
This is very similar to `importrepotomoodle.php` but normally Gitsync retrieves questions within a Moodle context, returning all or a subselection of question categories with the repo directory structure matching the category structure in Moodle. Courses and quizzes are in separate contexts, however. `importwholecoursetomoodle.php` and the matching repo creation and export tools keep quizzes with their parent course in a single repo.

- Create a repository where the top-most directory contains question directories for the course and each of its quizzes. Within each of these context directories, store your questions with a folder hierarchy akin to question categories i.e. top/category/subcategory. Alternativelty, use `createwholecourserepo.php` to create a repo from existing questions on Moodle.
- Each category below top should have a `gitsync_category.xml` file containing a 'category question' with the details of he category. See the `testrepoparent` folder in this repository for an example.
- If you have created a repository (or copied one) make sure you have a `.gitignore` file and it ignores gitsync's manifest and temporary files.  
`touch .gitignore`  
`printf '%s\n' '**/*_question_manifest.json' '**/*_manifest_update.tmp' >> .gitignore`  
Commit this update.  
(This will be done automatically when using `createrepo.php`.)
- From the commandline in the `cli` folder run `importwholecoursetomoodle.php`. There are a number of options you can input. List them all with `php importwholecoursetomoodle.php -h`. You can use -shortname or --longname on the command line followed by a space and the value.

|Short|Long|Required value|
|-|-|-|
|i|moodleinstance|Key of Moodle instance in  moodleinstances to use. Should match end of instance URL.|
|r|rootdirectory|Directory on user's computer containing repos.|
|f|manifestpath|Filepath of manifest file relative to root directory.|
|d|directory|Directory of repo on users computer containing "top" folder, relative to root directory.|
|s|subdirectory|Relative subdirectory of repo to actually import.|
|k|targetcategory|Category to import a subdirectory into.
|a|targetcategoryname|Category to import a subdirectory into.
|c|coursename|Unique course name.
|n|instanceid|Numerical id of the course.
|t|token|Security token for webservice.
|h|help|
|u|usegit|Is the repo controlled using Git?
|x|ignorecat|Regex of categories to ignore - add an extra leading / for Windows.

Examples:

Example 1:
To update the questions in Moodle from the repo, point the script to your course manifest file. (Quiz structures will not be updated.)
`php importrepotomoodle.php -f "scratch-wholecourse/scratch-course"`

Example 2:
To import course context questions, quiz context questions and quiz structures into a course for the first time, you will need to point the script to the course directory and the course to import into (using either coursename -c or id -n). You will also need to specify context level but this will always be course (`-l "course"`) for Moodle 4 and (`-l "module"`) for Moodle 5+.

`php importwholecoursetomoodle.php -r "C:\question_repos" -d "scratch-wholecourse/scratch-course" -l "course" -c "Course 2"`

## Scenario 1: Re-importing questions into Moodle when the manifest files already exist

You should specify the course manifest file path and context will be extracted from that. You can still enter a subdirectory to only re-import some of the questions.

Import will only be possible if there are not updates to the questions in Moodle which haven't been exported.

Only questions that have changed in the repo since the last import will be imported to Moodle (to avoid creating a new version in Moodle when nothing has changed).

Quiz structure will not be updated.

## Scenario 2: Importing questions into Moodle from an existing repo

e.g. You have exported questions from one Moodle instance to create the repo and you want to import them into a different instance

Importing will create a manifest file specific to the Moodle instance and context in each of the context directories of the repo. This links files in the repo to specific questionbankentries in the Moodle instance.

You will need to specify the course you want to import the questions and quizzes into. You will need to supply the course name. Alternatively, the instanceid of the course can be supplied. This is available from the URL while browsing the course in Moodle ('?id=XXX').

If you only want to import a certain question category (and its subcategories) within the course you will need to supply the path of the corresponding folder within your repo relative to the 'top' category e.g. 'category-1/subcategory-2'.

If the manifest file was created using targeting, import will always use the same subcategory and subdirectory. If the manifest was created using a subdirectory or subcategory but not targeted, then import will use these by default but they can be overridden by specifying `subdirectory` when running the script. See the [README file](../README.md#Using-subsets-of-materials) for details on targeting and subselections.

For Moodle 5+, there is no longer a course context question bank. Questions are contained in module level question banks. Gitsync can be made to treat a course with a single question bank like an old course, however. Add `-l "module"` to the command line parameters and `cmid` from the URL of the question bank as `--instanceid` or `-n`.

`php importwholecoursetomoodle.php -l 'module' -n 7 -d 'moodle-5/scratch-course'`

## Deletion

A check will be run to see if there are questions in the manifest for each context that do not have a file in the repo. These will be listed.

A check will be run to see if there are questions in the course and quizzes in Moodle that are not in the manifest. These will be listed.

To delete the questions from Moodle and tidy the manifest, run `deletefrommoodle.php` for the course or an individual quiz.

### On failure
- If the script fails, it can be safely run again once the issue has been dealt with. Pending updates to the manifest file are stored in a temporary file in the root directory and these will be picked up at the start of the new run, avoiding multiple new versions of a question being created in Moodle.
