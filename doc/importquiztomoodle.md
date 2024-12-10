# Importing a quiz into Moodle including both questions and quiz structure

## Prerequisites
- Set up the [webserver](webservicesetup.md) on the Moodle instance.
- Set up your [local machine](localsetup.md).

## Importing
This script is only for the initial import of a quiz into Moodle including both questions and quiz structure. To update the questions later, use `importrepotomoodle.php`.
- Create a repository of questions with a folder hierarchy akin to question categories i.e. top/category/subcategory or use `createrepo.php` to create a repo from existing questions on Moodle.
- Each category below top should have a `gitsync_category.xml` file containing a 'category question' with the details of he category. See the `testrepo` folder in this repository for an example.
- If you have created a repository (or copied one) make sure you have a `.gitignore` file and it ignores gitsync's manifest and temporary files.  
`touch .gitignore`  
`printf '%s\n' '**/*_question_manifest.json' '**/*_manifest_update.tmp' >> .gitignore`  
Commit this update.  
(This will be done automatically when using `createrepo.php`.)
- You will also need a quiz structure file.
- From the commandline in the `cli` folder run `importquiztomoodle.php`. There are a number of options you can input. List them all with `php importquiztomoodle.php -h`. You can use -shortname or --longname on the command line followed by a space and the value.

|Short|Long|Required value|
|-|-|-|
|i|moodleinstance|Key of Moodle instance in  moodleinstances to use. Should match end of instance URL.|
|r|rootdirectory|Directory on user's computer containing repos.|
|p|nonquizmanifestpath|Filepath of non-quiz manifest file relative to root directory.|
|a|quizdatapath|Filepath of quiz data file relative to root directory.
|d|directory|Directory of repo on users computer containing "top" folder, relative to root directory.|
|s|subdirectory|Relative subdirectory of repo to actually import.|
|l|contextlevel|Context in which to place questions. Set to system, coursecategory, course or module
|c|coursename|Unique course name for course or module context.
|n|instanceid|Numerical id of the course, module of course category.
|t|token|Security token for webservice.
|h|help|
|x|ignorecat|Regex of categories to ignore - add an extra leading / for Windows.

Examples:

`php importquiztomoodle.php -d 'quizexport' -n 2`

This will import the quiz in the `quizexport` directory to the course with `id` 2. The first quiz structure file to be found in that directory will be used. If you have multiple structure files in your repo you will need to specify `--quizdatapath`.

`php importquiztomoodle.php -d 'quizexport' -f 'course1/instance2_course_course-1_question_manifest.json'`

Import the quiz into the course specified in the manifest file. The quiz structure can contain questions from the manifest file and the quiz context.

## Deletion

A check will be run to see if there are questions in the context/category in the manifest that do not have a file in the repo. These will be listed.

A check will be run to see if there are questions in the context in Moodle that are not in the manifest. These will be listed.

To delete the questions from Moodle and tidy the manifest, run deletefrommoodle.php

### On failure
- If the script fails, it can be safely run again once the issue has been dealt with. Pending updates to the manifest file are stored in a temporary file in the root directory and these will be picked up at the start of the new run, avoiding multiple new versions of a question being created in Moodle.
