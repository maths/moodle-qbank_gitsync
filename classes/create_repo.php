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
     * Settings for POST request.
     *
     * These are the parameters for the webservice call.
     *
     * @var array
     */
    public array $postsettings;
    /**
     * Settings for list POST request.
     *
     * These are the parameters for the webservice call.
     *
     * @var array
     */
    public array $listpostsettings;
    /**
     * cURL request handle for file upload.
     *
     * @var curl_request
     */
    public curl_request $curlrequest;
    /**
     * cURL request handle for question list retrieve.
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
     * Moodle id of question category.
     *
     * @var int|null
     */
    public ?int $qcategoryid = null;
    /**
     * Regex of categories to ignore.
     *
     * @var string|null
     */
    public ?string $ignorecat;
    /**
     * Path to actual manifest file.
     *
     * @var string
     */
    public string $manifestpath;
    /**
     * Path to actual manifest file.
     *
     * @var string|null
     */
    public ?string $nonquizmanifestpath;
    /**
     * Path to temporary manifest file.
     *
     * @var string
     */
    public string $tempfilepath;
    /**
     * Path of root of repo
     * i.e. folder containing manifest.
     *
     * @var string
     */
    public string $directory;
    /**
     * Parsed content of JSON manifest file.
     *
     * @var \stdClass|null
     */
    public ?\stdClass $manifestcontents;
    /**
     * URL of Moodle instance.
     *
     * @var string
     */
    public string $moodleurl;
    /**
     * Are we using git?.
     * Set in config. Adds commit hash to manifest.
     * @var bool
     */
    public bool $usegit;
    /**
     * Directory to export into. Will always be null or top.
     *
     * @var string|null
     */
    public ?string $targetdirectory = null;
    /**
     * Id of subcategory to export into.
     *
     * @var int|null
     */
    public ?int $targetcategory;

    /**
     * Constructor.
     *
     * @param cli_helper $clihelper
     * @param array $moodleinstances pairs of names and URLs
     */
    public function __construct(cli_helper $clihelper, array $moodleinstances) {
        // Convert command line options into variables.
        // (Moodle code rules don't allow 'extract()').
        $arguments = $clihelper->get_arguments();
        $moodleinstance = $arguments['moodleinstance'];
        $this->usegit = $arguments['usegit'];
        if ($arguments['directory']) {
            $this->directory = ($arguments['rootdirectory']) ?
                    $arguments['rootdirectory'] . '/' . $arguments['directory'] : $arguments['directory'];
        } else {
            $this->directory = $arguments['rootdirectory'];
        }
        if (!empty($arguments['nonquizmanifestpath'])) {
            $this->nonquizmanifestpath = ($arguments['rootdirectory']) ?
                    $arguments['rootdirectory'] . '/' . $arguments['nonquizmanifestpath'] : $arguments['nonquizmanifestpath'];
        } else {
            $this->nonquizmanifestpath = null;
        }
        $this->subcategory = ($arguments['subcategory']) ? $arguments['subcategory'] : 'top';
        if (is_array($arguments['token'])) {
            $token = $arguments['token'][$moodleinstance];
        } else {
            $token = $arguments['token'];
        }

        if (!empty($arguments['istargeted'])) {
            $this->targetdirectory = 'top';
        }
        $contextlevel = $arguments['contextlevel'];
        $coursename = $arguments['coursename'];
        $modulename = (isset($arguments['modulename'])) ? $arguments['modulename'] : null;
        $coursecategory = (isset($arguments['coursecategory'])) ? $arguments['coursecategory'] : null;
        $qcategoryid = $arguments['qcategoryid'];
        $instanceid = $arguments['instanceid'];
        $this->ignorecat = $arguments['ignorecat'];

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
            'ignorecat' => $this->ignorecat,
            'localversion' => cli_helper::GITSYNC_VERSION,
        ];
        $this->listcurlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->listcurlrequest->set_option(CURLOPT_POST, 1);
        $this->listcurlrequest->set_option(CURLOPT_POSTFIELDS, $this->listpostsettings);
        $instanceinfo = $clihelper->check_context($this, false, false);
        $this->subcategory = $instanceinfo->contextinfo->qcategoryname;

        $this->qcategoryid = $instanceinfo->contextinfo->qcategoryid;

        $this->listpostsettings['contextlevel'] =
                cli_helper::get_context_level($instanceinfo->contextinfo->contextlevel);
        $this->listpostsettings['coursecategory'] = $instanceinfo->contextinfo->categoryname;
        $this->listpostsettings['coursename'] = $instanceinfo->contextinfo->coursename;
        $this->listpostsettings['modulename'] = $instanceinfo->contextinfo->modulename;
        $this->listpostsettings['instanceid'] = $instanceinfo->contextinfo->instanceid;
        $this->listcurlrequest->set_option(CURLOPT_POSTFIELDS, $this->listpostsettings);
        if (!empty($arguments['istargeted'])) {
            $this->targetcategory = $this->qcategoryid;
            $this->manifestpath = cli_helper::get_manifest_path_targeted($moodleinstance,
                                                $instanceinfo->contextinfo->qcategoryname,
                                                $instanceinfo->contextinfo->qcategoryid,
                                                $this->directory);
        } else {
            $this->targetcategory = null;
            $this->manifestpath = cli_helper::get_manifest_path($moodleinstance, $contextlevel,
                                                $instanceinfo->contextinfo->categoryname,
                                                $instanceinfo->contextinfo->coursename,
                                                $instanceinfo->contextinfo->modulename,
                                                $this->directory);
        }
        if (file_exists($this->directory . '/top')) {
            echo 'The specified directory already contains files. Please delete them if you really want to continue.';
            echo "\n{$this->directory}\n";
            $this->call_exit();
        }
        $this->tempfilepath = str_replace(cli_helper::MANIFEST_FILE,
                                          '_export' . cli_helper::TEMP_MANIFEST_FILE,
                                          $this->manifestpath);
        $this->manifestcontents = new \stdClass();
        $this->manifestcontents->context = new \stdClass();
        $this->manifestcontents->context->contextlevel = cli_helper::get_context_level($instanceinfo->contextinfo->contextlevel);
        $this->manifestcontents->context->coursename = $instanceinfo->contextinfo->coursename;
        $this->manifestcontents->context->modulename = $instanceinfo->contextinfo->modulename;
        $this->manifestcontents->context->coursecategory = $instanceinfo->contextinfo->categoryname;
        $this->manifestcontents->context->instanceid = $instanceinfo->contextinfo->instanceid;
        $this->manifestcontents->context->istargeted = ($this->targetcategory) ? true : false;
        $this->manifestcontents->context->defaultsubcategoryid = $this->qcategoryid;
        $this->manifestcontents->context->defaultsubdirectory = null;
        $this->manifestcontents->context->defaultignorecat = $this->ignorecat;
        $this->manifestcontents->context->moodleurl = $this->moodleurl;
        $this->manifestcontents->questions = [];
    }

    /**
     * Obtain a list of questions and categories from Moodle, iterate through them and
     * export them one at a time. Create repo directories and files.
     *
     * @return void
     */
    public function process(): void {
        $this->export_to_repo();
        $this->manifestcontents->context->defaultsubdirectory = $this->subdirectory;
        cli_helper::create_manifest_file($this->manifestcontents, $this->tempfilepath,
                                         $this->manifestpath, false);
        unlink($this->tempfilepath);
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
     * Create quiz directories and populate.
     * @param object $clihelper
     * @param string $scriptdirectory - directory of CLI scripts
     * @return void
     */
    public function create_quiz_directories(object $clihelper, string $scriptdirectory): void {
        $contextinfo = $clihelper->check_context($this, false, true);
        $arguments = $clihelper->get_arguments();
        if ($arguments['directory']) {
            $basedirectory = $arguments['rootdirectory'] . '/' . $arguments['directory'];
        } else {
            $basedirectory = $arguments['rootdirectory'];
        }
        $moodleinstance = $arguments['moodleinstance'];
        $instanceid = $arguments['instanceid'];
        if (is_array($arguments['token'])) {
            $token = $arguments['token'][$moodleinstance];
        } else {
            $token = $arguments['token'];
        }
        $ignorecat = $arguments['ignorecat'];
        $ignorecat = ($ignorecat) ? ' -x "' . $ignorecat . '"' : '';
        $quizlocations = [];
        foreach ($contextinfo->quizzes as $quiz) {
            $instanceid = $quiz->instanceid;
            $quizdirectory = cli_helper::get_quiz_directory($basedirectory, $quiz->name);
            $rootdirectory = $clihelper->create_directory($quizdirectory);
            echo "\nExporting quiz: {$quiz->name} to {$rootdirectory}\n";
            $output = $this->call_repo_creation($rootdirectory, $moodleinstance, $instanceid, $token, $ignorecat, $scriptdirectory);
            echo $output;
            $quizmanifestpath = cli_helper::get_manifest_path($moodleinstance, 'module', null,
                                    $contextinfo->contextinfo->coursename, $quiz->name, $rootdirectory);
            $output = $this->call_export_quiz($moodleinstance, $token, $quizmanifestpath,
                                                $this->manifestpath, $scriptdirectory);
            $quizlocation = new \StdClass();
            $quizlocation->moduleid = $instanceid;
            $quizlocation->directory = basename($rootdirectory);
            $quizlocations[] = $quizlocation;
            $this->manifestcontents->quizzes = $quizlocations;
            $success = file_put_contents($this->manifestpath, json_encode($this->manifestcontents));
            if ($success === false) {
                echo "\nUnable to update manifest file: {$this->manifestpath}\n Aborting.\n";
                exit();
            }
            echo $output;
        }
        if ($arguments['usegit'] && empty($arguments['subcall'])) {
            // Commit the final quiz file.
            // The others are committed by the following createrepo.
            chdir($basedirectory);
            exec("git add --all");
            exec('git commit -m "Initial Commit - final update"');
        }
    }

    /**
     * Separate out exec call for mocking.
     *
     * @param string $rootdirectory
     * @param string $moodleinstance
     * @param string $instanceid
     * @param string $token
     * @param string $ignorecat
     * @param string $scriptdirectory
     * @return string|null
     */
    public function call_repo_creation(string $rootdirectory, string $moodleinstance, string $instanceid,
                                       string $token, string $ignorecat, string $scriptdirectory
                                      ): ?string {
        chdir($scriptdirectory);
        $usegit = ($this->usegit) ? 'true' : 'false';
        return shell_exec('php createrepo.php -u ' . $usegit . ' -w -r "' .
                $rootdirectory .  '" -i "' . $moodleinstance .
                '" -l "module" -n ' . $instanceid . ' -t ' . $token . $ignorecat);
    }
}
