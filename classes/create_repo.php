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
 * Wrapper class for processing performed by command line interface for creating a repo from Moodle.
 *
 * Utilised in cli\createrepo.php
 *
 * Allows mocking and unit testing via PHPUnit.
 * Used outside Moodle.
 *
 * @package    qbank_gitsync
 * @copyright  2023 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace qbank_gitsync;
/**
 * Export a Git repo.
 */
class create_repo {
    /**
     * Settings for POST request
     *
     * These are the parameters for the webservice call.
     *
     * @var array
     */
    public array $postsettings;
    /**
     * Settings for list POST request
     *
     * These are the parameters for the webservice call.
     *
     * @var array
     */
    public array $listpostsettings;
    /**
     * cURL request handle for file upload
     *
     * @var curl_request
     */
    public curl_request $curlrequest;
    /**
     * cURL request handle for question list retrieve
     *
     * @var curl_request
     */
    public curl_request $listcurlrequest;
    /**
     * Relative path of subdirectory to import.
     *
     * @var string
     */
    public string $subdirectory;
    /**
     * Path to actual manifest file
     *
     * @var string
     */
    public string $manifestpath;
    /**
     * Path to temporary manifest file
     *
     * @var string
     */
    public string $tempfilepath;
    /**
     * Path of root of repo
     * i.e. folder containing manifest
     *
     * @var string
     */
    public string $directory;

    /**
     * Obtain a list of questions and categories from Moodle, iterate through them and
     * export them one at a time. Create repo directories and files.
     *
     * @param cli_helper $clihelper
     * @param array $moodleinstances pairs of names and URLs
     * @return void
     */
    public function process(cli_helper $clihelper, array $moodleinstances):void {
        // Convert command line options into variables.
        // (Moodle code rules don't allow 'extract()').
        $arguments = $clihelper->get_arguments();
        $moodleinstance = $arguments['moodleinstance'];
        $this->directory = $arguments['rootdirectory'] . $arguments['directory'];
        $this->subdirectory = '';
        if ($arguments['subdirectory']) {
            $this->subdirectory = $arguments['subdirectory'];
        }
        if (is_array($arguments['token'])) {
            $token = $arguments['token'][$moodleinstance];
        } else {
            $token = $arguments['token'];
        }
        $contextlevel = $arguments['contextlevel'];
        $coursename = $arguments['coursename'];
        $modulename = $arguments['modulename'];
        $coursecategory = $arguments['coursecategory'];
        $this->manifestpath = cli_helper::get_manifest_path($moodleinstance, $contextlevel, $coursecategory,
                                                $coursename, $modulename, $this->directory);
        $this->tempfilepath = $this->directory . '/' .
                              $moodleinstance . '_' . $contextlevel . cli_helper::TEMP_MANIFEST_FILE;

        $moodleurl = $moodleinstances[$moodleinstance];
        $wsurl = $moodleurl . '/webservice/rest/server.php';

        $this->curlrequest = $this->get_curl_request($wsurl);
        $this->postsettings = [
            'wstoken' => $token,
            'wsfunction' => 'qbank_gitsync_export_question',
            'moodlewsrestformat' => 'json',
            'questionbankentryid' => null,
            'includecategory' => 1,
        ];
        $this->curlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->curlrequest->set_option(CURLOPT_POST, 1);
        $this->listcurlrequest = $this->get_curl_request($wsurl);
        $this->listpostsettings = [
            'wstoken' => $token,
            'wsfunction' => 'qbank_gitsync_get_question_list',
            'moodlewsrestformat' => 'json',
            'contextlevel' => cli_helper::get_context_level($contextlevel),
            'coursename' => $coursename,
            'modulename' => $modulename,
            'coursecategory' => $coursecategory,
            'qcategoryname' => substr($this->subdirectory, 1)
        ];
        $this->listcurlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->listcurlrequest->set_option(CURLOPT_POST, 1);
        $this->listcurlrequest->set_option(CURLOPT_POSTFIELDS, $this->listpostsettings);

        $this->export_to_repo();
        $manifestcontents = new \stdClass();
        $manifestcontents->context = null;
        $manifestcontents->questions = [];
        cli_helper::create_manifest_file($manifestcontents, $this->tempfilepath, $this->manifestpath, $moodleurl);
        unlink($this->tempfilepath);

        return;
    }

    /**
     * Wrapper for cURL request to allow mocking.
     *
     * @param string $wsurl webservice URL
     * @return curl_request
     */
    public function get_curl_request($wsurl):curl_request {
        return new \qbank_gitsync\curl_request($wsurl);
    }

    /**
     * Loop through questions in manifest file, export each from Moodle and update local copy
     *
     * @return void
     */
    public function export_to_repo() {
        $questionsinmoodle = json_decode($this->listcurlrequest->execute());
        $tempfile = fopen($this->tempfilepath, 'a+');
        foreach ($questionsinmoodle as $questioninfo) {
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
                        $currentdirectory = $this->directory . $categorysofar;
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
                    'contextlevel' => $this->listpostsettings['contextlevel'],
                    'filepath' => $currentdirectory . "/{$qname}.xml",
                    'coursename' => $this->listpostsettings['coursename'],
                    'modulename' => $this->listpostsettings['modulename'],
                    'coursecategory' => $this->listpostsettings['coursecategory'],
                    'format' => 'xml',
                ];
                fwrite($tempfile, json_encode($fileoutput) . "\n");
            }
        }

        return;
    }
}
