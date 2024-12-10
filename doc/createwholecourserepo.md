# Creating a repo for a course and all its quizzes

## Prerequisites
- Set up the [webserver](webservicesetup.md) on the Moodle instance.
- Set up your [local machine](localsetup.md).

## Exporting
- From the commandline in the `cli` folder run `createwholecourserepo.php`. There are a number of options you can input. List them all with `php createwholecourserepo.php -h`. You can use -shortname or --longname on the command line followed by a space and the value.

|Short|Long|Required value|
|-|-|-|
|i|moodleinstance|Key of Moodle instance in $moodleinstances to use. Should match end of instance URL.|
|r|rootdirectory|Directory on user's computer containing repos.|
|d|directory|Directory of repo on user's computer containing "top" folder, relative to root directory.|
|s|subcategory|Relative subcategory of repo to actually export.|
|c|coursename|Unique course name for course or module context.
|q|questioncategoryid|Numerical id of subcategory to actually export.
|n|instanceid|Numerical id of the course category.
|t|token|Security token for webservice.
|h|help|
|x|ignorecat|Regex of categories to ignore - add an extra leading / for Windows.

This is very similar to [`createrepo.php`](createrepo.md) but normally Gitsync retrieves questions within a Moodle context, returning all or a subselection of question categories with the repo directory structure matching the category structure in Moodle. Courses and quizzes are in separate contexts, however. Use `createwholecourserepo.php` to keep quizzes with their parent course in a single repo.

You can use all the same command line arguments as a basic `createrepo` to narrow down the questions exported. You will need to supply either the course name or its ID. You will also need to supply the destination directory for the course - this should be a child directory of the main repo directory.

Example:

Assume you have correct information in config.php, i.e. the Moodle URL in `$moodleinstances`, and the webservice token stored in `$token`, the default moodle instance in `$instance` and the local root directory for your question files in `$rootdirectory`.

Assume you have a course called "Scratch" in Moodle. You would like all questions from the "top" level, and all sub-categories, to become files in a sub-directory "scratch-wholecourse/scratch-course" of your local `$rootdirectory` directory. You would also like to export the questions belonging to all the course's quiz contexts and the structures of those quizzes into "scratch-wholecourse".  

Create and initialise the "scratch-wholecourse" directory using `git init scratch-wholecourse` then run `php createwholecourserepo.php`.

`php createwholecourserepo.php -c "Scratch" -d "scratch-wholecourse/scratch-course" `

Along with the "scratch-course" directory, there should be a directory created by the script for each of the quizzes with names in the format "scratch-course_quiz_sanitised-quiz-name".

### On failure

- If the script fails, discard changes in the repository (e.g. with the `git reset` command) and run the `php createwholecourserepo.php` again once the issue has been dealt with.