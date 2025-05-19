# Synchronize questions with an external git repository

The goal of this project is to synchronize questions in a Moodle context with files in an external git repository.

The reasons for needing this functionality are as follows.

1. Share _copies_ of questions between courses in a single institution. Yes, questions could be in a DB and shared, but where courses are run by separate staff they might prefer private copies. Later, e.g. at the end of an academic year, we need to reconcile (e.g. merge) changes.  We do not always want changes during the year to always impact all users immediately.  E.g. one colleague might well decide to alter the number of marks but not everyone will agree.
2. Share questions over multiple sites. Sharing needs to be in both directions, in particular we need to make it easier for colleagues to contribute back improvements, however minor.
3. Collaborate on large question bank developments. Colleagues need transparency to be able to review changes and audit development over time.
4. Read and write from external question banks for use with other projects, e.g. ILIAS and a (future) STACK API.
5. Coordinate translation of materials into different languages. The random question generation, and feedback trees, contain significant value. Translation and question maintainance are likely to be increasinly done by different colleagues.

Verson control is potentially complex (when all features are used), but using version control at the outset to just satisfy requirement 1 (copies of questions in a single institution) will make future developments coherent and easier.

We do not need to solve problems, e.g. seeing "diff" between files or merging, which verson control has already addressed.

To use Gitsync, start with the [README file](../README.md).

### Use-case examples

Online courses, particularly those in mathematics, can have very large numbers of questions. For example _Fundamentals of algebra and calculus_ [FAC](https://stack-assessment.org/CaseStudies/2019/FAC/) has over 1000 [STACK](https://stack-assessment.org/) and other types of questions. These questions are categorised by course week within the original course at Edinburgh but to facilitate wider sharing they have been re-categorised to match chapters and sections with an open-source textbook and imported to another Moodle instance. The questions have also had their names changed. The relationship between questions on the two instances is recorded via a spreadsheet. Questions have been improved and fixed on both instances. How can those changes be identified and combined? 

The [HELM project](https://stack-assessment.org/CaseStudies/2021/HELM/) is a collection of 50 workbooks that covers the curriculum of first- and second-year mathematics courses for engineering undergraduates. This is a substantial corpus of "battle tested" materials which continue to be widely used.  We want to make it easier for colleagues to contribute changes and improvements to these projects.  Some kind of version control is needed.

IDEMS has a number of open question banks with around 200 questions in each. The questions are all in one category but filtered by tag. Copies of questions can be in multiple question banks. Priorities for them include being able to see the difference between Moodle versions of a question and to combine changes to multiple copies of a question that are in different courses.

University of Canterbury created an external database of all their questions as part of a QA effort. It did all sorts of useful things like highlight question features (e.g. "Uses JSXGraph", "String input type") and gave direct links to question edit or preview screens. These became obsolete on upgrading to Moodle 4, because question versioning broke the links. Every version of a question is a new question in the DB with its own ID which forms part of the URL. They'd like to see the changes between different versions of a question. (Could we export each version of a question in turn and commit to the branch, adding that history to the Git repo? Versioning is certainly another wrinkle to be taken into account when matching questions within the repo with questions within Moodle.)

