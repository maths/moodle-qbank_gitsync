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
 * Wrapper class for processing performed by command line interface for importing quiz data to Moodle.
 *
 * Utilised in cli\importquizstructuretomoodle.php
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
 * Import structure data of a Moodle quiz.
 */
class import_quiz {
    /**
     * CLI helper for this import
     *
     * @var cli_helper
     */
    public cli_helper $clihelper;
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
    public ?string $quizmanifestpath;
    /**
     * Parsed content of JSON manifest file
     *
     * @var \stdClass|null
     */
    public ?\stdClass $quizmanifestcontents;
    /**
     * Full path to manifest file
     *
     * @var string|null
     */
    public ?string $nonquizmanifestpath;
    /**
     * Parsed content of JSON manifest file
     *
     * @var \stdClass|null
     */
    public ?\stdClass $nonquizmanifestcontents;
    /**
     * URL of Moodle instance
     *
     * @var string
     */
    public string $moodleurl;
    /**
     * Full path to data file
     *
     * @var string
     */
    public string $quizdatapath;
    /**
     * Parsed content of JSON data file
     *
     * @var \stdClass|null
     */
    public ?\stdClass $quizdatacontents;

    /**
     * Constructor
     *
     * @param cli_helper $clihelper
     * @param array $moodleinstances pairs of names and URLs
     */
    public function __construct(cli_helper $clihelper, array $moodleinstances) {
        // Convert command line options into variables.
        $this->clihelper = $clihelper;
        $arguments = $clihelper->get_arguments();
        $moodleinstance = $arguments['moodleinstance'];
        // TODO Use additional manifest file as well.
        $this->quizmanifestpath = ($arguments['quizmanifestpath']) ?
                $arguments['rootdirectory'] . '/' . $arguments['quizmanifestpath'] : null;
        $this->nonquizmanifestpath = ($arguments['nonquizmanifestpath']) ?
                $arguments['rootdirectory'] . '/' . $arguments['nonquizmanifestpath'] : null;
        $this->quizdatapath = $arguments['rootdirectory'] . '/' . $arguments['quizdatapath'];
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
        $this->quizdatacontents = json_decode(file_get_contents($this->quizdatapath));
        if (!$this->quizdatacontents) {
            echo "\nUnable to access or parse data file: {$this->quizdatapath}\nAborting.\n";
            $this->call_exit();
        }

        $this->moodleurl = $moodleinstances[$moodleinstance];
        $wsurl = $this->moodleurl . '/webservice/rest/server.php';

        $this->listcurlrequest = $this->get_curl_request($wsurl);
        $this->listpostsettings = [
            'wstoken' => $token,
            'wsfunction' => 'qbank_gitsync_get_question_list',
            'moodlewsrestformat' => 'json',
            'contextlevel' => 50,
            'coursename' => $arguments['coursename'],
            'modulename' => null,
            'coursecategory' => null,
            'qcategoryname' => 'top',
            'qcategoryid' => null,
            'instanceid' => $arguments['instanceid'],
            'contextonly' => 1,
            'qbankentryids[]' => null,
            'ignorecat' => null,
        ];
        $this->listcurlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->listcurlrequest->set_option(CURLOPT_POST, 1);

        $this->curlrequest = $this->get_curl_request($wsurl);
        $this->postsettings = [
            'wstoken' => $token,
            'wsfunction' => 'qbank_gitsync_import_quiz_data',
            'moodlewsrestformat' => 'json',
        ];
        $this->curlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->curlrequest->set_option(CURLOPT_POST, 1);
    }

    /**
     * Get quiz data from file, convert question file locations to ids
     * and then import to Moodle.
     *
     * @return void
     */
    public function process():void {
        $this->import_quiz_data();
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
     * Get quiz data from file, convert question file locations to ids
     * and then import to Moodle.
     *
     * @return void
     */
    public function import_quiz_data() {
        $instanceinfo = $this->clihelper->check_context($this, false, true);
        // TODO - Message to user.
        $manifestentries = array_column($this->quizmanifestcontents->questions, null, 'filepath');
        foreach ($this->quizdatacontents->quiz as $key => $quizparam) {
            $this->postsettings["quiz[{$key}]"] = $quizparam;
        }
        $this->postsettings['quiz[coursename]'] = $instanceinfo->contextinfo->coursename;
        $this->postsettings['quiz[courseid]'] = $instanceinfo->contextinfo->instanceid;
        foreach ($this->quizdatacontents->sections as $sectionkey => $section) {
            foreach ($section as $key => $sectionparam) {
                $this->postsettings["sections[{$sectionkey}][{$key}]"] = $sectionparam;
            }
        }
        foreach ($this->quizdatacontents->questions as $questionkey => $question) {
            foreach ($question as $key => $questionparam) {
                $this->postsettings["questions[{$questionkey}][{$key}]"] = $questionparam;
            }
            $manifestentry = $manifestentries["{$question->quizfilepath}"] ?? false;
            if ($manifestentry) {
                $this->postsettings["questions[{$questionkey}][questionbankentryid]"] = $manifestentry->questionbankentryid;
                unset($this->postsettings["questions[{$questionkey}][quizfilepath]"]);
            } else {
                // TODO - what happens here?
            }
        }
        foreach ($this->quizdatacontents->feedback as $feedbackkey => $feedback) {
            foreach ($feedback as $key => $feedbackparam) {
                $this->postsettings["feedback[{$feedbackkey}][{$key}]"] = $feedbackparam;
            }
        }
        $this->curlrequest->set_option(CURLOPT_POSTFIELDS,$this->postsettings);
        $response = $this->curlrequest->execute();
        $responsejson = json_decode($response);
        if (!$responsejson) {
            echo "Broken JSON returned from Moodle:\n";
            echo $response . "\n";
            $this->call_exit();
        } else if (property_exists($responsejson, 'exception')) {
            echo "{$responsejson->message}\n";
            if (property_exists($responsejson, 'debuginfo')) {
                echo "{$responsejson->debuginfo}\n";
            }
            $this->call_exit();
        }
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
