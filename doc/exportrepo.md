# Updating a repo with the latest version of questions in Moodle

## Prerequisites
- Set up the [webserver](webservicesetup.md) on the Moodle instance.
- Set up your [local machine](localsetup.md).
- Import a repo into Moodle using `importrepo.php` or create a repo from Moodle using `createrepo.php`.

## Exporting
- From the commandline in the `cli` folder run `exportrepo.php`. There are a number of options you can input. List them all with `php exportrepo.php -h`. You can use -shortname or --longname on the command line followed by a space and the value.

|Short|Long|Required value|
|-|-|-|
|i|moodleinstance|Key of Moodle instance in $moodleinstances to use. Should match end of instance URL.|
|r|rootdirectory|Directory on user's computer containing repos.|
|f|manifestpath|Filepath of manifest file relative to root directory.|
|s|subcategory|Relative subcategory of repo to actually export.|
|q|questioncategoryid|Numerical id of subcategory to actually export.
|t|token|Security token for webservice.|
|h|help|

Examples:

`php exportrepo.php -r "C:\question_repos" -f "source_1\edmundlocal_system_question_manifest.json"`

The manifest file should have been created by createrepo.php if the repo was initially created by exporting questions from Moodle or importrepo.php if questions were initially imported into Moodle from an existing repo.

The context of the questions to export will be extracted from the manifest file.

If you only want to export a certain question category (and its subcategories) within the context you will need to supply the category's name relative to the 'top' category e.g. 'category 1/subcategory 2'. Alternatively you can supply the questioncategoryid which is available in the URL ('&category=XXX') when browsing the category in the question bank.

Export will only be possible if there are no uncommitted changes in the repo. After the export, the manifest will be tidied to remove any entries where the question is no longer in the Moodle. (The manifest is the link between your repo and Moodle and you can't link to something which isn't there.)

### On failure

- If the script fails, discard changes in the repository (e.g. with the `git reset` command) and run the `php exportrepo.php` again once the issue has been dealt with. All questions will be exported afresh.