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
     * Relative path of subcategory to import.
     *
     * @var string
     */
    public string $subcategory;
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
     * Parsed content of JSON manifest file
     *
     * @var \stdClass|null
     */
    public ?\stdClass $manifestcontents;
    /**
     * URL of Moodle instance
     *
     * @var string
     */
    public string $moodleurl;

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
        if ($arguments['directory']) {
            $this->directory = $arguments['rootdirectory'] . '/' . $arguments['directory'];
        } else {
            $this->directory = $arguments['rootdirectory'];
        }
        $this->subcategory = $arguments['subcategory'];
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

        $this->moodleurl = $moodleinstances[$moodleinstance];

        $wsurl = $this->moodleurl . '/webservice/rest/server.php';

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
            'qcategoryname' => $this->subcategory,
            'qcategoryid' => $qcategoryid,
            'instanceid' => $instanceid,
            'contextonly' => 0,
            'qbankentryids[]' => null,
        ];
        $this->listcurlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->listcurlrequest->set_option(CURLOPT_POST, 1);
        $this->listcurlrequest->set_option(CURLOPT_POSTFIELDS, $this->listpostsettings);
        $instanceinfo = $clihelper->check_context($this);

        $this->listpostsettings['contextlevel'] =
                cli_helper::get_context_level($instanceinfo->contextinfo->contextlevel);
        $this->listpostsettings['coursecategory'] = $instanceinfo->contextinfo->categoryname;
        $this->listpostsettings['coursename'] = $instanceinfo->contextinfo->coursename;
        $this->listpostsettings['modulename'] = $instanceinfo->contextinfo->modulename;
        $this->listpostsettings['instanceid'] = $instanceinfo->contextinfo->instanceid;
        $this->listcurlrequest->set_option(CURLOPT_POSTFIELDS, $this->listpostsettings);

        $this->manifestpath = cli_helper::get_manifest_path($moodleinstance, $contextlevel,
                                                $instanceinfo->contextinfo->categoryname,
                                                $instanceinfo->contextinfo->coursename,
                                                $instanceinfo->contextinfo->modulename, $this->directory);
        if (file_exists($this->directory . '/top')) {
            echo 'The specified directory already contains files. Please delete them if you really want to continue.';
            echo "\n{$this->directory}\n";
            $this->call_exit();
        }
        $this->tempfilepath = str_replace(cli_helper::MANIFEST_FILE,
                                          '_export' . cli_helper::TEMP_MANIFEST_FILE,
                                          $this->manifestpath);
    }

    /**
     * Obtain a list of questions and categories from Moodle, iterate through them and
     * export them one at a time. Create repo directories and files.
     *
     * @return void
     */
    public function process():void {
        $this->manifestcontents = new \stdClass();
        $this->manifestcontents->context = null;
        $this->manifestcontents->questions = [];
        $this->export_to_repo();
        cli_helper::create_manifest_file($this->manifestcontents, $this->tempfilepath, $this->manifestpath, $this->moodleurl);
        unlink($this->tempfilepath);
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
}
