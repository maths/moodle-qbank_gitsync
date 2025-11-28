# Storing questions as YAML

MoodleXML is a great way to store questions but is not very readable by humans and is difficult to edit.

Notes: 

1. _If you wish to store questions in YAML format you will need to install Symfony YAML on the local PHP setup (not on Moodle). This can be done via composer._
2. _If you want to switch between using YAML and XML, create a new repo. Switching an existing repo will cause problems with filename extensions._

By setting `useyaml` to true either in your config file (`useyaml = true;`) or for individual script runs (`--useyaml "true"`), you can store STACK questions in your repo as YAML difference representations. When exporting to the repo, Gitsync downloads STACK questions as normal and then converts them to YAML. Non-STACK questions will be left as XML.

If you also set `usefragments` to true either in your config file (`usefragments = true;`) or for individual script runs (`--usefragments "true"`), Gitsync then compares them with a defaults file. Fields that match the default (and are not part of the minimum represenations) are removed and then what's left is stored in the repo.

Gitsync will select a defaults file on the following basis:
  - If you supply a filename as a script argument (`--defaultfile` or `-o`), Gitsync will look for it in the repo directory.
  - Otherwise, if there is a default file recorded in the manifest file from manifest creation, Gitsync will look for it in the repo directory.
  - Failing that, Gitsync will look for a `questiondefault.yml` in the repo directory.
  - As a last resort, Gitsync will use [it's own defaults file](../questiondefaults.yml).

Certain important fields are always included in the YAML representation even if they match the default. The example below is for a default question with a single input, PRT and node but fields will be shown for every input, prt and node in a more complex question.

```
name: Default
questionsimplify: '1'
input:
  - name: ans1
    type: algebraic
    tans: ta1
    forbidfloat: '1'
    requirelowestterms: '0'
    checkanswertype: '0'
    mustverify: '1'
    showvalidation: '1'
prt:
  - name: prt1
    autosimplify: '1'
    feedbackstyle: '1'
    node:
      - name: '0'
        answertest: AlgEquiv
        sans: ans1
        tans: ta1
        quiet: '0'
```

Some YAML fields are named slightly differently than from XML - rather than fields having `text` and `format` children, there are
`field` and `fieldformat` fields e.g.  
```
<specificfeedback>
  <text><p>[[feedback:prt1]]</p></text>
  <format>html</format>
</specificfeedback>`  
```
becomes
```
specificfeedback: <p>[[feedback:prt1]]</p>
specificfeedbackformat: html
```

See [test question](../tests/fixtures/fullquestion.yml) for sample YAML layout.

Additionally you can set your default file so that the answer tests for your questions are stored
in a summary format:
```
ATanswertest(sans,tans,testoptions)
```
Default file example:
```
  answertest: ATAlgEquiv(ans1,ta1)
  # sans, tans, testoptions will not be used but need to be here for diff compatibility
  # with questions which have them rather than the summary answertest.
  sans:
  tans:
  testoptions:
```

When importing to Moodle, Gitsync adds all the missing fields to the YAML representation from the defaults file, converts it to XML, creates a temporary XML file in the repo directory and then uploads this to Moodle. The defaults file is selected in the same way as for export so you can use different defaults if required. If you haven't set Gitsync to use fragments but your YAML is incomplete, STACK has built in defaults and will attempt to fill in the gaps - you will need to export your questions from Moodle to see the results in your repo.

Obviously, using different defaults for import and export should be handled with care to avoid information loss! It makes most sense for altering site-specific settings that will be the same for every question e.g. decimal separator. Normally Gitsync skips importing questions that have not changed in the repo since the last import so if you want to update them using different defaults you will need to use `-z` to force import of questions that have not changed themselves.

