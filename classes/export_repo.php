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
 * Wrapper class for processing performed by command line interface for exporting a repo from Moodle.
 *
 * Utilised in cli\exportrepo.php
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
class export_repo {
    use export_trait;
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
     * Full path to manifest file
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
     * Parsed content of JSON manifest file
     *
     * @var \stdClass|null
     */
    public ?\stdClass $manifestcontents;

    /**
     * Constructor
     *
     * @param cli_helper $clihelper
     * @param array $moodleinstances pairs of names and URLs
     */
    public function __construct(cli_helper $clihelper, array $moodleinstances) {
        // Convert command line options into variables.
        // (Moodle code rules don't allow 'extract()').
        $arguments = $clihelper->get_arguments();
        $moodleinstance = $arguments['moodleinstance'];
        $this->manifestpath = $arguments['rootdirectory'] . $arguments['manifestpath'];
        if (is_array($arguments['token'])) {
            $token = $arguments['token'][$moodleinstance];
        } else {
            $token = $arguments['token'];
        }
        $this->manifestcontents = json_decode(file_get_contents($this->manifestpath));
        $this->tempfilepath = dirname($this->manifestpath) . '/' . $this->manifestcontents->context->qcategoryname . '/' .
            $moodleinstance . '_' . $this->manifestcontents->context->contextlevel . cli_helper::TEMP_MANIFEST_FILE;
        $moodleurl = $moodleinstances[$moodleinstance];
        $wsurl = $moodleurl . '/webservice/rest/server.php';

        $this->curlrequest = $this->get_curl_request($wsurl);
        $this->postsettings = [
            'wstoken' => $token,
            'wsfunction' => 'qbank_gitsync_export_question',
            'moodlewsrestformat' => 'json',
            'questionbankentryid' => null,
            'includecategory' => 0,
        ];
        $this->curlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->curlrequest->set_option(CURLOPT_POST, 1);
        $this->listcurlrequest = $this->get_curl_request($wsurl);
        $this->listpostsettings = [
            'wstoken' => $token,
            'wsfunction' => 'qbank_gitsync_get_question_list',
            'moodlewsrestformat' => 'json',
            'contextlevel' => $this->manifestcontents->context->contextlevel,
            'coursename' => $this->manifestcontents->context->coursename,
            'modulename' => $this->manifestcontents->context->modulename,
            'coursecategory' => $this->manifestcontents->context->coursecategory,
            'qcategoryname' => $this->manifestcontents->context->qcategoryname
        ];
        $this->listcurlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->listcurlrequest->set_option(CURLOPT_POST, 1);
        $this->listcurlrequest->set_option(CURLOPT_POSTFIELDS, $this->listpostsettings);
    }

    /**
     * Iterate through the manifest file, request up to date versions via
     * the webservice and update local files.
     *
     * @return void
     */
    public function process():void {
        // Export latest versions of questions in manifest from Moodle.
        $this->export_questions_in_manifest();
        // Export any questions that are in Moodle but not in the manifest.
        $this->export_to_repo();
        unlink($this->tempfilepath);
        // Remove questions from manifest that are no longer in Moodle.
        // Will be restored from repo on next import if file is still there.
        $this->tidy_manifest();
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
    public function export_questions_in_manifest() {
        foreach ($this->manifestcontents->questions as $questioninfo) {
            $this->postsettings['questionbankentryid'] = $questioninfo->questionbankentryid;
            $this->curlrequest->set_option(CURLOPT_POSTFIELDS, $this->postsettings);
            $response = $this->curlrequest->execute();
            $responsejson = json_decode($response);
            if (!$responsejson) {
                echo "Broken JSON returned from Moodle:\n";
                echo $response . "\n";
                echo "{$questioninfo->filepath} not updated.\n";
            } else if (property_exists($responsejson, 'exception')) {
                echo "{$responsejson->message}\n";
                if (property_exists($responsejson, 'debuginfo')) {
                    echo "{$responsejson->debuginfo}\n";
                }
                echo "{$questioninfo->filepath} not updated.\n";
            } else {
                $question = cli_helper::reformat_question($responsejson->question);
                $questioninfo->exportedversion = $responsejson->version;
                file_put_contents(dirname($this->manifestpath) . $questioninfo->filepath, $question);
            }
        }
        // Will not be updated properly if there is an error but this is no loss.
        // Process can simply be run again from start.
        file_put_contents($this->manifestpath, json_encode($this->manifestcontents));
    }

    /**
     * Loop through questions in manifest file and
     * remove from file if the matching question is no longer in Moodle.
     *
     * @return void
     */
    public function tidy_manifest():void {
        $response = $this->listcurlrequest->execute();
        $questionsinmoodle = json_decode($response);
        if (is_null($questionsinmoodle)) {
            echo "Broken JSON returned from Moodle:\n";
            echo $response . "\n";
            echo "Failed to tidy manifest.\n";
        } else if (!is_array($questionsinmoodle)) {
            if (property_exists($questionsinmoodle, 'exception')) {
                echo "{$questionsinmoodle->message}\n";
            }
            echo "Failed to tidy manifest.\n";
        } else {
            $existingquestions = array_column($questionsinmoodle, null, 'questionbankentryid');
            $newentrylist = [];
            foreach ($this->manifestcontents->questions as $currententry) {
                if (isset($existingquestions[$currententry->questionbankentryid])) {
                    array_push($newentrylist, $currententry);
                }
            }
            $this->manifestcontents->questions = $newentrylist;
            file_put_contents($this->manifestpath, json_encode($this->manifestcontents));
        }
    }
}
