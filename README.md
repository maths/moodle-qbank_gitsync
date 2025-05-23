# Synchronize questions in a Moodle context question bank with files in an external git repository

The purpose of this module is to synchronise questions from a moodle context question bank to an external file system.

The motivating use-case is to share questions either (i) between multiple courses on a single moodle site or (ii) between multiple sites.
If the external file system is part of a version control repository (e.g. git), then version control tools can be used on the external file system to track differences between versions, merge changes, use branches to maintain different versions, and so on.

This tool helps users export questions from a moodle context (e.g. course or course category) to their local file system, avoiding the need to access the file system on the server directly.

This module will not help users make judgements on how to organise their question banks! Users must consider how to arrange their materials in such a way that makes sense.

The basic unit is a "folder of questions", which may contain sub-folders.

* On the external (local) file system a "folder of questions" is literally a directory of Moodle .xml files, each file is a single question file. There will be a `top` directory for the `top` category of the context and then a branching structure of directories to match
the category tree within Moodle. Directories below `top` will ahve a `gitsync_category.xml` file with details of the category.
* On Moodle these "folders of questions" are categories in the Moodle question bank.

A "manifest file" tracks the link between the external file system and the moodle question bank.

The position of the manifest file in the external file system is the same location as the top-level directory. On the external file system, the software will look down the directory tree for question files.

## Terminology

Before we go any further, it's worth clarifying some concepts:

**Moodle context** - Moodle access is based on context e.g. system, course category, course or module (i.e. quiz). Prior to Moodle 5, each
context has an associated question bank. Gitsync works at the level of context - you specify a context and you get the matching question
bank. Moodle 5+ ditches most of these question banks. All question banks are at the module level. Quizzes still have their own
question bank but courses have question bank modules. To export the questions for a course you will need to
specify each course question bank in turn and create a separate repo.

**Question category** - A question bank has a branching question category structure starting with a `top` directory. Gitsync uses this
as the basis for its file system, using sanitised versions of the category names as directory names. Category data (e.g. path and description)
is exported on Gitsync export and placed in `gitsync_category.xml` files in catgeory levels below `top`. This category data is not tracked
and updated on a fresh export or import (as category data is not versioned in Moodle). If you rename or move catgeories they will be treated
as new when importing/exporting.

**Question version** - What is meant by question version can depend on the question's location. Every time a question is updated in
Moodle a new numbered (Moodle) version of the question is created. Gitsync keeps track of the Moodle version of question when it is
exported from Moodle and the Moodle version created on the most recent import into Moodle and logs these in the manifest file. Questions
can also be updated in the repo. When using Git, uncommitted changes prevent Gitsync scripts from running. Committed changes create a new
commit hash and Gitsync keeps track of the current commit hash for each question and the commit hash at the point of the most recent import
of that question to Moodle. (Basically, if these are different then Gitsync knows the question has changed in the repo and imports
the question when `importrepotomoodle` is run. This will create a new version in Moodle. If the hashes are the same, the question is not imported,
avoiding the creation of a Moodle version which is identical to the previous one.)

The interaction between contexts, categories and different versions is where things get complicated and it's very easy for organising questions
to get out of hand.

## Using version control tools to track changes.

Manifest files sit in the same directory as the .git directory. Importing and exporting questions from the moodle question bank to/from an external file system enables a user to track changes (and externally backup) questions in a moodle question bank. Generally, manifest files are not commited to the version control repository as they are dependent on the instance and context of Moodle being worked with.

## Using version control tools to share materials

One or more manifest files sit in the same directory as the .git directory.
The user can then share materials in two ways.

1. Use .git as version control.  Users with their own fork of the repository will have a parallel manifest file in their fork to synchronise questions from their external file system to a Moodle context.
2. More than one manifest file sits in the same directory as the .git directory. A single user can then synchronise questions from their external file system to different Moodle contexts on a single site, or different moodle sites. Version control is very useful in this situation for tracking changes, backing up and resolving possible conflicts between versions.

