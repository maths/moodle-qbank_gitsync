# Git flow for 2 sources

Steps for dealing with 2 different Moodle sources of a set of questions. Files from a source (and also for the gold copy) have their own branch in Git but also their own location on the user's computer:

In umbrella folder, create 'gold' master branch and then clone to store files for different sources in different folders:

`git init master`
`git clone /home/efarrow1/gitquestions/master source_1`
`git clone /home/efarrow1/gitquestions/master source_2`

Switch to branches for each source:
In source_1:
`git checkout -b source_1`

In source_2:
`git checkout -b source_2`

Set config file.

Export initial repo from Moodle:
`php createrepo.php -l course -c "Course 1" -d "/source_1" -s "/top/Source 1"`
`php createrepo.php -l course -c "Course 1" -d "/source_2" -s "/top/Source 2"`

For Windows we have slash issues! Works like this but obviously not ideal:
`php createrepo.php -l course -c "Course 1" -d '\source_2' -s "\top/Source 2"`

Set up ignore manifest file and do initial commit of each source (now done by createrepo script):
In source_1:
`touch .gitignore`
`cat >> .gitignore << EOF`
`/*_question_manifest.json`
`EOF`
`git add .`
`git commit -m "Initial contents"`
In source_2:
`touch .gitignore`
`cat >> .gitignore << EOF`
`/*_question_manifest.json`
`EOF `
`git add .`
`git commit -m "Initial contents"`

To import/export:

Unix:
`php exportrepo.php -f "/source_2/edmundlocal_course_Course 1_question_manifest.json"`
`php importrepo.php -l course -c "Course 1" -d "/source_2" -s "/top/Source 2"`

Windows:
`php exportrepo.php -f "\source_2\edmundlocal_course_Course 1_question_manifest.json"`
`php importrepo.php -l course -c "Course 1" -d "\source_2" -s "\top/Source 2"`

On fresh export from Moodle, check whether repo has changed first and do not export if so. (Now done in script) :
`git add . # Make sure everything changed has been staged`
`git update-index --refresh # Removes false positives due to timestamp changes`
`git diff-index --quiet HEAD -- || echo "Deal with changes first" # First command fails if there are changes.`
Do the same for import

## On Creation:
- Export all questions from Moodle.
- Set 'version' to current version in Moodle.
- Commit changes.
- Update 'moodlecommit' to 'currentcommit' for all files to this initial commit.

## On Export:
- Check repo for uncommitted changes. Abort if there are any.
- Export all questions in manifest from Moodle (as it's safe to overwrite repo content). Update 'exportedversion' number in manifest.
- Export all questions in Moodle but not in manifest. Add to manifest.
- Remove entries from manifest where question no longer exists in Moodle.
- Leave files in repo that do not have a linked question in Moodle. (Question will be created on next import.)
- User will need to check and commit changes.

## On Import:
- Check repo for uncommitted changes. Abort if there are any.
- Check all questions in Moodle. If they're in the manifest but version number is not the same as 'version' or 'exportedversion' in manifest, abort. User will need to export and reconcile changes. (This will update 'exportedversion' in the manifest and allow the import to proceed.)
- Update the 'currentcommit' hash in the manifest for each question file. (This is the commit on which the file was last changed.)
`git log -n 1 --pretty=format:%H -- 'relative_file_path'`
- Import all questions which don't have an entry in the manifest where 'currentcommit' and 'moodlecommit' match. Add manifest entry or set 'moodlecommit' to 'currentcommit' and 'version' to current Moodle version of question.
- Offer to delete questions which don't have a file in the repo. Remove from manifest.
- Offer to delete questions which aren't in manifest.
