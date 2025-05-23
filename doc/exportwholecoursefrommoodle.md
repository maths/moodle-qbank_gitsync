# Creating a repo for a course and all its quizzes from Moodle

## Prerequisites
- Set up the [webserver](webservicesetup.md) on the Moodle instance.
- Set up your [local machine](localsetup.md).
- Import a repo into Moodle using `importwholecoursetomoodle.php` or create a repo from Moodle using `createwholecourserepo.php`.

## Exporting
- From the commandline in the `cli` folder run `exportwholecoursefrommoodle.php`. There are a number of options you can input. List them all with `php exportwholecoursefrommoodle.php -h`. You can use -shortname or --longname on the command line followed by a space and the value.

|Short|Long|Required value|
|-|-|-|
|i|moodleinstance|Key of Moodle instance in $moodleinstances to use. Should match end of instance URL.|
|r|rootdirectory|Directory on user's computer containing repos.|
|f|manifestpath|Filepath of manifest file relative to root directory.|
|s|subcategory|Relative subcategory of repo to actually export.|
|q|questioncategoryid|Numerical id of subcategory to actually export.
|t|token|Security token for webservice.|
|h|help|
|u|usegit|Is the repo controlled using Git?
|x|ignorecat|Regex of categories to ignore - add an extra leading / for Windows.

Examples:

This is very similar to [`exportrepofrommoodle.php`](exportrepofrommoodle.md) but normally Gitsync retrieves questions within a Moodle context, returning all or a subselection of question categories with the repo directory structure matching the category structure in Moodle. Courses and quizzes are in separate contexts, however. `exportwholecoursefrommoodle.php` keeps quizzes with their parent course in a single repo.

`php exportwholecoursefrommoodle.php -f "scratch-wholecourse\scratch-course\moodle1_course_course-1_question_manifest.json"`

The manifest file should have been created by `createwholecourserepo.php` if the repo was initially created by exporting questions from Moodle or `importwholecoursetomoodle.php` if questions were initially imported into Moodle from an existing repo. When creating a whole course manifest, additional information is stored in the manifest linking the local directories of the quizzes to the instances of the quizzes in Moodle.

If you only want to export a certain question category (and its subcategories) within the context you will need to supply the category's name relative to the 'top' category e.g. 'category 1/subcategory 2'. Alternatively you can supply the questioncategoryid which is available in the URL ('&category=XXX') when browsing the category in the question bank. (This is harder to find on more recent versions of Moodle - go to the categories page from the question bank and then click on the category you want to return to the question bank and check for '&cat' in the URL.)

If the manifest file was created using targeting, export will always use the same subcategory and subdirectory. If the manifest was created using a subdirectory or subcategory but not targeted, then export will use these by default but they can be overridden by specifying `subcategory` when running the script. See the [README file](../README.md#Using-subsets-of-materials) for details on targeting and subselections.

Export will only be possible if there are no uncommitted changes in the repo. After the export, the manifest will be tidied to remove any entries where the question is no longer in the Moodle. (The manifest is the link between your repo and Moodle and you can't link to something which isn't there.)

### On failure

- If the script fails, discard changes in the repository (e.g. with the `git reset` command) and run the `php exportwholecoursefrommoodle.php` again once the issue has been dealt with. All questions will be exported afresh.