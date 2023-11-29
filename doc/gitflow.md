# Maintaining a one-to-one link between a Moodle instance and a Git repo

## Creating a Git repo from questions in Moodle

If you have a category of questions in a Moodle question bank and want to store them in the file system of your local computer in a Git repo.

1. Identify a category in Moodle.
2. Create an empty git repository on your local disc.  Within the directory defined by your config `$rootdirectory` the following command creates an empty directory `master`:

`git init master`

To export questions from moodle to the git repo, from the cli directory of the PHP scripts, you need to:

`php createrepo.php -l course -c "Course 1" -d "master" -s "Source 1/subcat 2_1"`

* `-h` gives command line documentation.
* `-l` is the context level within Moodle, e.g. the "course level".
* `-c` is the Moodle coursename.
* `-s` is the subcategory of questions to actually export.
* `-d` is the directory of the repo within the root directory.

You can use context instance id and subcategory id instead:

`php createrepo.php -l course -n 2 -d "master" -q 80`

* `-n` the contect instance id, which is the course id in this case.
* `-q` the question category id

You'll get a confirmation of the context and category with the option to abort before performing the actual export. A manifest file will be created in the specified directory.

To import/export:
 
`php exportrepo.php -f "master/instancename_contextlevel_contextname_question_manifest.json" -s "Source 1/subcat 2_1"`
`php importrepo.php -f "master/instancename_contextlevel_contextname_question_manifest.json" -s "Source-1/subcat-2_1"`

* `-f` the filepath of the manifest file relative to the root directory.
* `-s` the question subcategory for export or the subdirectory for import. These are essentially the same thing but for export we're working within Moodle so you need to supply the question categories as named in Moodle (or alternatively the category id using `-q`). For import we're working from the repo so we're dealing with the sanitised versions of the category names that are used for folder names within the repo.

After initial creation of the repo, you will be able to move questions between categories/contexts in Moodle and they will remain linked to the file in your repo. The file will not move within the repo, however. Try to avoid moving questions within the repo. If you do move the question within the repo, gitsync will interpret this as the question being deleted and a new one being created. On import, a new question will be created in Moodle and you will be prompted to delete the old one. To prevent this, you (and everyone who shares the repo!) will need to manually update the filepath in the manifest file to link to the question's new location.

**If you move questions around in Moodle, it's vital you keep backups of your manifest file** as creating a new one may be difficult. Gitsync itself creates backups of previous versions but this won't help you if your computer dies. If you haven't moved questions around, you can just use createrepo to create a new manifest. If you have, you'll need to createrepo for all the relevant contexts and then Frankenstein something together.

On export, the manifest will be tidied to remove questions that are no longer in Moodle. The corresponding files will not be removed from the repo, however, and will create a new question in Moodle on the next import.

On import, you will be notified of questions that are in Moodle but have no corresponding manifest entry and/or file in the repo. Run deletefrommoodle.php to delete them from Moodle if required.

`php deletefrommoodle.php -f "master/instancename_contextlevel_contextname_question_manifest.json"`

## Importing an existing Git repo into Moodle

If you have a repo of questions and want to import them to Moodle, use importrepo.php with context information as your starting point rather than createrepo.php.

`php importrepo.php -l course -n 2 -d "master" -s "Source-1/cat-2"`

After that, import, export and deletion are the same as above.

# Maintaining a Git repo of questions on two moodle sites

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

`php exportrepo.php -f "/source_2/instancename_course_coursename_question_manifest.json"`  
`php importrepo.php -f "/source_2/instancename_course_coursename_question_manifest.json"`

## Merge/compare, etc

### Create initial version of master branch
The master directory is essentially acting as a remote repository. Push one of your sources to it, create your master branch and merge them.
In Source_1:
`git push origin source_1`

In master:
`git merge source_1`

### Compare with your other source
In Source_2:
`git push origin source_2`

In master:
`git diff master source_2` to see the differences.
`git merge source_2` - all the differences will be flagged as merge conflicts which you will need to resolve. then `git add .` and `git commit -m "Merge differences from source_2"`

Much of this will be easier if you use a Git desktop application to help. Be careful when resolving merge conflicts and ensure your editor is using the same line endings as the rest of the file. (In Windows, you can use Notepad++ to display all the line endings to make sure.)

### Apply changes to source branches

Go back into each of Source_1 and Source_2 and `git pull origin master`. This will prevent merge conflicts in future.

### Starting from a single source

If you have some questions on Moodle and want to create a second copy elsewhere (e.g. another course or Moodle instance) and keep them in synch, the instructions are very similar. When exporting the initial repos simply create them both from same Moodle context, then import one of the repos to your new context. (You will have a superfluous manifest file in your imported repo. You can delete it if you want but that's not necessary.)

# Multiple users

If multiple users are using the same remote repo on different Moodle instances/contexts that should only have the same issues as maintaining any other code base.

If multiple users are using a repo to maintain the same context on the same instance, the manifest file will need to be added to the repo. (If anyone else is using the repo this may require negotiation and/or fun with branches, etc.) If questions are not being moved around in Moodle, users could use exportrepo before making changes instead.
 
# Detailed list of steps that take place for each process

## On Creation:
- Export all questions from Moodle.
- Set 'importedversion' and 'exportedversion' to current version in Moodle.
- Commit changes.
- Update 'moodlecommit' to 'currentcommit' for all files to this initial commit.

## On Export (from Moodle to the local file system):
- Check repo for uncommitted changes. Abort if there are any.
- Export all questions in manifest from Moodle (as it's safe to overwrite repo content). Update 'exportedversion' number in manifest.
- Export all questions in Moodle but not in manifest. Add to manifest.
- Remove entries from manifest where question no longer exists in Moodle.
- Leave files in repo that do not have a linked question in Moodle. (Question will be created on next import.)
- User will need to check and commit changes.

## On Import (from the local file system to Moodle):
- Check repo for uncommitted changes. Abort if there are any.
- Check all questions in Moodle. If they're in the manifest but version number is not the same as 'importedversion' or 'exportedversion' in manifest, abort. User will need to export and reconcile changes. (This will update 'exportedversion' in the manifest and allow the import to proceed.)
- Update the 'currentcommit' hash in the manifest for each question file. (This is the commit on which the file was last changed.)
- Import all questions which don't have an entry in the manifest where 'currentcommit' and 'moodlecommit' match. (This will include any questions that have been exported after repo creation but have not been re-imported since. They might not have been changed but we have no way of knowing short of re-exporting and comparing strings - a user can export a question and then alter it before committing it.) Add a manifest entry (where moodlecommit=currentcommit & importedversion=exportedversion) or set 'moodlecommit' to 'currentcommit' and 'importedversion' to current Moodle version of question.
- Warn of questions in Moodle which don't have a file in the repo. Remove from manifest.
- Warn of questions in Moodle which aren't in manifest.

## On Delete
- Offer to delete questions in Moodle which don't have a file in the repo. Remove from manifest.
- Offer to delete questions in Moodle which aren't in manifest.
