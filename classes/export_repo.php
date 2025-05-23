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
 * Utilised in cli\exportrepofrommoodle.php
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
    use tidy_trait;
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
     * @var string
     */
    public string $manifestpath;
    /**
     * Full path to manifest file
     *
     * @var string|null
     */
    public ?string $nonquizmanifestpath;
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
     * URL of Moodle instance
     *
     * @var string
     */
    public string $moodleurl;
    /**
     * Relative path of subcategory to export.
     *
     * @var string
     */
    public string $subcategory;
    /**
     * Directory to export into.
     *
     * @var string|null
     */
    public ?string $targetdirectory = null;
    /**
     * Regex of categories to ignore.
     *
     * @var string|null
     */
    public ?string $ignorecat;
    /**
     * Are we using git?.
     * Set in config. Adds commit hash to manifest.
     * @var bool
     */
    public bool $usegit;

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
        $this->moodleurl = $moodleinstances[$moodleinstance];
        $this->usegit = $arguments['usegit'];
        $defaultwarning = false;
        if ($arguments['manifestpath']) {
            $this->manifestpath = ($arguments['rootdirectory']) ? $arguments['rootdirectory'] . '/' . $arguments['manifestpath'] :
                                                            $arguments['manifestpath'];
        } else {
            $this->manifestpath = null;
        }
        if (!empty($arguments['nonquizmanifestpath'])) {
            $this->nonquizmanifestpath = ($arguments['rootdirectory']) ?
                        $arguments['rootdirectory'] . '/' . $arguments['nonquizmanifestpath'] : $arguments['nonquizmanifestpath'];
        } else {
            $this->nonquizmanifestpath = null;
        }
        if (is_array($arguments['token'])) {
            $token = $arguments['token'][$moodleinstance];
        } else {
            $token = $arguments['token'];
        }
        $this->manifestcontents = json_decode(file_get_contents($this->manifestpath));
        if (!$this->manifestcontents) {
            echo "\nUnable to access or parse manifest file: {$this->manifestpath}\nAborting.\n";
            $this->call_exit();
        }
        if ($this->manifestcontents->context->moodleurl !== $this->moodleurl) {
            echo "\nManifest file is for the wrong Moodle instance: {$this->manifestcontents}\nAborting.\n";
            $this->call_exit();
        }
        if (!empty($this->manifestcontents->context->istargeted)) {
            $this->targetdirectory = $this->manifestcontents->context->defaultsubdirectory;
            if ($arguments['subcategory'] || $arguments['qcategoryid']) {
                echo "\nThe manifest file was created using targeting. The question category cannot be overridden.\nAborting.\n";
                $this->call_exit();
            }
        }

        if ($arguments['subcategory']) {
            $this->subcategory = $arguments['subcategory'];
            $qcategoryid = null;
        } else {
            if ($arguments['qcategoryid']) {
                $qcategoryid = $arguments['qcategoryid'];
            } else {
                $qcategoryid = $this->manifestcontents->context->defaultsubcategoryid;
                $defaultwarning = true;
            }
            // Subcategory will be properly set later.
            $this->subcategory = 'top';
        }
        if ($arguments['ignorecat']) {
            $this->ignorecat = $arguments['ignorecat'];
        } else {
            $this->ignorecat = $this->manifestcontents->context->defaultignorecat ?? null;
        }

        $this->tempfilepath = str_replace(cli_helper::MANIFEST_FILE,
                                          '_export' . cli_helper::TEMP_MANIFEST_FILE,
                                          $this->manifestpath);
        $wsurl = $this->moodleurl . '/webservice/rest/server.php';

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
            'qcategoryname' => $this->subcategory,
            'qcategoryid' => $qcategoryid,
            'instanceid' => $this->manifestcontents->context->instanceid,
            'contextonly' => 0,
            'qbankentryids[]' => null,
            'ignorecat' => $this->ignorecat,
            'localversion' => cli_helper::GITSYNC_VERSION,
        ];
        $this->listcurlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->listcurlrequest->set_option(CURLOPT_POST, 1);
        $this->listcurlrequest->set_option(CURLOPT_POSTFIELDS, $this->listpostsettings);
        $moodlequestionlist = $clihelper->check_context($this, $defaultwarning, false);
        $this->subcategory = $moodlequestionlist->contextinfo->qcategoryname;
    }

    /**
     * Iterate through the manifest file, request up to date versions via
     * the webservice and update local files.
     *
     * @return void
     */
    public function process(): void {
        // Export latest versions of questions in manifest from Moodle.
        $this->export_questions_in_manifest();
        // Export any questions that are in Moodle but not in the manifest.
        $this->export_to_repo();
        cli_helper::create_manifest_file($this->manifestcontents, $this->tempfilepath,
                                         $this->manifestpath, false);
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
    public function get_curl_request($wsurl): curl_request {
        return new \qbank_gitsync\curl_request($wsurl);
    }

    /**
     * Loop through questions in manifest file, export each from Moodle and update local copy
     *
     * @return void
     */
    public function export_questions_in_manifest() {
        $categorynames = [];
        $topdirectory = dirname($this->manifestpath);
        $count = 0;
        foreach ($this->manifestcontents->questions as $questioninfo) {
            $currentdirectory = dirname($topdirectory . $questioninfo->filepath);
            if (isset($categorynames[$currentdirectory])) {
                $qcategoryname = $categorynames[$currentdirectory];
            } else if ($topdirectory . '/top' === $currentdirectory) {
                $qcategoryname = 'top';
                $categorynames[$currentdirectory] = $qcategoryname;
            } else {
                $categoryfile = $currentdirectory. '/' . cli_helper::CATEGORY_FILE . '.xml';
                $qcategoryname = cli_helper::get_question_category_from_file($categoryfile);
                $categorynames[$currentdirectory] = $qcategoryname;
            }
            if (!$qcategoryname) {
                echo "Problem with the category file or file location.\n" .
                     "{$questioninfo->filepath} not exported.\n";
                continue;
            }
            if (!$this->targetdirectory) {
                // If this is a targeted link, export is all or nothing. We're using the subcategory
                // for targeting. Essentially, the user can't select a sub subcategory.
                if (substr($qcategoryname, 0, strlen($this->subcategory)) !== $this->subcategory) {
                    // Start of category path of question must match start of subcategory to export.
                    continue;
                }
                if (strlen($qcategoryname) > strlen($this->subcategory)
                        && !preg_match('/^\/{1}(?!\/)/' , substr($qcategoryname, strlen($this->subcategory)))) {
                    // Category path and subcategory must either match or path must be longer and continue with
                    // one (and only) one slash i.e. for subcategory parameter of top/cat, a question
                    // in top/cat/subcat is fine but one in top/cat2 is not and nor is top/cat//one.
                    continue;
                }
            }
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
                try {
                    $question = cli_helper::reformat_question($responsejson->question);
                } catch (\Exception $e) {
                    echo "\n{$e->getmessage()}\n";
                    echo "{$questioninfo->filepath} not updated.\n";
                    continue;
                }
                $success = file_put_contents(dirname($this->manifestpath) . $questioninfo->filepath, $question);
                if ($success === false) {
                    echo "\nAccess issue.\n";
                    echo "{$questioninfo->filepath} not updated.\n";
                } else {
                    $count++;
                    $questioninfo->exportedversion = $responsejson->version;
                }
            }
        }
        echo "\nExported {$count} previously linked question" . (($count !== 1) ? 's' : '') . ".\n";
        if ($count > 0) {
            echo "(Check your repository to see which questions have changes.)\n";
        }
        // Will not be updated properly if there is an error but this is no loss.
        // Process can simply be run again from start.
        $success = file_put_contents($this->manifestpath, json_encode($this->manifestcontents));
        if ($success === false) {
            echo "\nUnable to update manifest file: {$this->manifestpath}\n Aborting.\n";
            $this->call_exit();
        }
    }

    /**
     * Create/update quiz directories and populate.
     * @param object $clihelper
     * @param string $scriptdirectory - directory of CLI scripts
     * @return void
     */
    public function update_quiz_directories($clihelper, $scriptdirectory) {
        $arguments = $clihelper->get_arguments();
        $contextinfo = $clihelper->check_context($this, false, true);
        $basedirectory = dirname($this->manifestpath);
        $moodleinstance = $arguments['moodleinstance'];
        if (is_array($arguments['token'])) {
            $token = $arguments['token'][$moodleinstance];
        } else {
            $token = $arguments['token'];
        }
        $ignorecat = $arguments['ignorecat'];
        $ignorecat = ($ignorecat) ? ' -x "' . $ignorecat . '"' : '';
        $quizlocations = isset($this->manifestcontents->quizzes) ? $this->manifestcontents->quizzes : [];
        $locarray = array_column($quizlocations, null, 'moduleid');
        foreach ($contextinfo->quizzes as $quiz) {
            $instanceid = (int) $quiz->instanceid;
            if (!isset($locarray[$instanceid])) {
                $rootdirectory = $clihelper->create_directory(cli_helper::get_quiz_directory($basedirectory, $quiz->name));
                if (!isset($locarray[$instanceid])) {
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
                }
                echo "\nExporting quiz: {$quiz->name} to {$rootdirectory}\n";
                $output = $this->call_repo_creation($rootdirectory, $moodleinstance,
                                                    $instanceid, $token, $ignorecat, $scriptdirectory);
            } else if (!is_dir(dirname($basedirectory) . '/' . $locarray[$instanceid]->directory)) {
                $rootdirectory = dirname($basedirectory) . '/' . $locarray[$instanceid]->directory;
                mkdir($rootdirectory);
                mkdir($rootdirectory . '/top');
                echo "\nExporting quiz: {$quiz->name} to {$rootdirectory}\n";
                $output = $this->call_repo_creation($rootdirectory, $moodleinstance,
                                                    $instanceid, $token, $ignorecat, $scriptdirectory);
            } else {
                $rootdirectory = dirname($basedirectory) . '/' . $locarray[$instanceid]->directory;
                echo "\nExporting quiz: {$quiz->name} to {$rootdirectory}\n";
                $quizmanifestname = cli_helper::get_manifest_path($moodleinstance, 'module', null,
                                    $contextinfo->contextinfo->coursename, $quiz->name, '');
                $output = $this->call_export_repo($rootdirectory, $moodleinstance, $token,
                                    $quizmanifestname, $ignorecat, $scriptdirectory);
            }
            echo $output;
            $quizmanifestpath = cli_helper::get_manifest_path($moodleinstance, 'module', null,
                                    $contextinfo->contextinfo->coursename, $quiz->name, $rootdirectory);
            $output = $this->call_export_quiz($moodleinstance, $token, $quizmanifestpath, $this->manifestpath, $scriptdirectory);
            echo $output;
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
     * @return string|null
     */
    public function call_repo_creation(string $rootdirectory, string $moodleinstance, string $instanceid,
                                       string $token, string $ignorecat, string $scriptdirectory
                                      ): ?string {
        chdir($scriptdirectory);
        $usegit = ($this->usegit) ? 'true' : 'false';
        return shell_exec('php createrepo.php -u ' . $usegit . ' -w -r "' . $rootdirectory .  '" -i "' . $moodleinstance .
                '" -l "module" -n ' . $instanceid . ' -t ' . $token . $ignorecat);
    }

    /**
     * Separate out exec call for mocking.
     *
     * @param string $rootdirectory
     * @param string $moodleinstance
     * @param string $token
     * @param string $quizmanifestname
     * @param string $ignorecat
     * @param string $scriptdirectory
     * @return string|null
     */
    public function call_export_repo(string $rootdirectory, string $moodleinstance, string $token,
                string $quizmanifestname, string $ignorecat, string $scriptdirectory): ?string {
        chdir($scriptdirectory);
        $usegit = ($this->usegit) ? 'true' : 'false';
        return shell_exec('php exportrepofrommoodle.php -u ' . $usegit . ' -w -r "' . $rootdirectory . '" -i "' .
                            $moodleinstance . '" -f "' . $quizmanifestname . '" -t ' . $token . $ignorecat);
    }
}
