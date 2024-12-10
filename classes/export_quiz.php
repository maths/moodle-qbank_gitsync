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
 * Wrapper class for processing performed by command line interface for exporting quiz data from Moodle.
 *
 * Utilised in cli\exportquizstructurefrommoodle.php
 *
 * Allows mocking and unit testing via PHPUnit.
 * Used outside Moodle.
 *
 * @package    qbank_gitsync
 * @copyright  2024 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace qbank_gitsync;

/**
 * Export structure data of a Moodle quiz.
 */
class export_quiz {
    /**
     * Settings for POST request
     *
     * These are the parameters for the webservice call.
     *
     * @var array
     */
    public array $postsettings;
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
     * Settings for question list request
     *
     * These are the parameters for the webservice list call.
     *
     * @var array
     */
    public array $listpostsettings;
    /**
     * Full path to manifest file
     *
     * @var string|null
     */
    public ?string $quizmanifestpath = null;
    /**
     * Parsed content of JSON manifest file
     *
     * @var \stdClass|null
     */
    public ?\stdClass $quizmanifestcontents = null;
    /**
     * URL of Moodle instance
     *
     * @var string
     */
    public string $moodleurl;
    /**
     * Full path to manifest file
     *
     * @var string|null
     */
    public ?string $nonquizmanifestpath = null;
    /**
     * Parsed content of JSON manifest file
     *
     * @var \stdClass|null
     */
    public ?\stdClass $nonquizmanifestcontents = null;
    /**
     * Full path to output file
     *
     * @var string
     */
    public string $filepath;

    /**
     * Constructor
     *
     * @param cli_helper $clihelper
     * @param array $moodleinstances pairs of names and URLs
     */
    public function __construct(cli_helper $clihelper, array $moodleinstances) {
        // Convert command line options into variables.
        $arguments = $clihelper->get_arguments();
        $moodleinstance = $arguments['moodleinstance'];
        $this->moodleurl = $moodleinstances[$moodleinstance];
        $rootdirectory = ($arguments['rootdirectory']) ? $arguments['rootdirectory'] . '/' : '';
        $this->quizmanifestpath = ($arguments['quizmanifestpath']) ?
                    $rootdirectory . $arguments['quizmanifestpath'] : null;
        $this->quizmanifestcontents = json_decode(file_get_contents($this->quizmanifestpath));
        if (!$this->quizmanifestcontents) {
            echo "\nUnable to access or parse manifest file: {$this->quizmanifestpath}\nAborting.\n";
            $this->call_exit();
        }
        if ($this->quizmanifestcontents->context->moodleurl !== $this->moodleurl) {
            echo "\nManifest file is for the wrong Moodle instance: {$this->quizmanifestpath}\nAborting.\n";
            $this->call_exit();
        }
        $instanceid = $this->quizmanifestcontents->context->instanceid;
        if (!empty($arguments['nonquizmanifestpath'])) {
            $this->nonquizmanifestpath = ($arguments['nonquizmanifestpath']) ?
                    $rootdirectory . $arguments['nonquizmanifestpath'] : null;
            $this->nonquizmanifestcontents = json_decode(file_get_contents($this->nonquizmanifestpath));
            if (!$this->nonquizmanifestcontents) {
                echo "\nUnable to access or parse manifest file: {$this->nonquizmanifestpath}\nAborting.\n";
                $this->call_exit();
            }
            if ($this->nonquizmanifestcontents->context->moodleurl !== $this->moodleurl) {
                echo "\nManifest file is for the wrong Moodle instance: {$this->nonquizmanifestpath}\nAborting.\n";
                $this->call_exit();
            }
        }
        if (is_array($arguments['token'])) {
            $token = $arguments['token'][$moodleinstance];
        } else {
            $token = $arguments['token'];
        }

        $wsurl = $this->moodleurl . '/webservice/rest/server.php';

        $this->curlrequest = $this->get_curl_request($wsurl);
        $this->postsettings = [
            'wstoken' => $token,
            'wsfunction' => 'qbank_gitsync_export_quiz_data',
            'moodlewsrestformat' => 'json',
            'moduleid' => $instanceid,
            'coursename' => null,
            'quizname' => null,
        ];
        $this->curlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->curlrequest->set_option(CURLOPT_POST, 1);
        $this->curlrequest->set_option(CURLOPT_POSTFIELDS, $this->postsettings);
        if (empty($arguments['subcall'])) {
            $this->listcurlrequest = $this->get_curl_request($wsurl);
            $this->listpostsettings = [
                'wstoken' => $token,
                'wsfunction' => 'qbank_gitsync_get_question_list',
                'moodlewsrestformat' => 'json',
                'contextlevel' => 70,
                'coursename' => null,
                'modulename' => null,
                'coursecategory' => null,
                'qcategoryname' => 'top',
                'qcategoryid' => null,
                'instanceid' => $instanceid,
                'contextonly' => 1,
                'qbankentryids[]' => null,
                'ignorecat' => null,
            ];
            $this->listcurlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
            $this->listcurlrequest->set_option(CURLOPT_POST, 1);
            $clihelper->check_context($this, false, false);
        }
    }

