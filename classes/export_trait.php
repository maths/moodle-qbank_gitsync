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
 * Trait to re-use code for cycling through questions from Moodle.
 *
 * Used when creating and exporting the repository for the first export
 * of each question.
 *
 * Used in import_repo and create_repo
 *
 *
 * @package    qbank_gitsync
 * @copyright  2023 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace qbank_gitsync;
/**
 * Code used for both repo creation and exporting new questions.
 *
 * Uses quite a few internal properties of export_repo and create_repo
 * so probably clearer to use a trait than add to cli_helper.
 */
trait export_trait {
    /**
     * Obtain a list of questions from Moodle and loop through them.
     * If the question is not already in the manifest then create any necessary folders
     * and create the question file.
     */
    public function export_to_repo() {
        $response = $this->listcurlrequest->execute();
        $questionsinmoodle = json_decode($response);
        if (is_null($questionsinmoodle)) {
            echo "Broken JSON returned from Moodle:\n";
            echo $response . "\n";
        } else if (!is_array($questionsinmoodle)) {
            if (property_exists($questionsinmoodle, 'exception')) {
                echo "{$questionsinmoodle->message}\n";
            }
            echo "Failed to get list of questions from Moodle.\n";
            exit;
        }
        $this->postsettings['includecategory'] = 1;
        $tempfile = fopen($this->tempfilepath, 'a+');
        $existingentries = array_column($this->manifestcontents->questions, null, 'questionbankentryid');
        foreach ($questionsinmoodle as $questioninfo) {
            // This is the difference between create and export.
            // Only questions not already in the manifest are exported.
            // Export updates questions already in the manifest in export_repo->export_questions_in_manifest().
            if (isset($existingentries["{$questioninfo->questionbankentryid}"])) {
                continue;
            }
            $this->postsettings['questionbankentryid'] = $questioninfo->questionbankentryid;
            $this->curlrequest->set_option(CURLOPT_POSTFIELDS, $this->postsettings);
            $response = $this->curlrequest->execute();
            $responsejson = json_decode($response);
            if (!$responsejson) {
                echo "Broken JSON returned from Moodle:\n";
                echo $response . "\n";
            } else if (property_exists($responsejson, 'exception')) {
                echo "{$responsejson->message}\n";
                if (property_exists($responsejson, 'debuginfo')) {
                    echo "{$responsejson->debuginfo}\n";
                }
                echo "{$questioninfo->categoryname} - {$questioninfo->name} not downloaded.\n";
            } else {
                // XML will have a category question for each level of category below top + the actual question.
                // There should always be at least one category, if only default.
                $questionxml = simplexml_load_string($responsejson->question);
                $numcategories = count($questionxml->question) - 1;
                // We want to isolate the real question but keep surrounding structure
                // so unset all the categories.
                for ($i = 0; $i < $numcategories; $i++) {
                    unset($questionxml->question[0]);
                }
                $qname = $questionxml->question->name->text->__toString();
                $question = cli_helper::reformat_question($questionxml->asXML());
                $bottomdirectory = '';

                // Create directory for each category and add category question file.
                for ($j = 0; $j < $numcategories; $j++) {
                    $categoryxml = simplexml_load_string($responsejson->question);
                    // Isolate each category in turn.
                    for ($k = 0; $k < $numcategories + 1; $k++) {
                        if ($k < $j) {
                            unset($categoryxml->question[0]);
                        } else if ($k > $j) {
                            unset($categoryxml->question[count($categoryxml->question) - 1]);
                        }
                    }
                    $categorypath = $categoryxml->question->category->text->__toString();

                    // TODO Is this needed?
                    $directorylist = preg_split('~(?<!/)/(?!/)~', $categorypath);
                    $directorylist = array_map(fn($dir) => trim(str_replace('//', '/', $dir)), $directorylist);
                    $categorysofar = '';
                    // Create directory structure for category if it doesn't.
                    foreach ($directorylist as $categorydirectory) {
                        $categorysofar .= "/{$categorydirectory}";
                        $currentdirectory = dirname($this->manifestpath) . $categorysofar;
                        if (!is_dir($currentdirectory)) {
                            mkdir($currentdirectory);
                        }
                    }
                    $catfilepath = $currentdirectory . '/' . cli_helper::CATEGORY_FILE . '.xml';
                    // We're liable to get lots of repeats of categories between questions
                    // so only create and add file if it doesn't exist already.
                    if (!is_file($catfilepath)) {
                        $category = cli_helper::reformat_question($categoryxml->asXML());
                        file_put_contents($catfilepath, $category);
                    }
                    // Question will always be placed at the bottom category level so save
                    // that location for later.
                    if ($currentdirectory > $bottomdirectory) {
                        $bottomdirectory = $currentdirectory;
                    }
                }
                file_put_contents($bottomdirectory . "/{$qname}.xml", $question);
                $fileoutput = [
                    'questionbankentryid' => $questioninfo->questionbankentryid,
                    'version' => $responsejson->version,
                    'exportedversion' => $responsejson->version,
                    'contextlevel' => $this->listpostsettings['contextlevel'],
                    'filepath' => $bottomdirectory . "/{$qname}.xml",
                    'coursename' => $this->listpostsettings['coursename'],
                    'modulename' => $this->listpostsettings['modulename'],
                    'coursecategory' => $this->listpostsettings['coursecategory'],
                    'qcategoryname' => $this->listpostsettings['qcategoryname'],
                    'format' => 'xml',
                ];
                fwrite($tempfile, json_encode($fileoutput) . "\n");
            }
        }
    }
}
