# Detailed list of steps that take place for each CLI process

## On Creation - createrepo.php:
- Export all questions from Moodle.
- Set 'importedversion' and 'exportedversion' to current version in Moodle.
- Commit changes.
- Update 'moodlecommit' to 'currentcommit' for all files to this initial commit.

## On Export (from Moodle to the local file system) - exportrepofrommoodle.php:
- Check repo for uncommitted changes. Abort if there are any.
- Export all questions in manifest from Moodle (as it's safe to overwrite repo content). Update 'exportedversion' number in manifest.
- Export all questions in Moodle but not in manifest. Add to manifest.
- Remove entries from manifest where question no longer exists in Moodle.
- Leave files in repo that do not have a linked question in Moodle. (Question will be created on next import.)
- User will need to check and commit changes.

## On Import (from the local file system to Moodle) - importrepotomoodle.php:
- Check repo for uncommitted changes. Abort if there are any.
- Check all questions in Moodle. If they're in the manifest but version number is not the same as 'importedversion' or 'exportedversion' in manifest, abort. User will need to export and reconcile changes. (This will update 'exportedversion' in the manifest and allow the import to proceed.)
- Update the 'currentcommit' hash in the manifest for each question file. (This is the commit on which the file was last changed.)
- Import all questions which don't have an entry in the manifest where 'currentcommit' and 'moodlecommit' match. (This will include any questions that have been exported after repo creation but have not been re-imported since. They might not have been changed but we have no way of knowing short of re-exporting and comparing strings - a user can export a question and then alter it before committing it.) Add a manifest entry (where moodlecommit=currentcommit & importedversion=exportedversion) or set 'moodlecommit' to 'currentcommit' and 'importedversion' to current Moodle version of question.
- Warn of questions in Moodle which don't have a file in the repo. Remove from manifest.
- Warn of questions in Moodle which aren't in manifest.

## On Delete - deletefrommoodle.php:
- Offer to delete questions in Moodle which don't have a file in the repo. Remove from manifest.
- Offer to delete questions in Moodle which aren't in manifest.
