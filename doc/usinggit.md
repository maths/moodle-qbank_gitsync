# Different scenarios for maintaining sets of questions using a Git repository

It is recommended you at least skim the whole of this document before attempting to follow any of the examples as it deals with a number of different scenarios for using Gitsync. You need to decide which applies to you and your needs. For additional information on each of the PHP scripts as you try to use them, see:
- Creating a repo - [createrepo.php](createrepo.md)
- Importing questions to Moodle - [importrepotomoodle.php](importrepotomoodle.md)
- Exporting questions from Moodle - [exportrepofrommoodle.php](exportrepofrommoodle.md)
- Importing quizzes to Moodle - [importquiztomoodle.php](importquiztomoodle.md)

For detailed information on what happens at each stage of the process, see [Process Details](processdetails.md).

If you want to track a course and all its quizzes in a single repo, you will need to use different scripts that function in a similar way to the single context scripts:
- Creating a repo - [createwholecourserepo.php](createwholecourserepo.md)
- Importing questions to Moodle - [importwholecoursetomoodle.php](importwholecoursetomoodle.md)
- Exporting questions from Moodle - [exportwholecoursefrommoodle.php](exportwholecoursefrommoodle.md)

(See [Quizzes](#quizzes).)

# Moodle 5 update

For Moodle 5+, there are no longer course, course category or system context question banks. Questions are contained in module level question banks. This makes things simpler. Where command line parameters for `contextlevel` and `instanceid` are required you will always need to use `module` and the value of `cmid` from the URL of the question bank.

e.g. `php createrepo.php -l module -n 2 -d "master"`

## Maintaining a one-to-one link between a Moodle instance and a Git repo

### Creating a Git repo from questions in Moodle

If you have a category of questions in a Moodle question bank and want to store them in the file system of your local computer in a Git repo.

1. Identify a category in Moodle.
2. Create an empty git repository on your local disc.  Within the directory defined by your config `$rootdirectory` the following command creates an empty directory `master`:

`git init master`

To export questions from moodle to the git repo, from the gitsync cli directory on your local computer, you need to:

`php createrepo.php -l course -c "Course 1" -d "master" -s "Source 1/subcat 2_1"`

* `-h` gives command line documentation.
* `-l` is the context level within Moodle, e.g. the "course level".
* `-c` is the Moodle coursename.  This must be the full name, not the short name.
* `-s` is the subcategory of questions to actually export.
* `-d` is the directory of the repo within the root directory.

You can use context instance id and subcategory id instead:

`php createrepo.php -l course -n 2 -d "master" -q 80`

* `-n` the context instance id, which is the course id in this case.
* `-q` the question category id

You can also ignore certain categories (and their descendants) using `-x` and a regex expression to match the category name(s) within Moodle. (Add an extra leading / in Windows e.g. "//^.*DO_NOT_SHARE$/".)

`php createrepo.php -l course -n 2 -d "master" -q 80 -x "/^.*DO_NOT_SHARE$/"`

You'll get a confirmation of the context and category with the option to abort before performing the actual export. A manifest file will be created in the specified directory. The subdirectory/subcategory and any regex for ignoring categories you use will be stored in the manifest and used on import and export unless you override them using `-s` and/or `-x` (e.g. `-s "top" -x "/^\b$/"`).

To import/export changes:
 
`php exportrepofrommoodle.php -f "master/instancename_contextlevel_contextname_question_manifest.json" -s "Source 1/subcat 2_1"`  
`php importrepotomoodle.php -f "master/instancename_contextlevel_contextname_question_manifest.json" -s "Source-1/subcat-2_1"`

* `-f` the filepath of the manifest file relative to the root directory.
* `-s` the question subcategory for export or the subdirectory for import. These are essentially the same thing but for export we're working within Moodle so you need to supply the question categories as named in Moodle (or alternatively the category id using `-q`). For import we're working from the repo so we're dealing with the sanitised versions of the category names that are used for folder names within the repo.

After initial creation of the repo, you will be able to move questions between categories/contexts in Moodle and they will remain linked to the file in your repo. The file will not move within the repo, however. Try to avoid moving questions within the repo. If you do move the question within the repo, gitsync will interpret this as the question being deleted and a new one being created. On import, a new question will be created in Moodle and you will be prompted to delete the old one. To prevent this, you (and everyone who shares the repo!) will need to manually update the filepath in the manifest file to link to the question's new location.

**If you move questions around in Moodle, it's vital you keep backups of your manifest file** as creating a new one may be difficult. Gitsync itself creates backups of previous versions but this won't help you if your computer dies. If you haven't moved questions around, you can just use createrepo to create a new manifest. If you have, you'll need to createrepo for all the relevant contexts and then Frankenstein something together.

On export, the manifest will be tidied to remove questions that are no longer in Moodle. The corresponding files will not be removed from the repo, however, and will create a new question in Moodle on the next import.

On import, you will be notified of questions that are in Moodle but have no corresponding manifest entry and/or file in the repo. Run deletefrommoodle.php to delete them from Moodle if required.

`php deletefrommoodle.php -f "master/instancename_contextlevel_contextname_question_manifest.json"`

### Importing an existing Git repo into Moodle

If you have a repo of questions and want to import them to Moodle, use importrepotomoodle.php with context information as your starting point rather than createrepo.php.

`php importrepotomoodle.php -l course -n 2 -d "master" -s "Source-1/cat-2"`

After that, import, export and deletion are the same as above.

## Maintaining a Git repo of questions on two moodle sites

Steps for dealing with 2 different Moodle sources of a set of questions e.g. you're using the same questions on two different courses and you want to keep changes in sync. Files from a source (and also for the gold copy) have their own branch in Git but also their own location on the user's computer. (You don't need separate locations, particularly if you're used to Git, but it makes the process clearer and is liable to cut down on errors caused by mismatching branches and manifest files.):

For this to work, the two sources need to have the same relative category path within their contexts in Moodle.

In your root folder, create a 'gold' master branch and then clone the repo to store files for different sources in different folders:

`git init master`
In master: `git commit --allow-empty -m "Empty commit"` 
`git clone master source_1`  
`git clone master source_2`  

Create branches in each directory:
In source_1:  
`git checkout -b source_1`

In source_2:  
`git checkout -b source_2`

Export the initial repos from Moodle just like in the one-to-one setup:  
`php createrepo.php -l course -c "Course 1" -d "source_1"`  
`php createrepo.php -l course -c "Course 2" -d "source_2"`

To import/export:

`php exportrepofrommoodle.php -f "/source_2/instancename_course_coursename_question_manifest.json"`  
`php importrepotomoodle.php -f "/source_2/instancename_course_coursename_question_manifest.json"`

### Merge/compare, etc

#### Create initial version of master branch
The master directory is essentially acting as a remote repository. Push one of your sources to it, create your master branch and merge them.
In Source_1:
`git push origin source_1`

In master:
`git merge source_1`

#### Compare with your other source
In Source_2:
`git push origin source_2`

In master:
`git diff master source_2` to see the differences.
`git merge source_2` - all the differences will be flagged as merge conflicts which you will need to resolve. then `git add .` and `git commit -m "Merge differences from source_2"`

Much of this will be easier if you use a Git desktop application to help. Be careful when resolving merge conflicts and ensure your editor is using the same line endings as the rest of the file. (In Windows, you can use Notepad++ to display all the line endings to make sure.)

#### Apply changes to source branches

Go back into each of Source_1 and Source_2 and `git pull origin master`. This will prevent merge conflicts in future.

#### Starting from a single source

If you have some questions on Moodle and want to create a second copy elsewhere (e.g. another course or Moodle instance) and keep them in synch, the instructions are very similar. When exporting the initial repos simply create them both from same Moodle context, then import one of the repos to your new context. (You will have a superfluous manifest file in your imported repo. You can delete it if you want but that's not necessary.)

## Multiple users

If multiple users are using the same remote repo on different Moodle instances/contexts that should only have the same issues as maintaining any other code base.

If multiple users are using a repo to maintain the same context on the same instance, the manifest file will need to be shared in some fashion. One option is to add it to the repo. (If anyone else is using the repo this may require negotiation and/or fun with branches, etc.)

Update .gitignore to just `manifest_backups/*_question_manifest.json`

An alternative to having the manifest in the repo is a one off copy of the manifest to the second user after they have cloned the repo. Each user then performs an exportrepofrommoodle before making changes as if the other user were updating questions manually within Moodle.

## Deleted questions

If you have multiple repos and you delete questions in one because you don't want them to appear in a particular Moodle instance then you will need to take care when merging to and from the master repository. The questions need to stay in master and not be re-introduced to your other branch. Run the merge withou committing the result and then go into the repository and revert the deletions/additions as necessary before committing.

`git merge --no-commit --no-ff source_branch_name`

## Quizzes

Quiz contexts can be exported and imported in the same way as other contexts but it is more useful to also include the quiz structure. `createrepo.php` and `exportrepofrommoodle.php` will attempt to export the quiz structure when the contextlevel is set to `module`. This may not work as a quiz can contain questions from multiple contexts. Gitsync can currently handle quizzes that have questions in 2 contexts but this requires the user to specify a manifest filepath for the nonquiz context (either in the initial export/create or later using `exportquizstructurefrommoodle.php`). In most cases, this will be a course manifest file. The quiz structure is stored in a file `quiz-name_quiz.json` at the top of the repo. Unlike the manifest, the quiz structure is independent of Moodle and should be tracked by Git. It holds basic quiz information such as headings and the relative location of questions within the directory of the quiz context and of the secondary context.

When importing a quiz into a different course, `importquiztomoodle.php` should be used. This creates a quiz, imports the quiz context questions and then populates the structure of the quiz. A quiz structure should only be imported once. Update it manually within Moodle. Using `importrepotomoodle.php` for a `module` context will simply update the questions in Moodle.

### Whole course

Normally Gitsync retrieves questions within a Moodle context, returning all or a subselection of question categories, with the repo directory structure matching the category structure in Moodle. Courses and quizzes are in separate contexts, however. There are scripts specifically to export and import a 'whole course' to and from a single Git repo with the course and quizzes in sibling directories. The course and quizzes still have separate manifests so can also be imported/exported individually if required. The manifest for the course links the quiz instance ids in Moodle to the quiz directories in the repo.

## Quiz examples

### To handle a quiz that only uses questions from its own context
- `createrepo.php` will export the quiz structure along with the questions into the new repo.
- `exportrepofrommoodle.php` will update the quiz structure along with the questions in the repo.
- Use `importquiztomoodle.php` for initial import of questions and structure into a Moodle instance. (The quiz will be created within a specified course.)
- Use `importrepotomoodle.php` to update questions in Moodle.
- Manually update structure in Moodle.

Example:  
Initialise repo for quiz:  
`git init quizexport`  
Export quiz with `cmid=50` into directory `quizexport` (assuming rootdirectory, token, moodleinstance, usegit, etc, all set in your config file.):  
`php createrepo.php -d 'quizexport' -l module -n 50`  
Export questions/structures again after updates in Moodle:  
`php exportrepofrommoodle.php -f 'quizexport/instance1_module_course-1_quiz-only_question_manifest.json'`  
Import questions again after updates in the repo:  
`php importrepotomoodle.php -f 'quizexport/instance1_module_course-1_quiz-only_question_manifest.json'`  
Create quiz and import into course with `id=2`:  
`php importquiztomoodle.php -d 'quizexport' -n 2`

### To handle a quiz that uses questions from another context
- `createrepo.php` will export the questions into the new repo but will not export the structure and will list the questions from other contexts unless you also supply a manifest file containing the additional questions.
- `exportrepofrommoodle.php` will update the questions in the repo (and the quiz structure if you supply the manifest file again).
- Use `exportquizstructurefrommoodle.php` to export just the quiz structure from Moodle if needed.
- Use `importquiztomoodle.php` for initial import of questions and structure into a Moodle instance. (The quiz will be created within a specified course.)
- Use `importrepotomoodle.php` to update questions in Moodle.
- Manually update structure in Moodle.

Example:  
Initialise repo for quiz:  
`git init quizexport`  
Export quiz with `cmid=50` into directory `quizexport` (assuming rootdirectory, token, moodleinstance, usegit, etc, all set in your config file.):  
`php createrepo.php -d 'quizexport' -l module -n 49 -p 'course1/instance1_course_course-1_question_manifest.json'`  
Export questions/structures again after updates in Moodle:  
`php exportrepofrommoodle.php -f 'quizexport/instance1_module_course-1_mixed-quiz_question_manifest.json' -p 'course1/instance1_course_course-1_question_manifest.json'`  
Import questions again after updates in the repo:  
`php importrepotomoodle.php -f 'quizexport/instance1_module_course-1_mixed-quiz_question_manifest.json'`  
Create quiz and import into course with `id=2`:  
`php importquiztomoodle.php -d 'quizexport' -n 2 -p 'course1/instance1_course_course-1_question_manifest.json'`  

### To handle a course and its quizzes in a single repo
- `createwholecourserepo.php` will export a course context and associated quizzes in sibling directories. As long as the quizzes only use questions from the course and their own context, the quiz structures will be exported.
- `exportwholecoursefrommoodle.php` will update the questions and quiz structures in the repo.
- Use `importwholecoursetomoodle.php` for initial import of course questions and quiz structures into Moodle. Also use it to update questions in Moodle. (Quizzes will be created within the course.)
- Manually update structure in Moodle.

Example:  
Initialise a repo for course and quizzes together:  
`git init course1whole`  
Export course with `id=3` into directory `course1` (assuming rootdirectory, token, moodleinstance, usegit, etc, all set in your config file.):  
`php createwholecourserepo.php -n 3 -d 'course1whole/course1'`  
(Moodle 5+ with course question bank `cmid=7`: `php createwholecourserepo.php -l 'module' -n 7 -d 'course1whole/course1'`)  
Export questions/structures again after updates in Moodle:  
`php exportwholecoursefrommoodle.php -f 'course1whole/course1/instance1_course_course-1_question_manifest.json'`  
Import questions again after updates in the repo:  
`php importwholecoursetomoodle.php -f 'course1whole/course1/instance1_course_course-1_question_manifest.json'`  
Import course questions and quizzes into course with `id=2`:  
`php importwholecoursetomoodle.php -d 'course1whole/course1' -l 'course' -n 2`  
(Moodle 5+ into course question bank `cmid=9`: `php importwholecoursetomoodle.php -d 'course1whole/course1' -l 'module' -n 9`)

You can use the normal filters like subcategory and ignorecategory e.g.:  
`php createwholecourserepo.php -n 3 -d 'course1whole/course1' -x '/subcat/'`  
`php importwholecoursetomoodle.php -f 'course1whole/course1/instance1_course_course-1_question_manifest.json' -x '/subcat/'`

For Moodle 5+, there is no longer a course context question bank. Questions are contained in module level question banks. Gitsync can be made to treat a course with a single question bank like an old course, however. Add `-l "module"` to the command line parameters and `cmid` from the URL of the question bank as `--instanceid` when creating the repo or importing into a new course.

`php createwholecourserepo.php -l 'module' -n 7 -d 'course1whole/course1'`  
`php importwholecoursetomoodle.php -l 'module' -n 9 -d 'course1whole/course1'`

It is recommended that you do not use Gitsync with courses that have multiple question banks.