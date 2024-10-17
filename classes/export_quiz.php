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
 * Utilised in cli\exportquizdatafrommoodle.php
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
 * Export a Git repo.
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
     * Full path to manifest file
     *
     * @var string
     */
    public string $quizmanifestpath;
    /**
     * Parsed content of JSON manifest file
     *
     * @var \stdClass|null
     */
    public ?\stdClass $quizmanifestcontents;
    /**
     * URL of Moodle instance
     *
     * @var string
     */
    public string $moodleurl;
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
        // TODO Use additional manifest file as well.
        $this->quizmanifestpath = $arguments['rootdirectory'] . '/' . $arguments['quizmanifestpath'];
        if (is_array($arguments['token'])) {
            $token = $arguments['token'][$moodleinstance];
        } else {
            $token = $arguments['token'];
        }
        $this->quizmanifestcontents = json_decode(file_get_contents($this->quizmanifestpath));
        if (!$this->quizmanifestcontents) {
            echo "\nUnable to access or parse manifest file: {$this->quizmanifestpath}\nAborting.\n";
            $this->call_exit();
        }

        $this->tempfilepath = str_replace(cli_helper::MANIFEST_FILE,
                                          '_export' . cli_helper::TEMP_MANIFEST_FILE,
                                          $this->quizmanifestpath);
        $this->moodleurl = $moodleinstances[$moodleinstance];
        $wsurl = $this->moodleurl . '/webservice/rest/server.php';

        $this->curlrequest = $this->get_curl_request($wsurl);
        $this->postsettings = [
            'wstoken' => $token,
            'wsfunction' => 'qbank_gitsync_export_quiz_data',
            'moodlewsrestformat' => 'json',
            'quizname' => $arguments['moodlename'],
            'moduleid' => $arguments['instanceid'],
        ];
        $this->curlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->curlrequest->set_option(CURLOPT_POST, 1);
        $this->curlrequest->set_option(CURLOPT_POSTFIELDS, $this->postsettings);
    }

    /**
     * Get quiz data from webservice, convert question ids to file locations
     * and then write to file.
     *
     * @return void
     */
    public function process():void {
        $this->export_quiz_data();
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
    public function export_quiz_data() {
        $response = $this->curlrequest->execute();
        $responsejson = json_decode($response);
        if (!$responsejson) {
            echo "Broken JSON returned from Moodle:\n";
            echo $response . "\n";
            echo "{$this->filepath} not updated.\n";
            $this->call_exit();
        } else if (property_exists($responsejson, 'exception')) {
            echo "{$responsejson->message}\n";
            if (property_exists($responsejson, 'debuginfo')) {
                echo "{$responsejson->debuginfo}\n";
            }
            echo "{$this->filepath} not updated.\n";
            $this->call_exit();
        }
        $this->filepath = cli_helper::get_quiz_structure_path($responsejson->quiz->name, dirname($this->quizmanifestpath));
        $manifestentries = array_column($this->quizmanifestcontents->questions, null, 'questionbankentryid');
        foreach ($responsejson->questions as $question) {
            $manifestentry = $manifestentries["{$question->questionbankentryid}"] ?? false;
            if ($manifestentry) {
                $question->quizfilepath = $manifestentry->filepath;
                unset($question->questionbankentryid);
            } else {
                // TODO - what happens here?
            }
        }
        file_put_contents($this->filepath, json_encode($responsejson));
    }

    /**
     * Mockable function that just exits code.
     *
     * Required to stop PHPUnit displaying output after exit.
     *
     * @return void
     */
    public function call_exit():void {
        exit;
    }
}