    /**
     * Get quiz data from webservice, convert question ids to file locations
     * and then write to file.
     *
     * @return void
     */
    public function process(): void {
        $this->export_quiz_data();
    }

    /**
     * Wrapper for cURL request to allow mocking.
     *
     * @param string $wsurl webservice URL
     * @return curl_request
     */
    public function get_curl_request($wsurl): curl_request {
        return new \qbank_gitsync\curl_request($wsurl);
    }

    /**
     * Get quiz data from webservice, convert question ids to file locations
     * and then write to file.
     *
     * @return void
     */
    public function export_quiz_data() {
        $response = $this->curlrequest->execute();
        $responsejson = json_decode($response);
        if (!$responsejson) {
            echo "Broken JSON returned from Moodle:\n";
            echo $response . "\n";
            echo "Quiz data file not updated.\n";
            $this->call_exit();
            $responsejson = json_decode('{"quiz": {"name": ""}, "questions": []}'); // For unit test purposes.
        } else if (property_exists($responsejson, 'exception')) {
            echo "{$responsejson->message}\n";
            if (property_exists($responsejson, 'debuginfo')) {
                echo "{$responsejson->debuginfo}\n";
            }
            echo "Quiz data file not updated.\n";
            $this->call_exit();
            $responsejson = json_decode('{"quiz": {"name": ""}, "questions": []}'); // For unit test purposes.
        }
        $quizmanifestentries = [];
        $nonquizmanifestentries = [];
        $missingquestions = false;
        // Determine quiz info location based on locations of manifest paths.
        if ($this->quizmanifestpath) {
            $this->filepath = cli_helper::get_quiz_structure_path($responsejson->quiz->name, dirname($this->quizmanifestpath));
            $quizmanifestentries = array_column($this->quizmanifestcontents->questions, null, 'questionbankentryid');
        } else {
            $this->filepath = cli_helper::get_quiz_structure_path($responsejson->quiz->name, dirname($this->nonquizmanifestpath));
        }
        if ($this->nonquizmanifestpath) {
            $nonquizmanifestentries = array_column($this->nonquizmanifestcontents->questions, null, 'questionbankentryid');
        }
        // Convert the returned QBE ids into file locations using the manifest files to translate.
        foreach ($responsejson->questions as $question) {
            $quizmanifestentry = $quizmanifestentries["{$question->questionbankentryid}"] ?? false;
            $nonquizmanifestentry = $nonquizmanifestentries["{$question->questionbankentryid}"] ?? false;
            if ($quizmanifestentry) {
                $question->quizfilepath = $quizmanifestentry->filepath;
                unset($question->questionbankentryid);
            } else if ($nonquizmanifestentry) {
                $question->nonquizfilepath = $nonquizmanifestentry->filepath;
                unset($question->questionbankentryid);
            } else {
                $missingquestions = true;
                $multiple = ($this->quizmanifestpath && $this->nonquizmanifestpath) ? 's' : '';
                echo "\nQuestion: {$question->questionbankentryid}\n";
                echo "This question is in the quiz but not in the supplied manifest file{$multiple}\n";
            }
        }
        if ($missingquestions) {
            echo "Questions must either be in the repo for the quiz context defined by a supplied quiz manifest " .
                    "(--quizmanifestpath) or in the context (e.g. course) " .
                    "defined by a different manifest (--nonquizmanifestpath).\n";
            echo "You can supply either or both. If your quiz questions are spread between 3 or more contexts " .
                    "you will need to consolidate them.\n";
            echo "Quiz structure file: {$this->filepath} not updated.\n";
        } else {
            // Save exported information (including relative file location but not QBE id so Moodle independent).
            $success = file_put_contents($this->filepath, json_encode($responsejson));
            if ($success === false) {
                echo "\nUnable to update quiz structure file: {$this->filepath}\n Aborting.\n";
                $this->call_exit();
            }
            echo "Quiz data exported to:\n";
            echo "{$this->filepath}\n";
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
