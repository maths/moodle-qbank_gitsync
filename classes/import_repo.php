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
 * Wrapper class for processing performed by command line interface for importing a repo.
 *
 * Utilised in cli\importrepo.php
 *
 * Allows mocking and unit testing via PHPUnit.
 * Used outside Moodle.
 *
 * @package    qbank_gitsync
 * @copyright  2023 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace qbank_gitsync;
use stdClass;
/**
 * Import a Git repo.
 */
class import_repo {
    use tidy_trait;
    /**
     * File system iterator for categories
     *
     * @var \RecursiveIteratorIterator
     */
    public \RecursiveIteratorIterator $repoiterator;
    /**
     * File system iterator for questions
     *
     * @var \RecursiveIteratorIterator
     */
    public \RecursiveIteratorIterator $subdirectoryiterator;
    /**
     * Settings for POST request
     *
     * These are the parameters for the webservice import call.
     *
     * @var array
     */
    public array $postsettings;
    /**
     * Settings for POST file upload request
     *
     * These are the parameters for the webservice upload call.
     *
     * @var array
     */
    public array $uploadpostsettings;
    /**
     * Settings for question delete request
     *
     * These are the parameters for the webservice delete call.
     *
     * @var array
     */
    public array $deletepostsettings;
    /**
     * Settings for question list request
     *
     * These are the parameters for the webservice list call.
     *
     * @var array
     */
    public array $listpostsettings;
    /**
     * cURL request handle
     *
     * @var curl_request
     */
    public curl_request $curlrequest;
    /**
     * cURL request handle for file upload
     *
     * @var curl_request
     */
    public curl_request $uploadcurlrequest;
    /**
     * cURL request handle for question delete
     *
     * @var curl_request
     */
    public curl_request $deletecurlrequest;
    /**
     * cURL request handle for question list retrieve
     *
     * @var curl_request
     */
    public curl_request $listcurlrequest;
    /**
     * Path to temporary manifest file
     *
     * @var string
     */
    public string $tempfilepath;
    /**
     * Path to actual manifest file
     *
     * @var string
     */
    public string $manifestpath;
    /**
     * URL of Moodle instance
     *
     * @var string
     */
    public string $moodleurl;
    /**
     * Path of root of repo
     * i.e. folder containing manifest
     *
     * @var string
     */
    public string $directory;
    /**
     * Relative path of subdirectory to import.
     *
     * @var string
     */
    public string $subdirectory;
    /**
     * Are we using git?.
     * Set in config. Adds commit hash to manifest.
     * @var bool
     */
    public bool $usegit;
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
     * @param bool $usegit
     */
    public function __construct(cli_helper $clihelper, array $moodleinstances) {
        // Convert command line options into variables.
        // (Moodle code rules don't allow 'extract()').
        $arguments = $clihelper->get_arguments();
        $moodleinstance = $arguments['moodleinstance'];
        if ($arguments['directory']) {
            $this->directory = $arguments['rootdirectory'] . '/' . $arguments['directory'];
        } else {
            $this->directory = $arguments['rootdirectory'];
        }
        $this->subdirectory = $arguments['subdirectory'];
        if (is_array($arguments['token'])) {
            $token = $arguments['token'][$moodleinstance];
        } else {
            $token = $arguments['token'];
        }
        $contextlevel = $arguments['contextlevel'];
        $coursename = $arguments['coursename'];
        $modulename = $arguments['modulename'];
        $coursecategory = $arguments['coursecategory'];
        $qcategoryid = $arguments['qcategoryid'];
        $instanceid = $arguments['instanceid'];
        $manifestpath = $arguments['manifestpath'];

        $this->usegit = $arguments['usegit'];

        $this->moodleurl = $moodleinstances[$moodleinstance];
        $wsurl = $this->moodleurl . '/webservice/rest/server.php';

        $this->curlrequest = $this->get_curl_request($wsurl);
        $this->deletecurlrequest = $this->get_curl_request($wsurl);
        $this->listcurlrequest = $this->get_curl_request($wsurl);
        $this->uploadcurlrequest = $this->get_curl_request($this->moodleurl . '/webservice/upload.php');

        $this->postsettings = [
            'wstoken' => $token,
            'wsfunction' => 'qbank_gitsync_import_question',
            'moodlewsrestformat' => 'json',
            'questionbankentryid' => null,
            'contextlevel' => cli_helper::get_context_level($contextlevel),
            'coursename' => $coursename,
            'modulename' => $modulename,
            'coursecategory' => $coursecategory,
            'instanceid' => $instanceid,
        ];
        $this->curlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->curlrequest->set_option(CURLOPT_POST, 1);
        $this->uploadpostsettings = [
            'token' => $token,
            'moodlewsrestformat' => 'json'
        ];
        $this->uploadcurlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->uploadcurlrequest->set_option(CURLOPT_POST, 1);
        $this->deletepostsettings = [
            'wstoken' => $token,
            'wsfunction' => 'qbank_gitsync_delete_question',
            'moodlewsrestformat' => 'json',
            'questionbankentryid' => null
        ];
        $this->deletecurlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->deletecurlrequest->set_option(CURLOPT_POST, 1);
        $this->listpostsettings = [
            'wstoken' => $token,
            'wsfunction' => 'qbank_gitsync_get_question_list',
            'moodlewsrestformat' => 'json',
            'contextlevel' => cli_helper::get_context_level($contextlevel),
            'coursename' => $coursename,
            'modulename' => $modulename,
            'coursecategory' => $coursecategory,
            'qcategoryname' => $this->subdirectory,
            'qcategoryid' => $qcategoryid,
            'instanceid' => $instanceid,
            'contextonly' => 0,
        ];
        $this->listcurlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->listcurlrequest->set_option(CURLOPT_POST, 1);
        $this->listcurlrequest->set_option(CURLOPT_POSTFIELDS, $this->listpostsettings);

        if ($manifestpath) {
            $this->manifestpath = $arguments['rootdirectory'] . '/' . $manifestpath;
        } else {
            $instanceinfo = $clihelper->check_context($this);
            $this->manifestpath = cli_helper::get_manifest_path($moodleinstance, $contextlevel, $instanceinfo->contextinfo->categoryname,
                                                $instanceinfo->contextinfo->coursename,
                                                $instanceinfo->contextinfo->modulename, $this->directory);
        }
        $this->tempfilepath = str_replace(cli_helper::MANIFEST_FILE,
                                          '_import' . cli_helper::TEMP_MANIFEST_FILE,
                                           $this->manifestpath);
        // Create manifest file if it doesn't already exist.
        $manifestfile = fopen($this->manifestpath, 'a+');
        if ($manifestfile === false) {
            echo "\nUnable to access manifest file: {$this->manifestpath}\n Aborting.\n";
            $this->call_exit();
            return; // Required for PHPUnit.
        }
        fclose($manifestfile);
        $contentsjson = file_get_contents($this->manifestpath);
        $manifestcontents = json_decode($contentsjson);
        if ($manifestcontents === null && $contentsjson) {
            echo "\nUnable to parse manifest file: {$this->manifestpath}\nAborting.\n";
            $this->call_exit();
        }
        if (!$manifestcontents && $manifestpath) {
            echo "\nManifest file is empty: {$this->manifestpath}\nAborting.\n";
            $this->call_exit();
        } else if (!$manifestcontents && !$manifestpath) {
            $this->manifestcontents = new \stdClass();
            $this->manifestcontents->context = null;
            $this->manifestcontents->questions = [];
        } else {
            $this->manifestcontents = $manifestcontents;
        }

        if ($manifestpath) {
            // Set context info from manifest file rather than other CLI arguments.
            $this->postsettings['instanceid'] = $this->manifestcontents->instanceid;
            $this->postsettings['coursename'] = $this->manifestcontents->coursename;
            $this->postsettings['modulename'] = $this->manifestcontents->modulename;
            $this->postsettings['coursecategory'] = $this->manifestcontents->coursecategory;
            $this->listpostsettings['instanceid'] = $this->manifestcontents->instanceid;
            $this->listpostsettings['coursename'] = $this->manifestcontents->coursename;
            $this->listpostsettings['modulename'] = $this->manifestcontents->modulename;
            $this->listpostsettings['coursecategory'] = $this->manifestcontents->coursecategory;
            $this->listcurlrequest->set_option(CURLOPT_POSTFIELDS, $this->listpostsettings);
            $instanceinfo = $clihelper->check_context($this);
        }
        if (count($this->manifestcontents->questions) === 0) {
            echo "\nManifest file is empty. This should only be the case if you are importing ";
            echo "questions for the first time into a Moodle context where they don't already exist.\n";
            $this->handle_abort();
        }
    }

