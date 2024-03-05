# Different scenarios for maintaining sets of questions using a Git repository

It is recommended you at least skim the whole of this document before attempting to follow any of the examples as it deals with a number of different scenarios for using Gitsync. You need to decide which applies to you and your needs. For additional information on each of the PHP scripts as you try to use them, see:
- Creating a repo - [createrepo.php](createrepo.md)
- Importing questions to Moodle - [importrepotomoodle.php](importrepotomoodle.md)
- Exporting questions from Moodle - [exportrepofrommoodle.php](exportrepofrommoodle.md)

For detailed information on what happens at each stage of the process, see [Process Details](processdetails.md).

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

You'll get a confirmation of the context and category with the option to abort before performing the actual export. A manifest file will be created in the specified directory.

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
