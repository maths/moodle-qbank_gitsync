# Synchronize questions with an external git repository

The goal of this project is to synchronize questions in a Moodle question bank with files in an external git repository.

The reasons for needing this functionality are as follows.

1. Share _copies_ of questions between courses in a single institution.  Yes, questions could be in a DB and shared, but where courses are run by separate staff they might prefer private copies.  Later, e.g. at the end of an academic year, we need to reconcile (e.g. merge) changes.  We do not always want changes during the year to always impact all users immediately.  E.g. one colleague might well decide to alter the number of marks but not everyone will agree.
2. Share questions over multiple sites.  Sharing needs to be in both directions, in particular we need to make it easier for colleagues to contribute back improvememnts, however minor.
3. Collaborate on large qestion bank developments.  Colleagues need transparency to be able to review changes and audit development over time.
4. Read and write from external question banks for use with other projects, e.g. ILIAS and a (future) STACK API.
5. Coordinate translation of materials into different languages.  The random question generation, and feedback trees, contain significant value.  Translation and question maintainance are likely to be increasinly done by different colleagues.

Verson control is potentially complex (when all features are used), but using version control at the outset to just satisfy requirement 1 (copies of questions in a single institution) will make future developments coherent and easier.

We do not need to solve problems, e.g. seeing "diff" between files or merging, which verson control has already addressed.

### Use-case examples

Online courses, particularly those in mathematics, can have very large numbers of questions.  For example _Fundamentals of algebra and calculus_ [FAC](https://stack-assessment.org/CaseStudies/2019/FAC/) has over 1000 [STACK](https://stack-assessment.org/) and other types of questions.

The [HELM project](https://stack-assessment.org/CaseStudies/2021/HELM/) is a collection of 50 workbooks that covers the curriculum of first- and second-year mathematics courses for engineering undergraduates. This is a substantial corpus of "battle tested" materials which continue to be widely used.  We want to make it easier for colleagues to contribute changes and improvements to these projects.  Some kind of version control is needed.