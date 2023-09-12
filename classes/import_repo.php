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
    public string $manifestpath;
    public \stdClass $manifestcontents;
    /**
     * Iterate through the directory structure and call the web service
     * to create categories and questions.
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
        $this->directory = $arguments['directory'];
        $this->subdirectory = '';
        if ($arguments['subdirectory']) {
            $this->subdirectory = $arguments['subdirectory'];
        }
        $token = $arguments['token'];
        $contextlevel = $arguments['contextlevel'];
        $coursename = $arguments['coursename'];
        $modulename = $arguments['modulename'];
        $coursecategory = $arguments['coursecategory'];
        $help = $arguments['help'];

        if ($help) {
            $clihelper->showhelp();
            exit;
        }

        $moodleurl = $moodleinstances[$moodleinstance];
        $wsurl = $moodleurl . '/webservice/rest/server.php';
        $filenamemod = '_' . $contextlevel;
        switch ($contextlevel) {
            case 'coursecategory':
                $filenamemod = $filenamemod . '_' . $coursecategory;
                break;
            case 'course':
                $filenamemod = $filenamemod . '_' . $coursename;
                break;
            case 'module':
                $filenamemod = $filenamemod . '_' . $coursename . '_' . $modulename;
                break;
        }

        $this->manifestpath = $this->directory . '/' . $moodleinstance . $filenamemod . cli_helper::MANIFEST_FILE;
        $this->tempfilepath = $this->directory . $this->subdirectory . '/' .
                              $moodleinstance . $filenamemod . '_manifest_update.tmp';
        $this->manifestcontents = json_decode(file_get_contents($this->manifestpath));

        $this->curlrequest = $this->get_curl_request($wsurl);
        $this->uploadcurlrequest = $this->get_curl_request($moodleurl . '/webservice/upload.php');

        $this->postsettings = [
            'wstoken' => $token,
            'wsfunction' => 'qbank_gitsync_import_question',
            'moodlewsrestformat' => 'json',
            'questionbankentryid' => null,
            'contextlevel' => cli_helper::get_context_level($contextlevel),
            'coursename' => $coursename,
            'modulename' => $modulename,
            'coursecategory' => $coursecategory
        ];
        $this->curlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->uploadcurlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->uploadcurlrequest->set_option(CURLOPT_POST, 1);
        $this->curlrequest->set_option(CURLOPT_POST, 1);
        $this->uploadpostsettings = [
            'token' => $token,
            'moodlewsrestformat' => 'json'
        ];

        $this->import_categories();
        $this->import_questions();
        $this->curlrequest->close();
        $questionstodelete = $this->create_manifest_file();
        $this->delete_questions($questionstodelete);
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
        $repoiterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        // Find all the category files first and create categories where needed.
        // Categories will be dealt with before their sub-categories. Beyond that,
        // order is uncertain.
        foreach ($repoiterator as $repoitem) {
            if ($repoitem->isFile()) {
                if (pathinfo($repoitem, PATHINFO_EXTENSION) === 'xml'
                        && pathinfo($repoitem, PATHINFO_FILENAME) === cli_helper::CATEGORY_FILE) {
                    $this->postsettings['categoryname'] = '';
                    $this->upload_file($repoitem);
                    $this->curlrequest->set_option(CURLOPT_POSTFIELDS, $this->postsettings);
                    $this->curlrequest->execute();
                }
            }
        }
    }

    /**
     * Uploads a file to Moodle via cURL request to webservice
     *
     * Fileinfo parameter is set ready for import call to the webservice.
     *
     * @param $repoitem
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
        $subdirectoryiterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory . $this->subdirectory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $tempfile = fopen($this->tempfilepath, 'a+');
        $existingentries = array_column($this->manifestcontents->questions, null, 'filepath');
        // Find all the question files and import them. Order is uncertain.
        foreach ($subdirectoryiterator as $repoitem) {
            if ($repoitem->isFile()) {
                if (pathinfo($repoitem, PATHINFO_EXTENSION) === 'xml'
                        && pathinfo($repoitem, PATHINFO_FILENAME) !== cli_helper::CATEGORY_FILE) {
                    // Path of file (without filename) relative to base $directory.
                    $this->postsettings['categoryname'] = str_replace( '\\', '/',
                                $this->subdirectory . $subdirectoryiterator->getSubPath());
                    if ($this->postsettings['categoryname']) {
                        if (!$this->upload_file($repoitem)) {
                            continue;
                        };
                        $existingentry = $existingentries["{$repoitem->getPathname()}"] ?? false;
                        if ($existingentry) {
                            $this->postsettings['questionbankentryid'] = $existingentry->questionbankentryid;
                        }
                        $this->curlrequest->set_option(CURLOPT_POSTFIELDS, $this->postsettings);
                        $responsejson = json_decode($this->curlrequest->execute());
                        if (property_exists($responsejson, 'exception')) {
                            echo "{$responsejson->message}\n" .
                                 "{$responsejson->debuginfo}\n" .
                                 "{$repoitem->getPathname()} not imported.\n";
                        } else {
                            $fileoutput = [
                                'questionbankentryid' => $responsejson->questionbankentryid,
                                // Questions can be imported in multiple contexts.
                                'contextlevel' => $this->postsettings['contextlevel'],
                                'filepath' => $repoitem->getPathname(),
                                'coursename' => $this->postsettings['coursename'],
                                'modulename' => $this->postsettings['modulename'],
                                'coursecategory' => $this->postsettings['coursecategory'],
                                'format' => 'xml',
                            ];
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
     * Create manifest file from temporary file.
     *
     * @return array Contains manifest entries that no longer have a matching file.
     */
    public function create_manifest_file():array {
        // Create manifest file if it doesn't already exist.
        $manifestfile = fopen($this->manifestpath, 'a+');
        fclose($manifestfile);

        // Read in temp file a question at a time, process and add to manifest.
        // No actual processing at the moment so could simplify to write straight
        // to manifest in the first place if no processing materialises.
        $tempfile = fopen($this->tempfilepath, 'r');
        $existingentries = array_column($this->manifestcontents->questions, null, 'questionbankentryid');
        if (!$this->manifestcontents) {
            $this->manifestcontents = new \stdClass();
            $this->manifestcontents->context = null;
            $this->manifestcontents->questions = [];
        }
        while (!feof($tempfile)) {
            $questioninfo = json_decode(fgets($tempfile));
            if ($questioninfo) {
                $existingentry = $existingentries["{$questioninfo->questionbankentryid}"] ?? false;
                if (!$existingentry) {
                    array_push($this->manifestcontents->questions,
                            [
                                'questionbankentryid' => $questioninfo->questionbankentryid,
                                'filepath' => $questioninfo->filepath,
                                'format' => $questioninfo->format
                            ]);
                } else {
                    unset($existingentries["{$questioninfo->questionbankentryid}"]);
                }
            }
            if ($this->manifestcontents->context === null) {
                $this->manifestcontents->context = new stdClass();
                $this->manifestcontents->context->contextlevel = $questioninfo->contextlevel;
                $this->manifestcontents->context->coursename = $questioninfo->coursename;
                $this->manifestcontents->context->modulename = $questioninfo->modulename;
                $this->manifestcontents->context->coursecategory = $questioninfo->coursecategory;
            }
        }
        file_put_contents($this->manifestpath, json_encode($this->manifestcontents));

        fclose($tempfile);
        unlink($this->tempfilepath);
        $existingentries = array_filter($existingentries, function($value) {
            $subdirectorypath = $this->directory . $this->subdirectory;
            return (substr($value->filepath, 0, strlen($subdirectorypath)) === $subdirectorypath);
        });
        return $existingentries;
    }

    public function delete_questions(array $questionstodelete):void {
        echo "\nThese questions are listed in the manifest but there is no longer a matching file:\n";
        foreach ($questionstodelete as $question) {
            echo $question->filepath . "\n";
        }
        foreach ($questionstodelete as $question) {
            echo "\nDelete {$question->filepath} from Moodle? y/n\n";
            $handle = fopen ("php://stdin", "r");
            $line = fgets($handle);
            if (trim($line) === 'y') {
                // TO-DO Need a call to a delete webservice function here.
                echo "Deleted\n";
            } else {
                echo "Not deleted\n";
            }
            fclose($handle);
        }
    }
}
