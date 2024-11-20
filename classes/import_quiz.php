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
    public ?string $quizmanifestpath = null;
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
    public ?string $nonquizmanifestpath = null;
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
     * @var string|null
     */
    public ?string $quizdatapath = null;
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
        $instanceid = $arguments['instanceid'];
        if ($arguments['quizmanifestpath']) {
            $this->quizmanifestpath = ($arguments['quizmanifestpath']) ?
                    $arguments['rootdirectory'] . '/' . $arguments['quizmanifestpath'] : null;
            $this->quizmanifestcontents = json_decode(file_get_contents($this->quizmanifestpath));
            if (!$this->quizmanifestcontents) {
                echo "\nUnable to access or parse manifest file: {$this->quizmanifestpath}\nAborting.\n";
                $this->call_exit();

            } else {
                $this->quizdatapath = cli_helper::get_quiz_structure_path($this->quizmanifestcontents->context->modulename, dirname($this->quizmanifestpath));
            }
        } else {
            if ($arguments['quizdatapath']) {
                $this->quizdatapath = $arguments['rootdirectory'] . '/' . $arguments['quizdatapath'];
            } else {
                echo "\nPlease supply a quiz manifest filepath or a quiz data filepath.\nAborting.\n";
                $this->call_exit();
                return; // Required for unit tests.
            }
        }
        if ($arguments['nonquizmanifestpath']) {
            $this->nonquizmanifestpath = ($arguments['nonquizmanifestpath']) ?
                    $arguments['rootdirectory'] . '/' . $arguments['nonquizmanifestpath'] : null;
            $this->nonquizmanifestcontents = json_decode(file_get_contents($this->nonquizmanifestpath));
            if (!$this->nonquizmanifestcontents) {
                echo "\nUnable to access or parse manifest file: {$this->nonquizmanifestpath}\nAborting.\n";
                $this->call_exit();
            }
            if ($this->nonquizmanifestcontents->context->contextlevel === cli_helper::get_context_level('course')) {
                $instanceid = $this->nonquizmanifestcontents->context->instanceid;
            }
        }
        if (!$instanceid && !$arguments['coursename']) {
            echo "\nYou must identify the course you wish to add the quiz to. Use a course manifest path (--nonquizmanifestpath)" .
                    "or specify the course id (--instanceid) or course name (--coursename).\nAborting.\n";
            $this->call_exit();
            return; // Required for unit tests.
        }
        $this->quizdatacontents = json_decode(file_get_contents($this->quizdatapath));
        if (!$this->quizdatacontents) {
            echo "\nUnable to access or parse data file: {$this->quizdatapath}\nAborting.\n";
            $this->call_exit();
        }
        if (is_array($arguments['token'])) {
            $token = $arguments['token'][$moodleinstance];
        } else {
            $token = $arguments['token'];
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
            'instanceid' => $instanceid,
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
        $instanceinfo = $this->clihelper->check_context($this, false, true);
        if ($arguments['subcall']) {
            echo "\nCreating quiz: {$this->quizdatacontents->quiz->name}\n";
        } else {
            echo "\nPreparing to create a new quiz in Moodle.\n";
            echo "Moodle URL: {$this->moodleurl}\n";
            echo "Course: {$instanceinfo->contextinfo->coursename}\n";
            echo "Quiz: {$this->quizdatacontents->quiz->name}\n";
            $this->handle_abort();
        }
        $this->postsettings['quiz[coursename]'] = $instanceinfo->contextinfo->coursename;
        $this->postsettings['quiz[courseid]'] = $instanceinfo->contextinfo->instanceid;
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
        $quizmanifestentries = [];
        $nonquizmanifestentries = [];
        if ($this->quizmanifestpath) {
            $quizmanifestentries = array_column($this->quizmanifestcontents->questions, null, 'filepath');
        }
        if ($this->nonquizmanifestpath) {
            $nonquizmanifestentries = array_column($this->nonquizmanifestcontents->questions, null, 'filepath');
        }

        foreach ($this->quizdatacontents->quiz as $key => $quizparam) {
            $this->postsettings["quiz[{$key}]"] = $quizparam;
        }

        foreach ($this->quizdatacontents->sections as $sectionkey => $section) {
            foreach ($section as $key => $sectionparam) {
                $this->postsettings["sections[{$sectionkey}][{$key}]"] = $sectionparam;
            }
        }

        foreach ($this->quizdatacontents->questions as $questionkey => $question) {
            foreach ($question as $key => $questionparam) {
                $this->postsettings["questions[{$questionkey}][{$key}]"] = $questionparam;
            }
            $manifestentry = false;
            $qidentifier = '';
            if (isset($question->quizfilepath)) {
                $manifestentry = $quizmanifestentries["{$question->quizfilepath}"] ?? false;
                $qidentifier = "Quiz repo: {$question->quizfilepath}";
                unset($this->postsettings["questions[{$questionkey}][quizfilepath]"]);
            } else if (isset($question->nonquizfilepath)) {
                $manifestentry = $nonquizmanifestentries["{$question->nonquizfilepath}"] ?? false;
                $qidentifier = "Non-quiz repo: {$question->nonquizfilepath}";
                unset($this->postsettings["questions[{$questionkey}][nonquizfilepath]"]);
            }

            if ($manifestentry) {
                $this->postsettings["questions[{$questionkey}][questionbankentryid]"] = $manifestentry->questionbankentryid;
            } else {
                $multiple = ($this->quizmanifestpath && $this->nonquizmanifestpath) ? 's' : '';
                echo "Question: {$qidentifier}\n";
                echo "This question is in the quiz but not in the supplied manifest file" . $multiple . ".\n";
                echo "Questions must either be in the repo for the quiz context defined by a supplied quiz manifest " .
                    "(--quizmanifestpath) or in the course context " .
                    "defined by a different manifest (--nonquizmanifestpath).\n";
                echo "You can supply either or both.\n";
                echo "Aborting.\n";
                $this->call_exit();
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
        echo "Quiz imported.\n";
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

    /**
     * Prompt user whether they want to continue.
     *
     * @return void
     */
    public function handle_abort():void {
        echo "Abort? y/n\n";
        $handle = fopen ("php://stdin", "r");
        $line = fgets($handle);
        if (trim($line) === 'y') {
            $this->call_exit();
        }
        fclose($handle);
    }
}