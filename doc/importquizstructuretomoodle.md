# Importing a quiz structure into Moodle

## Prerequisites
- Set up the [webserver](webservicesetup.md) on the Moodle instance.
- Set up your [local machine](localsetup.md).
- Create a blank quiz in Moodle and then import any questions using `importrepotomoodle.php`.

## Importing
- From the commandline in the `cli` folder run `importquizstructuretomoodle.php`. There are a number of options you can input. List them all with `php importquizstructuretomoodle.php -h`. You can use -shortname or --longname on the command line followed by a space and the value.

|Short|Long|Required value|
|-|-|-|
|i|moodleinstance|Key of Moodle instance in $moodleinstances to use. Should match end of instance URL.|
|r|rootdirectory|Directory on user's computer containing repos.|
|p|nonquizmanifestpath|Filepath of manifest file of another context relative to root directory.|
|f|quizmanifestpath|Filepath of quiz manifest file relative to root directory.|
|a|quizdatapath|Filepath of quiz data file relative to root directory.
|t|token|Security token for webservice.|
|h|help|

Examples:

`php importquizstructuretomoodle.php -r "C:\question_repos" -f "quiz_1\instance2_module_stack1_quiz-1_question_manifest.json"`

The manifest files should have been created by createrepo.php if the repo was initially created by exporting questions from Moodle or importrepotomoodle.php if questions were initially imported into Moodle from an existing repo.

The quizdata file contains the structure of the quiz and the locations of the questions, the quizmanifest and nonquizmanifest link those locations to matching questions in Moodle. You must supply at least one of `quizmanifestpath` and `quizdatapath`. If you supply just one, Gitsync will calculate the location and name of the other based and its normal naming convention. If the quiz contains questions from a context other than its own (most-likely the course context), you will need to include the path to the manifest of the other context (`--nonquizmanifestpath`). In the quizdata file, those questions will have a relative filepath for the other repo (`nonquizfilepath`).

If you have multiple quiz structure files, you will need to supply both `quizmanifestpath` and `quizdatapath`. (This is if you're using the same questions in different quizzes in Moodle but only want to have one question repo.)

If you update the quiz structure in Moodle you can export the structure again and the structure file in the repo will be updated. If you update the quiz structure in the repo and want to update Moodle, create a new quiz and start again.

### On failure

- If the script fails, delete the quiz in Moodle and start again.