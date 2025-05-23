# Updating a quiz repo with the latest version of a the quiz structure

## Prerequisites
- Set up the [webserver](webservicesetup.md) on the Moodle instance.
- Set up your [local machine](localsetup.md).
- Import a quiz repo into Moodle using `importrepotomoodle.php` or create a quiz context repo from Moodle using `createrepo.php`.

## Exporting
- From the commandline in the `cli` folder run `exportquizstructurefrommoodle.php`. There are a number of options you can input. List them all with `php exportquizstructurefrommoodle.php -h`. You can use -shortname or --longname on the command line followed by a space and the value.

|Short|Long|Required value|
|-|-|-|
|i|moodleinstance|Key of Moodle instance in $moodleinstances to use. Should match end of instance URL.|
|r|rootdirectory|Directory on user's computer containing repos.|
|p|nonquizmanifestpath|Filepath of manifest file of another context relative to root directory.|
|f|quizmanifestpath|Filepath of quiz manifest file relative to root directory.|
|t|token|Security token for webservice.|
|h|help|
|u|usegit|Is the repo controlled using Git?

Generally you should not need to run this script yourself. It is run automatically when exporting a quiz module using `createrepo.php`,
`exportrepofrommoodle.php` or `createwholecourserepo.php`.

Examples:

`php exportquizstructurefrommoodle.php -f "quiz_1\instance2_module_stack1_quiz-1_question_manifest.json"`

The manifest file should have been created by createrepo.php if the repo was initially created by exporting questions from Moodle or importrepotomoodle.php if questions were initially imported into Moodle from an existing repo.

The structure of the quiz will be extracted into a file with a name in the format quiz-name_quiz.json. The file contains the layout of the quiz and relative file paths to the questions (`quizfilepath`).

If the quiz contains questions from a context other than its own (most-likely the course context), you will need to include the path to the manifest of the other context (--nonquizmanifestpath). Those questions will have a relative filepath for the other repo (`nonquizfilepath`).

The questions must either be in the quiz context or the context of the nonquizmanifest. If you have questions in a third location, the export will fail.

If you update the quiz structure in Moodle you can export the structure again and the structure file in the repo will be updated.

### On failure

- If the script fails, discard changes in the repository (e.g. with the `git reset` command) and run the `php exportquizstructurefrommoodle.php` again once the issue has been dealt with.