    /**
     * Iterate through the directory structure and call the web service
     * to create categories and questions.
     *
     * @return void
     */
    public function process():void {
        $this->import_categories();
        $this->import_questions();
        $this->curlrequest->close();
        $this->manifestcontents = cli_helper::create_manifest_file($this->manifestcontents,
                                                                   $this->tempfilepath,
                                                                   $this->manifestpath,
                                                                   $this->moodleurl);
        unlink($this->tempfilepath);
        $this->delete_no_file_questions(false);
        $this->delete_no_record_questions(false);
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
     * Import categories
     *
     * @return void
     */
    public function import_categories():void {
        $this->repoiterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        // Find all the category files first and create categories where needed.
        // Categories will be dealt with before their sub-categories. Beyond that,
        // order is uncertain.
        foreach ($this->repoiterator as $repoitem) {
            if ($repoitem->isFile()) {
                if (pathinfo($repoitem, PATHINFO_EXTENSION) === 'xml'
                        && pathinfo($repoitem, PATHINFO_FILENAME) === cli_helper::CATEGORY_FILE) {
                    $this->postsettings['qcategoryname'] = '';
                    $this->upload_file($repoitem);
                    $this->curlrequest->set_option(CURLOPT_POSTFIELDS, $this->postsettings);
                    $response = $this->curlrequest->execute();
                    $responsejson = json_decode($response);
                    if (!$responsejson) {
                        echo "Broken JSON returned from Moodle:\n";
                        echo $response . "\n";
                        echo "{$repoitem->getPathname()} not imported?\n";
                        echo "Stopping before trying to import questions.";
                        $this->call_exit();;
                    } else if (property_exists($responsejson, 'exception')) {
                        echo "{$responsejson->message}\n";
                        if (property_exists($responsejson, 'debuginfo')) {
                            echo "{$responsejson->debuginfo}\n";
                        }
                        echo "{$repoitem->getPathname()} not imported.\n";
                        echo "Stopping before trying to import questions.";
                        $this->call_exit();;
                    }
                }
            }
        }
    }

    /**
     * Uploads a file to Moodle via cURL request to webservice
     *
     * Fileinfo parameter is set ready for import call to the webservice.
     *
     * @param resource $repoitem
     * @return bool success or failure
     */
    public function upload_file($repoitem):bool {
        $this->uploadpostsettings['file_1'] = new \CURLFile($repoitem->getPathname());
        $this->uploadcurlrequest->set_option(CURLOPT_POSTFIELDS, $this->uploadpostsettings);
        $fileinfo = json_decode($this->uploadcurlrequest->execute());
        // We're expecting an array containing one file information object.
        // If things go wrong, we should get just an error object.
        if (!is_array($fileinfo)) {
            if (property_exists($fileinfo, 'error')) {
                echo "{$fileinfo->error}\n";
            }
            echo "{$repoitem->getPathname()} not imported.\n";
            return false;
        }
        $fileinfo = $fileinfo[0];
        $this->postsettings['fileinfo[contextid]'] = $fileinfo->contextid;
        $this->postsettings['fileinfo[userid]'] = $fileinfo->userid;
        $this->postsettings['fileinfo[component]'] = $fileinfo->component;
        $this->postsettings['fileinfo[filearea]'] = $fileinfo->filearea;
        $this->postsettings['fileinfo[itemid]'] = $fileinfo->itemid;
        $this->postsettings['fileinfo[filepath]'] = $fileinfo->filepath;
        $this->postsettings['fileinfo[filename]'] = $fileinfo->filename;
        return true;
    }

    /**
     * Import questions
     *
     * @return resource Temporary manifest file of added questions, one line per question.
     */
    public function import_questions() {
        if ($this->subdirectory) {
            $subdirectory = $this->directory . '/' . $this->subdirectory;
        } else {
            $subdirectory = $this->directory;
        }
        $this->subdirectoryiterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($subdirectory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $tempfile = fopen($this->tempfilepath, 'w+');
        if ($tempfile === false) {
            echo "\nUnable to access temp file: {$this->tempfilepath}\nAborting.\n";
            $this->call_exit();
        }
        $existingentries = array_column($this->manifestcontents->questions, null, 'filepath');
        // Find all the question files and import them. Order is uncertain.
        foreach ($this->subdirectoryiterator as $repoitem) {
            if ($repoitem->isFile()) {
                if (pathinfo($repoitem, PATHINFO_EXTENSION) === 'xml'
                        && pathinfo($repoitem, PATHINFO_FILENAME) !== cli_helper::CATEGORY_FILE) {
                    // Avoid starting slash in categoryname.
                    $qcategory = $this->subdirectory;
                    if ($qcategory && $this->subdirectoryiterator->getSubPath()) {
                        $qcategory = $qcategory . '/' . $this->subdirectoryiterator->getSubPath();
                    } else if ($this->subdirectoryiterator->getSubPath()) {
                        $qcategory = $this->subdirectoryiterator->getSubPath();
                    }
                    // Path of file (without filename) relative to base $directory.
                    $this->postsettings['qcategoryname'] = str_replace( '\\', '/',
                                        $qcategory);
                    if ($this->postsettings['qcategoryname']) {
                        $relpath = str_replace(dirname($this->manifestpath), '', $repoitem->getPathname());
                        $relpath = str_replace( '\\', '/', $relpath);
                        $existingentry = $existingentries["{$relpath}"] ?? false;
                        if ($existingentry) {
                            $this->postsettings['questionbankentryid'] = $existingentry->questionbankentryid;
                            if (isset($existingentry->currentcommit)
                                    && isset($existingentry->moodlecommit)
                                    && $existingentry->currentcommit === $existingentry->moodlecommit) {
                                continue;
                            }
                        } else {
                            $this->postsettings['questionbankentryid'] = null;
                        }
                        if (!$this->upload_file($repoitem)) {
                            echo 'File upload problem.\n';
                            echo "{$repoitem->getPathname()} not imported.\n";
                            continue;
                        };
                        $this->curlrequest->set_option(CURLOPT_POSTFIELDS, $this->postsettings);
                        $response = $this->curlrequest->execute();
                        $responsejson = json_decode($response);
                        if (!$responsejson) {
                            echo "Broken JSON returned from Moodle:\n";
                            echo $response . "\n";
                            echo "{$repoitem->getPathname()} not imported.\n";
                        } else if (property_exists($responsejson, 'exception')) {
                            echo "{$responsejson->message}\n";
                            if (property_exists($responsejson, 'debuginfo')) {
                                echo "{$responsejson->debuginfo}\n";
                            }
                            echo "{$repoitem->getPathname()} not imported.\n";
                        } else if ($responsejson->questionbankentryid) {
                            $fileoutput = [
                                'questionbankentryid' => $responsejson->questionbankentryid,
                                'version' => $responsejson->version,
                                // Questions can be imported in multiple contexts.
                                'contextlevel' => $this->postsettings['contextlevel'],
                                'filepath' => $repoitem->getPathname(),
                                'coursename' => $this->postsettings['coursename'],
                                'modulename' => $this->postsettings['modulename'],
                                'coursecategory' => $this->postsettings['coursecategory'],
                                'instanceid' => $this->postsettings['instanceid'],
                                'format' => 'xml',
                            ];
                            if ($existingentry && isset($existingentry->currentcommit)) {
                                $fileoutput['moodlecommit'] = $existingentry->currentcommit;
                            }
                            if ($this->usegit && !$existingentry) {
                                $manifestdirname = dirname($this->manifestpath);
                                chdir($manifestdirname);
                                $commithash = exec('git log -n 1 --pretty=format:%H -- "' . $repoitem->getPathname() . '"');
                                $fileoutput['moodlecommit'] = $commithash;
                                $fileoutput['currentcommit'] = $commithash;
                            }
                            fwrite($tempfile, json_encode($fileoutput) . "\n");
                        }
                    } else {
                        echo "Root directory should not contain XML files, only a 'top' directory and manifests.\n" .
                             "{$repoitem->getPathname()} not imported.\n";
                    }
                }
            }
        }
        fclose($tempfile);
        return $tempfile;
    }

    /**
     * Use to update manifest file after CLI failure.
     *
     * @return void
     */
    public function recovery():void {
        if (file_exists($this->tempfilepath)) {
            $this->manifestcontents = cli_helper::create_manifest_file($this->manifestcontents,
                                                                    $this->tempfilepath,
                                                                    $this->manifestpath,
                                                                    $this->moodleurl);
            unlink($this->tempfilepath);
        }
    }

    /**
     * Offer to delete questions from Moodle/manifest where the question is in the manifest
     * but there is no file in the repo.
     *
     * @param bool $deleenabled Allows question delete if true, otherwise just lists applicable questions
     * @return void
     */
    public function delete_no_file_questions($deleteenabled=false):void {
        // Get all manifest entries for imported subdirectory.
        $manifestentries = array_filter($this->manifestcontents->questions, function($value) {
            return (substr($value->filepath, 1, strlen($this->subdirectory)) === $this->subdirectory);
        });
        // Check to see there is a matching file in the repo still.
        $questionstodelete = [];
        $manifestdir = dirname($this->manifestpath);
        foreach ($manifestentries as $manifestentry) {
            if (!file_exists($manifestdir . $manifestentry->filepath)) {
                array_push($questionstodelete, $manifestentry);
            }
        }
        // If not offer to delete questions from Moodle as well.
        if (!empty($questionstodelete)) {
            echo "\nThese questions are listed in the manifest but there is no longer a matching file:\n";

            foreach ($questionstodelete as $question) {
                echo $question->filepath . "\n";
            }
            unset($question);
            if ($deleteenabled) {
                $existingentries = array_column($this->manifestcontents->questions, null, 'questionbankentryid');
                foreach ($questionstodelete as $question) {
                    echo "\nDelete {$question->filepath} from Moodle? y/n\n";
                    $wasdeleted = $this->handle_delete($question);
                    if ($wasdeleted) {
                        unset($existingentries["{$question->questionbankentryid}"]);
                    }
                }
                $this->manifestcontents->questions = array_values($existingentries);
                // On file failure will be picked up next time.
                file_put_contents($this->manifestpath, json_encode($this->manifestcontents));
            } else {
                echo "Run deletefrommoodle for the option to delete.\n";
            }
        }
    }

    /**
     * Offer to delete questions from Moodle where the question is in Moodle
     * but not in the manifest.
     *
     * @param bool $deleenabled Allows question delete if true, otherwise just lists applicable questions
     * @return void
     */
    public function delete_no_record_questions($deleteenabled=false):void {
        if (count($this->manifestcontents->questions) === 0 && $deleteenabled) {
            echo 'Manifest file is empty or inaccessible. You probably want to abort.\n';
            $this->handle_abort();
        }
        $existingentries = array_column($this->manifestcontents->questions, null, 'questionbankentryid');
        $response = $this->listcurlrequest->execute();
        $questionsinmoodle = json_decode($response);
        if (is_null($questionsinmoodle)) {
            echo "Broken JSON returned from Moodle:\n";
            echo $response . "\n";
            echo "Failed to check questions for deletion.\n";
            return;
        } else if (property_exists($questionsinmoodle, 'exception')) {
            echo "{$questionsinmoodle->message}\n";
            echo "Failed to check questions for deletion.\n";
            return;
        }
        $questionstodelete = [];
        // Check each question in Moodle to see if there is a corresponding entry
        // in the manifest for that questionbankentryid.
        foreach ($questionsinmoodle->questions as $moodleq) {
            if (!array_key_exists($moodleq->questionbankentryid, $existingentries)) {
                array_push($questionstodelete, $moodleq);
            }
        }
        // If not offer to delete question from Moodle.
        if (!empty($questionstodelete)) {
            echo "\nThese questions are in Moodle but not linked to your repository:\n";

            foreach ($questionstodelete as $question) {
                echo "{$question->questionbankentryid} - {$question->questioncategory} - {$question->name}\n";
            }
            unset($question);
            if ($deleteenabled) {
                $existingentries = array_column($this->manifestcontents->questions, null, 'questionbankentryid');
                foreach ($questionstodelete as $question) {
                    echo "\nDelete {$question->questioncategory} - {$question->name} from Moodle? y/n\n";
                    $this->handle_delete($question);
                }
            } else {
                echo "Run deletefrommoodle for the option to delete.\n";
            }
        }
    }

    /**
     * Prompt user whether to delete question
     *
     * @param object $question \stdClass question to be deleted
     * @return bool Was the question deleted
     */
    public function handle_delete(object $question):bool {
        $deleted = false;
        $handle = fopen ("php://stdin", "r");
        $line = fgets($handle);
        if (trim($line) === 'y') {
            $this->deletepostsettings['questionbankentryid'] = $question->questionbankentryid;
            $this->deletecurlrequest->set_option(CURLOPT_POSTFIELDS, $this->deletepostsettings);
            $response = $this->deletecurlrequest->execute();
            $responsejson = json_decode($response);
            if (!$responsejson) {
                echo "Broken JSON returned from Moodle:\n";
                echo $response . "\n";
                echo 'Not deleted?';
            } else if (property_exists($responsejson, 'exception')) {
                echo "{$responsejson->message}\n" .
                    "Not deleted\n";
            } else {
                echo "Deleted\n";
                $deleted = true;
            }
        } else {
            echo "Not deleted\n";
        }
        fclose($handle);
        return $deleted;
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

    /**
     * Check if the question versions in Moodle and the manifest match.
     *
     * @return void
     */
    public function check_question_versions(): void {
        $response = $this->listcurlrequest->execute();
        $questionsinmoodle = json_decode($response);
        if (is_null($questionsinmoodle)) {
            echo "Broken JSON returned from Moodle:\n";
            echo $response . "\n";
            echo "Failed to check question versions.\n";
            $this->call_exit();
            $questionsinmoodle = json_decode('{"questions": []}'); // Required for unit tests.
        } else if (property_exists($questionsinmoodle, 'exception')) {
            echo "{$questionsinmoodle->message}\n";
            echo "Failed to check question versions.\n";
            $this->call_exit();
            $questionsinmoodle = json_decode('{"questions": []}'); // Required for unit tests.
        }
        $manifestentries = array_column($this->manifestcontents->questions, null, 'questionbankentryid');
        $changes = false;
        // If the version in Moodle and in the manifest don't match, the question has been updated in Moodle
        // since we created the repo or last imported to Moodle.
        // If the last exportedversion doesn't match what's in the manifest we haven't dealt with
        // all the changes locally. Instruct user to export.
        foreach ($questionsinmoodle->questions as $moodleq) {
            if (isset($manifestentries[$moodleq->questionbankentryid])
                    && $moodleq->version !== $manifestentries[$moodleq->questionbankentryid]->version
                    && $moodleq->version !== $manifestentries[$moodleq->questionbankentryid]->exportedversion) {
                echo "{$moodleq->questionbankentryid} - {$moodleq->questioncategory} - {$moodleq->name}\n";
                echo "Moodle question version: {$moodleq->version}\n";
                echo "Version on last import to Moodle: {$manifestentries[$moodleq->questionbankentryid]->version}\n";
                echo "Version on last export from Moodle: {$manifestentries[$moodleq->questionbankentryid]->exportedversion}\n";
                $changes = true;
            }
        }
        if ($changes) {
            echo "Export questions from Moodle before proceeding.\n";
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
