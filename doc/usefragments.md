# Storing questions as difference fragments

Even simple STACK questions contain a great deal of XML, much of which is likely to be the same for all your questions. 

By setting `usefragments` to true either in your config file (`usefragments = true;`) or for individual script runs (`--usefragments "true"`), you can store STACK questions in your repo as difference representations. When exporting to the repo, Gitsync downloads STACK questions as normal and then compares them with a defaults file. Fields that match the default (and are not part of the minimum represenations) are removed and then what's left is stored in the repo. Non-STACK questions will be left as they are. (You can combine this with `useyaml` to get [YAML fragments](useyaml.md) which are much more readable.)

Gitsync will select a defaults file on the following basis:
  - If you supply a filename as a script argument (`--defaultfile` or `-o`), Gitsync will look for it in the repo directory.
  - Otherwise, if there is a default file recorded in the manifest file from manifest creation, Gitsync will look for it in the repo directory.
  - Failing that, Gitsync will look for a `questiondefault.xml` in the repo directory.
  - As a last resort, Gitsync will use [it's own defaults file](../questiondefaults.xml).

Certain important fields are always included in the XML representation even if they match the default. The example below is for a default question with a single input, PRT and node but fields will be shown for every input, prt and node in a more complex question.

```
<?xml version="1.0" encoding="UTF-8"?>
<quiz>
  <question type="stack">
    <name>
      <text>Default</text>
    </name>
    <questionsimplify>1</questionsimplify>
    <input>
      <name>ans1</name>
      <type>algebraic</type>
      <tans>ta1</tans>
      <forbidfloat>1</forbidfloat>
      <requirelowestterms>0</requirelowestterms>
      <checkanswertype>0</checkanswertype>
      <mustverify>1</mustverify>
      <showvalidation>1</showvalidation>
    </input>
    <prt>
      <name>prt1</name>
      <autosimplify>1</autosimplify>
      <feedbackstyle>1</feedbackstyle>
      <node>
        <name>0</name>
        <answertest>AlgEquiv</answertest>
        <sans>ans1</sans>
        <tans>ta1</tans>
        <quiet>0</quiet>
      </node>
    </prt>
  </question>
</quiz>
```

Additionally you can set your default file so that the answer tests for your questions are stored
in a summary format:
```
ATanswertest(sans,tans,testoptions)
```
Default file example:
```
  <answertest>ATAlgEquiv(ans1,ta1)</answertest>
  <sans></sans>
  <tans></tans>
  <testoptions></testoptions>
  # sans, tans, testoptions will not be used but need to be here for diff compatibility
  # with questions which have them rather than the summary answertest.
```

When importing to Moodle, Gitsync adds all the missing fields to the XML representation, creates a temporary XML file in the repo directory and then uploads this to Moodle. The defaults file is selected in the same way as for export so you can use different defaults if required. If you haven't set Gitsync to use fragments but your XML is incomplete, STACK has built in defaults and will attempt to fill in the gaps - you will need to export your questions from Moodle to see the results in your repo.

Obviously, using different defaults for import and export should be handled with care to avoid information loss! It makes most sense for altering site-specific settings that will be the same for every question e.g. decimal separator. Normally Gitsync skips importing questions that have not changed in the repo since the last import so if you want to update them using different defaults you will need to use `-z` to force import of questions that have not changed themselves.

