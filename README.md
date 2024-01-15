# Synchronize questions in a Moodle question bank with files in an external git repository

The purpose of this module is to synchronise questions from part of a moodle question bank to an external file system.

The motivating use-case is to share questions either (i) between multiple courses on a single moodle site or (ii) between multiple sites.
If the external file system is part of a version control repository (e.g. git), then version control tools can be used on the external file system to track differences between versions, merge changes, use branches to maintain different versions, and so on.

This tool helps users export questions from a moodle question bank to their local file system, avoiding the need to access the file system on the server directly.

This module will not help users make judgements on how to organise their question banks!  Users must consider how to arrange their materials in such a way that makes sense.

The basic unit is a "folder of questions", which may contain sub-folders.

* On the external (local) file system a "folder of questions" is literally a directory of Moodle .xml files, each file is a single question file.
* On Moodle these "folder of questions" are categories in the Moodle question bank.

A "manifest file" tracks the link between the external file system and the moodle question bank.

The position of the manifest file in the external file system is the same location as the top-level directory.  On the external file system, the software will look down the directory tree for question files.

## Using version control tools to track changes.

A single manifest file sits in the same directory as the .git directory. Importing and exporting questions from the moodle question bank to/from an external file system enables a user to track changes (and externally backup) questions in a designated part of a moodle question bank.

## Using version control tools to share materials

One or more manifest files sit in the same directory as the .git directory.
The user can then share materials in two ways.

1. Use .git as version control.  Users with their own fork of the repository will have a parallel manifest file in their fork to synchronise questions from their external file system to part of a moodle question bank.
2. More than one manifest file sits in the same directory as the .git directory.  A single user can then synchronise questions from their external file system to different parts of a moodle question bank on one site, or different moodle sites.  Version control is very useful in this situation for tracking changes, backing up and resolving possible conflicts between versions.

For example a user creates two branches to match two different Moodle courses: Exporting and committing to separate branches enables the git-merge features to help resolve conflicts.  Branches can diverge, if necessary, to reflect genuine differences between courses on moodle, but shared materials can still be merge between branches.  (But see "duplicates and waste" below.)

Note, that typically the manifest file is _not_ included in the git repository.

## Using subsets of materials

A Gitsync manifest is tied to a question context in moodle, e.g. system, course category, course or module (i.e. quiz). A user might want to manage questions at a question category level, however. This must be done by specifying a question category when exporting and a directory when importing. Only questions in or below this category/directory will be exported/imported.

This will help synchronise questions from part of a larger external library of materials (the git repro) to a moodle question bank.  This has the advantage of not importing irrelevant material from a large external library of materials into moodle. (But see "duplicates and waste" below.) A large moodle question bank can also be split into separate external git repositories in this way.

The question category structure on initial export/import is maintained. So if you import only questions in directory 'top/cat-1/subcat-1' into Moodle they will be placed in your specified context in category 'top/cat 1/subcat 1' not simply in 'top' and vice versa for export. You can move questions around within Moodle after their initial import/export.  

## Duplicates and waste

Expert users' time is much more valuable than DB storage or file storage.  If users import/export materials which are not needed in a particular moodle course then they could simply be ignored.....up to a point.

The logical disconnection of the synchronise questions from the .git repository provides lots of options when combined with version control features such as branches.  Users will need to match their expertise with the complexity of the way they choose to use the tool.  We expect the most common use will be via one or more manifest files in a single git repository.

This module will not help users make judgements on how to organise their question banks!  Users must consider how to arrange their materials in such a way that makes sense.

Users remain entirely responsible for deciding which copy of the materials is the "golden copy", remembering this decision, and looking after their digital materials!

## Using other tools

The purpose of this module is to synchronise questions from part of a moodle question bank to an external file system.  Users are free to create moodle xml files with external tools and then use this tool to import them to moodle.  For example, users have asked for tools to create similar questions from templates.  If such questions end up on an external file system in a suitable way, this tool could be used to import them into part of a moodle question bank.

## Maintainance of manifest files

There are two logically separate parts of this project.

1. Using manifest files to synchronise questions from part of a moodle question bank to an external file system.
2. Maintainance of manifest files.

This project provides tools which scan the files on the external file system, and maintains the manifest file to match the file system and the Moodle question bank. Questions added or removed from the file system (via `git add`, `git rm` or through consequences of a `git pull` from an external repro) will be reflected in the manifest file but not necessarily straight away. The manifest file does not record what is in the repo - the file system itself does that. The manifest file links questions in the file system to questions in a Moodle instance. The manifest is tidied as appropriate on import/export. See [process details](doc/processdetails.md) document for exactly what happens when.

By default, the manifest file is not stored as part of the Git repo but is only on the user's local computer. This allows repos to be shared with other users using different Moodle instances. For repos tied to a single Moodle instance adding the manifest to the repo may be useful.

# Setup

Gitsync is run from and stores question files on your local computer not on the Moodle server. First you need to install it and set it up as a plugin within Moodle and then you need to download and set it up locally.

1) Install the plugin on Moodle and [set up the webservice](doc/webservicesetup.md).
2) Set up Git, PHP and the plugin [on your local computer](doc/localsetup.md).

# Usage

1) Look through the [sample Git scenarios](doc/usinggit.md) and decide the best process for you.
2) As detailed in the sample scenarios, either create a repo locally [using createrepo.php](doc/createrepo.md) to extract questions from Moodle or [use importrepotomoodle](doc/importrepotomoodle.md) to import an existing local repo into your instance of Moodle.
3) [Import](doc/importrepotomoodle.md), [export](doc/exportrepofrommoodle.md) and [delete](doc/deletefrommoodle.md) questions as required.
