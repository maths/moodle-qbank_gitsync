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

/**
 * Import a Git repo.
 */
class import_repo {
    /**
     * File system iterator
     *
     * @var \RecursiveIteratorIterator
     */
    public \RecursiveIteratorIterator $repoiterator;
    /**
     * Settings for POST request
     *
     * These are the parameters for the webservice call.
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
    public string $manifestpath;
    /**
     * CATEGORY_FILE - Name of file containing category information in each directory and subdirectory.
     */
    public const CATEGORY_FILE = 'gitsync_category';
    /**
     * MANIFEST_FILE - File name ending for manifest file.
     * Appended to name of moodle instance.
     */
    public const MANIFEST_FILE = '_question_manifest.json';

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
        $directory = $arguments['directory'];
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
        $this->manifestpath = $directory . '/' . $moodleinstance . self::MANIFEST_FILE;
        $this->tempfilepath = $directory . '/' . $moodleinstance . '_manifest_update.tmp';

        $this->repoiterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

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
        $this->create_manifest_file();
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
    public function import_categories() {
        // Find all the category files first and create categories where needed.
        // Categories will be dealt with before their sub-categories. Beyond that,
        // order is uncertain.
        foreach ($this->repoiterator as $repoitem) {
            if ($repoitem->isFile()) {
                if (pathinfo($repoitem, PATHINFO_EXTENSION) === 'xml'
                        && pathinfo($repoitem, PATHINFO_FILENAME) === self::CATEGORY_FILE) {
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
        $tempfile = fopen($this->tempfilepath, 'a+');
        // Find all the question files and import them. Order is uncertain.
        foreach ($this->repoiterator as $repoitem) {
            if ($repoitem->isFile()) {
                if (pathinfo($repoitem, PATHINFO_EXTENSION) === 'xml'
                        && pathinfo($repoitem, PATHINFO_FILENAME) !== self::CATEGORY_FILE) {
                    // Path of file (without filename) relative to base $directory.
                    $this->postsettings['categoryname'] = str_replace( '\\', '/', $this->repoiterator->getSubPath());
                    if ($this->postsettings['categoryname']) {
                        if (!$this->upload_file($repoitem)) {
                            continue;
                        };
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
     * @return resource manifest file. Contains all questions (not just from this run) as
     * a single JSON array.
     */
    public function create_manifest_file() {
        // Create manifest file if it doesn't already exist.
        $manifestfile = fopen($this->manifestpath, 'a+');
        fclose($manifestfile);

        // Read in temp file a question at a time, process and add to manifest.
        // No actual processing at the moment so could simplify to write straight
        // to manifest in the first place if no processing materialises.
        $tempfile = fopen($this->tempfilepath, 'r');
        $manifestcontents = json_decode(file_get_contents($this->manifestpath));
        if (!$manifestcontents) {
            $manifestcontents = [];
        }
        while (!feof($tempfile)) {
            $questioninfo = json_decode(fgets($tempfile));
            if ($questioninfo) {
                array_push($manifestcontents, $questioninfo);
            }
        }
        file_put_contents($this->manifestpath, json_encode($manifestcontents));

        fclose($tempfile);
        unlink($this->tempfilepath);
        return $manifestfile;
    }
}