For example a user creates two branches to match two different Moodle courses: Exporting and committing to separate branches enables the git-merge features to help resolve conflicts.  Branches can diverge, if necessary, to reflect genuine differences between courses on moodle, but shared materials can still be merge between branches.  (But see "duplicates and waste" below.)

Note, that typically the manifest file is _not_ included in the git repository.

## Using subsets of materials

A Gitsync manifest is tied to a question context in moodle, e.g. system, course category, course or module (i.e. quiz). A user might want to manage questions at a question category level, however.

This can be done in two ways:

1) Specifying a question category when exporting and a directory when importing. Only questions in or below this category/directory will be exported/imported. This is most useful when you have a larger question structure and you only want to update a subset. If used on initial export/import category structure is maintained. So if you import only questions in directory 'top/cat-1/subcat-1' into Moodle they will be placed in your specified context in category 'top/cat 1/subcat 1' not simply in 'top' and vice versa for export.  

   You can move questions around within Moodle after their initial import/export.  In particular, once a manifest file links questions in moodle to files in the local directory it is the moodle DB `questionid` number which is relevant, not the current position of the question in the moodle question bank.

![Diagram of question and category mapping for targeted import/export](/images/sub.png)

2) Specifying a target subcategory and subdirectory on repo creation or import into a new context. This creates a new manifest file which maps the question category and directory directly. This is most useful for importing the required part of a large repo into a Moodle context (e.g. importing the algebra subdirectory of a repo of standard questions into an algebra course).

![Diagram of question and category mapping for targeted import/export](/images/target.png)

These options help synchronise questions from part of a larger external library of materials (the git repo) to a moodle question bank. This has the advantage of not importing irrelevant material from a large external library of materials into moodle. (But see "duplicates and waste" below.) A large moodle question bank can also be split into separate external git repositories in this way.

Note that combining the two methods above is not always possible. For instance, you can't do a targeted export (Method 2) and then do a subcategory export (Method 1) to only update a subselection of the questions - you have to work with the entirety of the targeted directory/category. That said, you could to a targeted export (Method 2) from one context and then use either method to import into a different context as that would created a fresh manifest file and new instances of the questions in Moodle.

## Duplicates and waste

Expert users' time is much more valuable than DB storage or file storage.  If users import/export materials which are not needed in a particular moodle course then they could simply be ignored.....up to a point.

The logical disconnection of the synchronise questions from the .git repository provides lots of options when combined with version control features such as branches.  Users will need to match their expertise with the complexity of the way they choose to use the tool.  We expect the most common use will be via one or more manifest files in a single git repository.

This module will not help users make judgements on how to organise their question banks!  Users must consider how to arrange their materials in such a way that makes sense.

Users remain entirely responsible for deciding which copy of the materials is the "golden copy", remembering this decision, and looking after their digital materials!

## Using other tools

The purpose of this module is to synchronise questions from a Moodle context to an external file system.  Users are free to create moodle xml files with external tools and then use this tool to import them to moodle.  For example, users have asked for tools to create similar questions from templates.  If such questions end up on an external file system in a suitable way, this tool could be used to import them into part of a moodle question bank.

## Maintainance of manifest files

There are two logically separate parts of this project.

1. Using manifest files to synchronise questions from a Moodle context to an external file system.
2. Maintainance of manifest files.

This project provides tools which scan the files on the external file system, and maintains the manifest file to match the file system and the Moodle context. Questions added or removed from the file system (via `git add`, `git rm` or through consequences of a `git pull` from an external repo) will be reflected in the manifest file but not necessarily straight away. The manifest file does not record what is in the repo - the file system itself does that. The manifest file links questions in the file system to questions in a Moodle instance. The manifest is tidied as appropriate on import/export. See [process details](doc/processdetails.md) document for exactly what happens when.

