<?php
// This file is part of Stack - http://stack.maths.ed.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Export from Moodle into a git repo containing questions.
 *
 * @package    qbank_gitsync
 * @copyright  2023 University of Edinburgh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Options for HTML Tidy.
// If in doubt, set to false to avoid unexpected 'repairs'.
$sharedoptions = [
    'break-before-br' => true,
    'show-body-only' => true,
    'wrap' => '0',
    'indent' => true,
    'coerce-endtags' => false,
    'drop-empty-elements' => false,
    'drop-empty-paras' => false,
    'fix-backslash' => false,
    'fix-bad-comments' => false,
    'merge-emphasis' => false,
    'quote-ampersand' => false,
    'quote-nbsp' => false,
];
$myxmldata = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<quiz>\n<!-- question: 20  -->\n  <question type=\"truefalse\">\n    <name>\n      <text>First</text>\n    </name>\n    <questiontext format=\"html\">\n      <text><![CDATA[<p dir=\"ltr\" style=\"text-align: left;\">?<br></p><p>Hello</p>]]></text>\n    </questiontext>\n    <generalfeedback format=\"html\">\n      <text></text>\n    </generalfeedback>\n    <defaultgrade>1</defaultgrade>\n    <penalty>1</penalty>\n    <hidden>0</hidden>\n    <idnumber></idnumber>\n    <answer fraction=\"0\" format=\"moodle_auto_format\">\n      <text>true</text>\n      <feedback format=\"html\">\n        <text></text>\n      </feedback>\n    </answer>\n    <answer fraction=\"100\" format=\"moodle_auto_format\">\n      <text>false</text>\n      <feedback format=\"html\">\n        <text></text>\n      </feedback>\n    </answer>\n  </question>\n\n</quiz>";
$dom = new DOMDocument('1.0');
$dom->preserveWhiteSpace = true;
$dom->formatOutput = true;
$dom->loadXML($myxmldata);

$xpath = new DOMXpath($dom);
$tidyoptions = array_merge($sharedoptions, [
    'output-xhtml' => true
]);

$tidy = new tidy();

// Find CDATA sections and format nicely.
foreach ($xpath->evaluate("//*[@format='html']/text/text()") as $cdata) {
    if ($cdata->data) {
        $tidy->parseString($cdata->data, $tidyoptions);
        $tidy->cleanRepair();
        $output = tidy_get_output($tidy);
        $cdata->data = "\n{$output}\n";
    }
}

$cdataprettyxml = $dom->saveXML();

// Remove question id comment.
$xml = simplexml_load_string($cdataprettyxml);
if (get_class($xml->comment) === 'SimpleXMLElement') {
    unset($xml->comment);
}

$noidxml = $xml->asXML();

// Tidy the whole thing, cluding indenting CDATA as a whole.
$tidyoptions = array_merge($sharedoptions, [
    'input-xml' => true,
    'output-xml' => true,
    'indent-cdata' => true
]);
$tidy->parseString($noidxml, $tidyoptions);
$tidy->cleanRepair();
$result = tidy_get_output($tidy);
echo $result;



