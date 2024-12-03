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
     * Relative path of subdirectory matching subcategory.
     *
     * @var string|null
     */
    public ?string $subdirectory = null;
    /**
     * Obtain a list of questions from Moodle and loop through them.
     * If the question is not already in the manifest then create any necessary folders
     * and create the question file.
     */
    public function export_to_repo() {
        $response = $this->listcurlrequest->execute();
        $moodlequestionlist = json_decode($response);
        if (is_null($moodlequestionlist)) {
            echo "Broken JSON returned from Moodle:\n";
            echo $response . "\n";
            $this->call_exit();
            $moodlequestionlist = json_decode('{"questions": []}'); // For unit test purposes.
        } else if (property_exists($moodlequestionlist, 'exception')) {
            echo "{$moodlequestionlist->message}\n";
            if (property_exists($moodlequestionlist, 'debuginfo')) {
                echo "{$moodlequestionlist->debuginfo}\n";
            }
            echo "Failed to get list of questions from Moodle.\n";
            $this->call_exit();
            $moodlequestionlist = json_decode('{"questions": []}'); // For unit test purposes.
        } else {
            $this->export_to_repo_main_process($moodlequestionlist);
        }
    }

    /**
     * Main export processing
     * Separated out so can be mocked in unit tests.
     *
     * @param object $moodlequestionlist
     * @return void
     */
    public function export_to_repo_main_process(object $moodlequestionlist): void {
        // Make top folder in case we don't have any questions.
        if (!is_dir(dirname($this->manifestpath) . '/top')) {
            mkdir(dirname($this->manifestpath) . '/top');
        }
        $this->subdirectory = 'top';
        $questionsinmoodle = $moodlequestionlist->questions;
        $this->postsettings['includecategory'] = 1;
        $tempfile = fopen($this->tempfilepath, 'w+');
        if ($tempfile === false) {
            echo "\nUnable to access temp file: {$this->tempfilepath}\nAborting.\n";
            $this->call_exit();
            return; // Required for PHPUnit.
        }
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
                echo "{$questioninfo->questioncategory} - {$questioninfo->name} not downloaded.\n";
            } else if (property_exists($responsejson, 'exception')) {
                echo "{$responsejson->message}\n";
                if (property_exists($responsejson, 'debuginfo')) {
                    echo "{$responsejson->debuginfo}\n";
                }
                echo "{$questioninfo->questioncategory} - {$questioninfo->name} not downloaded.\n";
            } else {
                // XML will have a category question for each level of category below top + the actual question.
                // There should always be at least one category, if only default.
                $questionxml = simplexml_load_string($responsejson->question);
                if ($questionxml === false) {
                    echo "\nBroken XML.\n";
                    echo "{$questioninfo->questioncategory} - {$questioninfo->name} not downloaded.\n";
                    continue;
                }
                $numcategories = count($questionxml->question) - 1;
                // We want to isolate the real question but keep surrounding structure
                // so unset all the categories.
                for ($i = 0; $i < $numcategories; $i++) {
                    unset($questionxml->question[0]);
                }
                $qname = $questionxml->question->name->text->__toString();
                try {
                    $question = cli_helper::reformat_question($questionxml->asXML());
                } catch (\Exception $e) {
                    echo "\n{$e->getmessage()}\n";
                    echo "{$questioninfo->questioncategory} - {$questioninfo->name} not downloaded.\n";
                    continue;
                }
                $bottomdirectory = '';

                // Create directory for each category and add category question file.
                for ($j = 0; $j < $numcategories; $j++) {
                    // No need to catch issues here - already checked above.
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

                    // Splits category path into an array of categories, splitting on single /.
                    // Double / are then converted to single / after split.
                    $directorylist = preg_split('~(?<!/)/(?!/)~', $categorypath);
                    $directorylist = array_map(fn($dir) => trim(str_replace('//', '/', $dir)), $directorylist);
                    $categorysofar = '';
                    // Create directory structure for category if it doesn't.
                    foreach ($directorylist as $categorydirectory) {
                        $categorydirectory = preg_replace(cli_helper::BAD_CHARACTERS, '-', $categorydirectory);
                        $categorysofar .= "/{$categorydirectory}";
                        $currentdirectory = dirname($this->manifestpath) . $categorysofar;
                        if (!is_dir($currentdirectory)) {
                            mkdir($currentdirectory);
                        }
                        if ($categorypath === $this->subcategory) {
                            $this->subdirectory = substr($categorysofar, 1);
                        }
                    }
                    $catfilepath = $currentdirectory . '/' . cli_helper::CATEGORY_FILE . '.xml';
                    // Question will always be placed at the bottom category level so save
                    // that location for later.
                    if ($currentdirectory > $bottomdirectory) {
                        $bottomdirectory = $currentdirectory;
                    }
                    // We're liable to get lots of repeats of categories between questions
                    // so only create and add file if it doesn't exist already.
                    if (!is_file($catfilepath)) {
                        try {
                            $category = cli_helper::reformat_question($categoryxml->asXML());
                        } catch (\Exception $e) {
                            echo "\n{$e->getmessage()}\n";
                            echo "{$catfilepath} not created.\n";
                            continue;
                        }
                        $success = file_put_contents($catfilepath, $category);
                        if ($success === false) {
                            echo "\nFile creation unsuccessful:\n";
                            echo "{$catfilepath}";
                            continue;
                        }
                    }
                }
                $sanitisedqname = preg_replace(cli_helper::BAD_CHARACTERS, '-', substr($qname, 0, 230));
                $holdername = $sanitisedqname;
                $i = 2;
                while (file_exists("{$bottomdirectory}/{$sanitisedqname}.xml")) {
                    $sanitisedqname = "{$holdername}_{$i}";
                    $i++;
                }
                $success = file_put_contents("{$bottomdirectory}/{$sanitisedqname}.xml", $question);
                if ($success === false) {
                    echo "\nFile creation or update unsuccessful:\n";
                    echo "{$bottomdirectory}/{$sanitisedqname}.xml";
                    continue;
                }
                $fileoutput = [
                    'questionbankentryid' => $questioninfo->questionbankentryid,
                    'version' => $responsejson->version,
                    'filepath' => str_replace( '\\', '/', $bottomdirectory) . "/{$sanitisedqname}.xml",
                    'format' => 'xml',
                ];
                fwrite($tempfile, json_encode($fileoutput) . "\n");
            }
        }
    }

    /**
     * Mockable function that just exits code.
     *
     * Required to stop PHPUnit displaying output after exit.
     *
     * @return void
     */
    public function call_exit(): void {
        exit;
    }
}