By default, the manifest file is not stored as part of the Git repo but is only on the user's local computer. This allows repos to be shared with other users using different Moodle instances. For repos tied to a single Moodle instance adding the manifest to the repo may be useful (but users will need to be careful to use the same Moodle instance short names in their config file).

A manifest file will have the name `instancename_contextlevel_contextname_question_manifest.json` and look like this:
```
"context": {
        "contextlevel": 50, // Moodle context code e.g. 50 === 'course'
        "coursename": "Course 1", // Context details required are dependent on contextlevel
        "modulename": null, // Restricted to quiz (and for Moodle 5, question bank) modules
        "coursecategory": null,
        "instanceid": "2", // Course, coursecategory or quiz id
        "istargeted": false, // Is this a targeted manifest directly linking the default subcategory and subdirectory?
        "defaultsubcategoryid": 3, // Set by the subcategory/directory used at manifest creation
        "defaultsubdirectory": "top", // Set by the subcategory/directory used at manifest creation
        "defaultignorecat": null, // Ignore categories containing the given regular expression
        "moodleurl": "http:\/\/stack.stack.virtualbox.org\/edmundlocal"
    },
    "questions": [
        {
            "questionbankentryid": "727",
            "filepath": "\/top\/Default-for-T1\/Slope-of-line.xml", // Relative to top of the repository
            "format": "xml", // Currently always XML
            "importedversion": "1", // Question version in Moodle on manifest creation or after last import
            "exportedversion": "1", // Question version last exported from Moodle
            "currentcommit": "371a103f2465319494c69500a84626a9d19e75f8", // Current Git hash of question file
            "moodlecommit": "371a103f2465319494c69500a84626a9d19e75f8" // Git hash of file when last imported into Moodle
        },
        {
            "questionbankentryid": "728",
            "filepath": "\/top\/Default-for-T1\/Equations-of-straight-lines.xml",
            "format": "xml",
            "importedversion": "11",
            "exportedversion": "11",
            "currentcommit": "371a103f2465319494c69500a84626a9d19e75f8",
            "moodlecommit": "371a103f2465319494c69500a84626a9d19e75f8"
        }
    ]
```

Targeted manifest files will be named differently: `instancename_contextlevel_contextname_parentdirectory_targetdirectory_categorypath_categoryid_question_manifest.json` (with
truncation of various parts as necessary).

# Setup

Gitsync is both a Moodle plugin which imports and exports questions, and a selection of command line scripts which call the plugin. The scripts are run from your local computer - not on the Moodle server. They store question files on your local computer.

The plugin needs to be installed and set up within Moodle. Then you need to download and set up Gitsync locally.

1. Install the plugin on Moodle and [set up the webservice](doc/webservicesetup.md).
2. Set up Git, PHP and the plugin scripts [on your local computer](doc/localsetup.md).

# Usage

1. Look through the [sample Git scenarios](doc/usinggit.md) and decide the best process for you. Even if you don't want to use
Git this is still the best place to go after this README to get an idea of how to work with Gitsync.
2. As detailed in the sample scenarios, either create a repo locally [using createrepo.php](doc/createrepo.md) to extract questions from Moodle or [use importrepotomoodle](doc/importrepotomoodle.md) to import an existing local repo into your instance of Moodle.
3. [Import](doc/importrepotomoodle.md), [export](doc/exportrepofrommoodle.md) and [delete](doc/deletefrommoodle.md) questions as required.
4. Use [importquiztomoodle.php](doc/importquiztomoodle.md) to import an individual quiz.
3. You can also [create](doc/createwholecourserepo.md), [import](doc/importwholecoursetomoodle.md)
and [export](doc/exportwholecoursefrommoodle.md) a course's questions along with its quizzes using the 'wholecourse' scripts.

# Other info

- [More information on goals and use cases for Gitsync.](doc/index.md)
- [Detailed list of steps that take place during the running of the four main scripts](doc/processdetails.md)
