# Synchronize questions in a Moodle question bank with files in an external git repository

The purpose of this module is to synchronise questions from part of a moodle question bank to an external file system.

The motivating use-case is to share questions either (i) between multiple courses on a single moodle site or (ii) between multiple sites.
If the external file system is part of a version control repository (e.g. git), then version control tools can be used on the external file system to track differences between versions, merge changes, use branches to maintain different versions, and so on.

This tool helps users export to their local file system, removing the need to access to the file system on the server directly.

This module will not help users make judgements on how to organise their question banks!  Users must consider how to arrange their materials in such a way that makes sense.

The basic unit is a "folder of questions", which may contain sub-folders.

* On the external file system these "folder of questions" are literally directories of Moodle .xml files, each file is a single question file.
* On Moodle these "folder of questions" are categories in the Moodle question bank.

A "manifest file" tracks the link between the external file system and the moodle question bank.

The position of the manifest file in the external file system dictates the top-level directory.  On the external file system, the software will look down the directory tree for question files.

## Using version control tools to track changes.

A single manifest file sits in the same directory as the .git directory.
Importing and exporting questions from the moodle question bank to/from an external file system enables a user to track changes (and externally backup) questions in a designated part of a moodle question bank.

## Using version control tools to share materials

One or more manifest files sits in the same directory as the .git directory.
The user can then share materials in two ways.

1. Use .git as version control.  Users with their own fork of the repository will have a parallel manifest file in their fork to synchronise questions from their external file system to part of a moodle question bank.
2. More than one manifest file sits in the same directory as the .git directory.  A single user can then synchronise questions from their external file system to different parts of a moodle question bank on one site, or different moodle sites.  Version control is very useful in this situation for tracking changes, backing up and resolving possible conflicts between versions.

For example a user creates two branches to match two different Moodle courses: Exporting and committing to separate branches enables the git-merge features to help resolve conflicts.  Branches can diverge, if necessary, to reflect genuine differences between courses on moodle, but shared materials can still be merge between branches.  (But see "duplicates and waste" below.)

## Using subsets of materials

A user might want to manage sub-sets of questions.  This must be done by positioning the manifest file inside the external file system.

Putting a manifest file in a sub-directory of a git repository will help synchronise questions from part of a larger external library of materials (the git repro) to a moodle question bank.  This has the advantage of not importing irrelevant material from a large external library of materials into moodle. (But see "duplicates and waste" below.)

Putting a number of separate git repositories into sub-directories below a manifest file on the external file system will help synchronise questions from a single large moodle question bank into separate external git repositories.  This has the advantage of a single, clean, import/export call.

The logical disconnection of the synchronise questions from the .git repository provides lots of options.  The position of the manifest file in the external file system dictates the top-level directory on the external file system, and the software will look down the directory tree for question files to syncronise with Moodle.

## Duplicates and waste

Expert users' time is much more valuable than DB storage or file storage.  If users import/export materials which are not needed in a particular moodle course then they could simply be ignored.....up to a point.

The logical disconnection of the synchronise questions from the .git repository provides lots of options when combined with version control features such as branches.  Users will need to match their expertise with the complexity of the way they choose to use the tool.  We expect the most common use will be one or more manifest files in the top-level of the git repository.

This module will not help users make judgements on how to organise their question banks!  Users must consider how to arrange their materials in such a way that makes sense.

Users remain entirely responsible for deciding which copy of the materials is the "golden copy", remembering this decision, and looking after their digital materials!

## Using other tools

The purpose of this module is to synchronise questions from part of a moodle question bank to an external file system.  Users are free to create moodle xml files with external tools and then use this tool to import them to moodle.  For example, users have asked for tools to create similar questions from templates.  If such questions end up on an external file system in a suitable way, this tool could be used to import them into part of a moodle question bank.
