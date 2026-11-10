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
 * Utilised in cli\importrepotomoodle.php
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
    use tidy_trait;

    /**
     * Largest total size of question XML sent in one import request with several questions.
     * Half of PHP's default post_max_size (8M). A larger question is sent on its own.
     */
    public const MAX_BATCH_BYTES = 4194304;

    /**
     * CLI helper for this import
     *
     * @var cli_helper
     */
    public cli_helper $clihelper;

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
     * Path to defaults file.
     *
     * @var string
     */
    public string $defaultsfilepath;
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
     * Id of subcategory to import into.
     *
     * @var int|null
     */
    public ?int $targetcategory;
    /**
     * Category path of subcategory to import into.
     *
     * @var string|null
     */
    public ?string $targetcategoryname;
    /**
     * Category path of targeted subdirectory.
     *
     * @var string|null
     */
    public ?string $basecategoryname;
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
     * Parsed content of JSON manifest file
     *
     * @var \stdClass|null
     */
    public ?\stdClass $manifestcontents;
    /**
     * Parsed content of YAML defaults file.
     *
     * @var array|null
     */
    public ?array $defaults;
    /**
     * Are we using YAML?.
     * Set in config. Saves questions as YAML.
     * @var bool
     */
    public bool $useyaml;
    /**
     * Are we using fragments?
     * Set in config. Saves questions as difference file and adds default file to repo.
     * @var bool
     */
    public bool $usefragments;
    /**
     * Commit all questions?
     * @var bool
     */
    public bool $forceimport;
    /**
     * Number of questions to import per request.
     * @var int
     */
    public int $batchsize;

    /**
     * Constructor
     *
     * @param cli_helper $clihelper
     * @param array $moodleinstances pairs of names and URLs
     */
    public function __construct(cli_helper $clihelper, array $moodleinstances) {
        // Convert command line options into variables.
        // (Moodle code rules don't allow 'extract()').
        $this->clihelper = $clihelper;
        $arguments = $clihelper->get_arguments();
        $moodleinstance = $arguments['moodleinstance'];
        $manifestpath = $arguments['manifestpath'];
        $this->forceimport = $arguments['forceimport'] ?? false;
        $this->batchsize = max(1, (int) ($arguments['batchsize'] ?? 1));
        if ($arguments['directory']) {
            $this->directory = ($arguments['rootdirectory']) ? $arguments['rootdirectory'] . '/' . $arguments['directory'] :
                                            $arguments['directory'];
        } else {
            if ($manifestpath && dirname($manifestpath) !== '.') {
                $this->directory = ($arguments['rootdirectory']) ? $arguments['rootdirectory'] . '/' . dirname($manifestpath) :
                                        dirname($manifestpath);
            } else {
                $this->directory = $arguments['rootdirectory'];
            }
        }
        if (is_array($arguments['token'])) {
            $token = $arguments['token'][$moodleinstance];
        } else {
            $token = $arguments['token'];
        }
        $contextlevel = $arguments['contextlevel'];
        $coursename = $arguments['coursename'];
        $modulename = $arguments['modulename'];
        $coursecategory = $arguments['coursecategory'];
        $this->targetcategory = $arguments['targetcategory'];
        $this->targetcategoryname = $arguments['targetcategoryname'];
        $instanceid = $arguments['instanceid'];
        $this->ignorecat = $arguments['ignorecat'];
        $this->usegit = $arguments['usegit'];
        $this->useyaml = isset($arguments['useyaml']) ? $arguments['useyaml'] : false;
        $this->usefragments = isset($arguments['usefragments']) ? $arguments['usefragments'] : false;

        $this->moodleurl = $moodleinstances[$moodleinstance];
        $wsurl = $this->moodleurl . '/webservice/rest/server.php';

        $this->curlrequest = $this->get_curl_request($wsurl);
        $this->deletecurlrequest = $this->get_curl_request($wsurl);
        $this->listcurlrequest = $this->get_curl_request($wsurl);

        $this->postsettings = [
            'wstoken' => $token,
            'wsfunction' => 'qbank_gitsync_import_questions',
            'moodlewsrestformat' => 'json',
            'questionbankentryid' => null,
            'importedversion' => null,
            'exportedversion' => null,
            'contextlevel' => ($contextlevel) ? cli_helper::get_context_level($contextlevel) : null,
            'coursename' => $coursename,
            'modulename' => $modulename,
            'coursecategory' => $coursecategory,
            'instanceid' => $instanceid,
            'qcategoryid' => null,
        ];
        $this->curlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->curlrequest->set_option(CURLOPT_POST, 1);
        $this->deletepostsettings = [
            'wstoken' => $token,
            'wsfunction' => 'qbank_gitsync_delete_question',
            'moodlewsrestformat' => 'json',
            'questionbankentryid' => null,
        ];
        $this->deletecurlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->deletecurlrequest->set_option(CURLOPT_POST, 1);
        $this->listpostsettings = [
            'wstoken' => $token,
            'wsfunction' => 'qbank_gitsync_get_question_list',
            'moodlewsrestformat' => 'json',
            'contextlevel' => ($contextlevel) ? cli_helper::get_context_level($contextlevel) : null,
            'coursename' => $coursename,
            'modulename' => $modulename,
            'coursecategory' => $coursecategory,
            'qcategoryname' => ($this->targetcategoryname) ? $this->targetcategoryname : 'top',
            'qcategoryid' => $this->targetcategory,
            'instanceid' => $instanceid,
            'contextonly' => 0,
            'qbankentryids[]' => null,
            'ignorecat' => $this->ignorecat,
            'localversion' => cli_helper::GITSYNC_VERSION,
        ];
        $this->listcurlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->listcurlrequest->set_option(CURLOPT_POST, 1);

        if ($manifestpath) {
            $this->manifestpath = ($arguments['rootdirectory']) ? $arguments['rootdirectory'] . '/' . $manifestpath :
                                                        $manifestpath;
        } else {
            $this->subdirectory = ($arguments['subdirectory']) ? $arguments['subdirectory'] : 'top';
            $instanceinfo = $this->clihelper->check_context($this, false, false);
            if ($this->targetcategory) {
                $this->manifestpath = cli_helper::get_manifest_path_targeted(
                    $moodleinstance,
                    $contextlevel,
                    $instanceinfo->contextinfo->categoryname,
                    $instanceinfo->contextinfo->coursename,
                    $instanceinfo->contextinfo->modulename,
                    $instanceinfo->contextinfo->qcategoryname,
                    $instanceinfo->contextinfo->qcategoryid,
                    $this->subdirectory,
                    $this->directory
                );
                $this->targetcategoryname = $instanceinfo->contextinfo->qcategoryname;
                $this->targetcategory = $instanceinfo->contextinfo->qcategoryid;
            } else {
                $this->manifestpath = cli_helper::get_manifest_path(
                    $moodleinstance,
                    $contextlevel,
                    $instanceinfo->contextinfo->categoryname,
                    $instanceinfo->contextinfo->coursename,
                    $instanceinfo->contextinfo->modulename,
                    $this->directory
                );
            }
            $this->postsettings['instanceid'] = $instanceinfo->contextinfo->instanceid;
            $this->postsettings['coursename'] = $instanceinfo->contextinfo->coursename;
            $this->postsettings['modulename'] = $instanceinfo->contextinfo->modulename;
            $this->postsettings['coursecategory'] = $instanceinfo->contextinfo->categoryname;
            $this->listpostsettings['instanceid'] = $instanceinfo->contextinfo->instanceid;
            $this->listpostsettings['coursename'] = $instanceinfo->contextinfo->coursename;
            $this->listpostsettings['modulename'] = $instanceinfo->contextinfo->modulename;
            $this->listpostsettings['ignorecat'] = $this->ignorecat;
        }
        $this->tempfilepath = str_replace(
            cli_helper::MANIFEST_FILE,
            '_import' . cli_helper::TEMP_MANIFEST_FILE,
            $this->manifestpath
        );
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

        if ($this->usefragments) {
            $defaultsfilename = cli_helper::DEFAULTS_FILE . ($this->useyaml ? 'yml' : 'xml');
            if ($arguments['defaultfile']) {
                $this->defaultsfilepath = $this->directory . '/' . $arguments['defaultfile'];
            } else if (!empty($manifestcontents->context->defaultdefaults)) {
                $this->defaultsfilepath = $this->directory . '/' . $manifestcontents->context->defaultdefaults;
            } else if (is_file(dirname($this->manifestpath) . '/' . $defaultsfilename)) {
                $this->defaultsfilepath = $this->directory . '/' . $defaultsfilename;
            } else {
                $this->defaultsfilepath = $this->directory . '/' . $defaultsfilename;
                copy($this->directory . '/../' . $defaultsfilename, $this->defaultsfilepath);
            }
            $this->defaults = yaml_converter::load_defaults($this->defaultsfilepath, $this->useyaml);
        }
        if (!$manifestcontents && $manifestpath) {
            echo "\nManifest file is empty: {$this->manifestpath}\n";
            echo "You will need to supply context details. Aborting.\n";
            $this->call_exit();
        } else if (!$manifestcontents && !$manifestpath) {
            $this->manifestcontents = new \stdClass();
            $this->manifestcontents->context = new \stdClass();
            $this->manifestcontents->context->contextlevel =
                        cli_helper::get_context_level($instanceinfo->contextinfo->contextlevel);
            $this->manifestcontents->context->coursename = $instanceinfo->contextinfo->coursename;
            $this->manifestcontents->context->modulename = $instanceinfo->contextinfo->modulename;
            $this->manifestcontents->context->coursecategory = $instanceinfo->contextinfo->categoryname;
            $this->manifestcontents->context->instanceid = $instanceinfo->contextinfo->instanceid;
            $this->manifestcontents->context->istargeted = ($this->targetcategory) ? true : false;
            $this->manifestcontents->context->defaultsubcategoryid = $instanceinfo->contextinfo->qcategoryid;
            $this->manifestcontents->context->defaultsubdirectory = $this->subdirectory;
            $this->manifestcontents->context->defaultignorecat = $this->ignorecat;
            $this->manifestcontents->context->defaultdefaults =
                isset($this->defaultsfilepath) ? basename($this->defaultsfilepath) : null;
            $this->manifestcontents->context->moodleurl = $this->moodleurl;
            $this->manifestcontents->questions = [];
        } else {
            $this->manifestcontents = $manifestcontents;
            if ($this->manifestcontents->context->moodleurl !== $this->moodleurl) {
                echo "\nManifest file is for the wrong Moodle instance: {$this->manifestcontents}\nAborting.\n";
                $this->call_exit();
            }
        }

        if ($manifestpath) {
            // Set context info from manifest file rather than other CLI arguments.
            $this->postsettings['instanceid'] = $this->manifestcontents->context->instanceid;
            $this->postsettings['contextlevel'] = $this->manifestcontents->context->contextlevel;
            $this->postsettings['coursename'] = $this->manifestcontents->context->coursename;
            $this->postsettings['modulename'] = $this->manifestcontents->context->modulename;
            $this->postsettings['coursecategory'] = $this->manifestcontents->context->coursecategory;
            $this->listpostsettings['instanceid'] = $this->manifestcontents->context->instanceid;
            $this->listpostsettings['contextlevel'] = $this->manifestcontents->context->contextlevel;
            $this->listpostsettings['coursename'] = $this->manifestcontents->context->coursename;
            $this->listpostsettings['modulename'] = $this->manifestcontents->context->modulename;
            $this->listpostsettings['coursecategory'] = $this->manifestcontents->context->coursecategory;
            $this->ignorecat = isset($arguments['ignorecat']) ?
                    $arguments['ignorecat'] : $this->manifestcontents->context->defaultignorecat ?? null;
            $this->listpostsettings['ignorecat'] = $this->ignorecat;
            $this->listcurlrequest->set_option(CURLOPT_POSTFIELDS, $this->listpostsettings);
            if ($arguments['subdirectory'] && !empty($this->manifestcontents->context->istargeted)) {
                echo "\nThe manifest file was created using targeting. The subdirectory cannot be overridden.\nAborting.\n";
                $this->call_exit();
            }
            if ($arguments['subdirectory'] && empty($this->manifestcontents->context->istargeted)) {
                $this->subdirectory = $arguments['subdirectory'];
                $instanceinfo = $this->clihelper->check_context($this, false, false);
            } else if (!empty($this->manifestcontents->context->istargeted)) {
                $this->subdirectory = $this->manifestcontents->context->defaultsubdirectory;
                $this->targetcategory = $this->manifestcontents->context->defaultsubcategoryid;
                $this->listpostsettings['qcategoryid'] = $this->targetcategory;
                $instanceinfo = $this->clihelper->check_context($this, true, false);
            } else {
                $this->subdirectory = $this->manifestcontents->context->defaultsubdirectory;
                $instanceinfo = $this->clihelper->check_context($this, true, false);
            }

            if ($this->targetcategory) {
                $this->targetcategoryname = $instanceinfo->contextinfo->qcategoryname;
            }
        }
        if (!$this->targetcategory) {
            $qcategoryname = null;
            if ($this->subdirectory === 'top') {
                $qcategoryname = 'top';
            } else {
                $listqcategoryfile = ($this->directory ) ?
                    $this->directory . '/' . $this->subdirectory . '/' . cli_helper::CATEGORY_FILE . '.xml' :
                    $this->subdirectory . '/' . cli_helper::CATEGORY_FILE . '.xml';
                $qcategoryname = cli_helper::get_question_category_from_file($listqcategoryfile);
            }
            if (!$qcategoryname) {
                echo '\nThere is a problem with question category file : ' . $listqcategoryfile . '\n';
                $this->call_exit();
            }
            // Set question category info after call to check_context.
            // We can't rely on the subcategories existing in Moodle until after import
            // if we're using category name.
            $this->listpostsettings['qcategoryname'] = $qcategoryname;
        }
        $this->listcurlrequest->set_option(CURLOPT_POSTFIELDS, $this->listpostsettings);

        if (count($this->manifestcontents->questions) === 0 && empty($arguments['subcall'])) {
            // A quiz in a whole course set up can have an empty manifest as
            // the questions may be in the course.
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
    public function process(): void {
        $this->import_categories();
        $this->import_questions();
        $this->manifestcontents = cli_helper::create_manifest_file(
            $this->manifestcontents,
            $this->tempfilepath,
            $this->manifestpath,
            true
        );
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
    public function get_curl_request($wsurl): curl_request {
        return new \qbank_gitsync\curl_request($wsurl);
    }

    /**
     * Import categories
     *
     * @return void
     */
    public function import_categories(): void {
        if ($this->subdirectory && $this->targetcategory) {
            // We only import a subselection of categories if we have a target category and a subdirectory.
            // Normal subdirectory behaviour is to import all categories but only a subselection of questions.
            $subdirectory = ($this->directory) ? $this->directory . '/' . $this->subdirectory : $this->subdirectory;
        } else {
            $subdirectory = $this->directory . '/top';
        }
        // NB $subdirectory legitimately does not exist for a quiz repo whose questions are
        // all referenced externally via "nonquizfilepath" (see import_quiz_data()) rather
        // than living in a local top/ tree of their own - that's not an error, there's
        // just nothing local to import. Substituting an always-empty iterator (rather
        // than skipping construction of $this->repoiterator entirely) keeps every foreach
        // below, and the ->rewind()/second pass further down that reuses the same
        // iterator, working unchanged - they just iterate zero times, same as an existing
        // but empty directory would already have produced.
        $this->repoiterator = new \RecursiveIteratorIterator(
            is_dir($subdirectory) ? new \RecursiveDirectoryIterator($subdirectory) : new \RecursiveArrayIterator([]),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        // Create missing category files.
        foreach ($this->repoiterator as $repoitem) {
            $itemdirname = pathinfo($repoitem, PATHINFO_DIRNAME);
            $topdir = $this->directory . '/top';
            if ($repoitem->isDir()) {
                if (
                    strpos($itemdirname, $topdir) === false
                    || pathinfo($repoitem, PATHINFO_BASENAME) === '..'
                    || $itemdirname === $topdir
                ) {
                    // Make sure we're actually in the category structure and below top.
                    continue;
                }
                $basepath;
                $newcategory;
                if (pathinfo($repoitem, PATHINFO_BASENAME) === '.') {
                    $basepath = pathinfo($repoitem, PATHINFO_DIRNAME);
                    $newcategory = str_replace('-', ' ', pathinfo($basepath, PATHINFO_BASENAME));
                } else {
                    $basepath = $repoitem->__toString();
                    $newcategory = str_replace('-', ' ', pathinfo($repoitem, PATHINFO_BASENAME));
                }

                $catfilepath = $basepath . '/' . cli_helper::CATEGORY_FILE . '.xml';
                if (!is_file($catfilepath)) {
                    $parentpath = str_replace('\\', '/', pathinfo($basepath, PATHINFO_DIRNAME));
                    $parentfilepath = $parentpath . '/' . cli_helper::CATEGORY_FILE . '.xml';
                    // If there is a category file in a subdirectory it will have the info
                    // we need.
                    $childcatfile = null;
                    $catsearch = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($basepath),
                        \RecursiveIteratorIterator::SELF_FIRST
                    );
                    foreach ($catsearch as $catcandidate) {
                        if (pathinfo($catcandidate, PATHINFO_BASENAME) === cli_helper::CATEGORY_FILE . '.xml') {
                            $childcatfile = $catcandidate;
                            break;
                        }
                    }
                    if ($childcatfile) {
                        $cattext = '';
                        $childcontents = simplexml_load_string(file_get_contents($childcatfile));
                        $childcategory = $childcontents->question->category->text;
                        $childparts = $this->split_category_path($childcategory);
                        $dirparts = explode('/', str_replace('\\', '/', substr($basepath, strlen($this->directory) + 1)));
                        foreach ($dirparts as $key => $dirpart) {
                            $cattext .= ($cattext) ? '/' . $childparts[$key] : $childparts[$key];
                        }
                    } else if (is_file($parentfilepath)) {
                        // If there is a parent file, it will have most of the info we need.
                        // We just need to add the current directory.
                        $parentcontents = simplexml_load_string(file_get_contents($parentfilepath));
                        $parentcategory = $parentcontents->question->category->text;
                        $cattext = $parentcategory . '/' . $newcategory;
                    } else {
                        // If there is no parent file, we must be one below top.
                        $cattext = 'top' . '/' . $newcategory;
                    }
                    $catfile = fopen($catfilepath, 'w+');
                    if ($catfile === false) {
                        echo "\nUnable to create category file: {$catfilepath}\nAborting.\n";
                        $this->call_exit();
                        return; // Required for PHPUnit.
                    }
                    $catcontents = '<?xml version="1.0" encoding="UTF-8"?>
<quiz>
  <question type="category">
    <category>
      <text></text>
    </category>
    <info format="moodle_auto_format">
      <text></text>
    </info>
    <idnumber></idnumber>
  </question>
</quiz>';
                    $categoryxml = simplexml_load_string($catcontents);
                    $categoryxml->question->category->text = $cattext;
                    $success = file_put_contents($catfilepath, $categoryxml->asXML());
                    if ($success === false) {
                        echo "\nUnable to update category file: {$catfilepath}\n Aborting.\n";
                        $this->call_exit();
                        return; // Required for PHPUnit.
                    }
                    fclose($catfile);
                }
            }
        }
        $this->repoiterator->rewind();
        // Find all the category files first and create categories where needed.
        // Categories will be dealt with before their sub-categories. Beyond that,
        // order is uncertain.

        if (!$this->subdirectory || $this->subdirectory === 'top' || !$this->targetcategory) {
            $this->basecategoryname = 'top';
        } else {
            $this->basecategoryname =
                cli_helper::get_question_category_from_file($subdirectory . '/' . cli_helper::CATEGORY_FILE . '.xml');
        }
        foreach ($this->repoiterator as $repoitem) {
            if ($repoitem->isFile()) {
                if (
                    pathinfo($repoitem, PATHINFO_EXTENSION) === 'xml'
                        && pathinfo($repoitem, PATHINFO_FILENAME) === cli_helper::CATEGORY_FILE
                ) {
                    $this->postsettings['qcategoryname'] = '';
                    $newcategory = null;
                    if ($this->ignorecat || $this->targetcategory) {
                        $qcategoryname = cli_helper::get_question_category_from_file($repoitem);
                        if (!$qcategoryname) {
                            echo "\n{$repoitem->getPathname()} not imported?\n";
                            echo "Stopping before trying to import questions.\n";
                            $this->call_exit();
                        }
                        if ($this->ignorecat) {
                            $catparts = $this->split_category_path($qcategoryname);
                            foreach ($catparts as $catpart) {
                                if (preg_match($this->ignorecat, $catpart)) {
                                    continue 2;
                                }
                            }
                        }
                        if ($this->targetcategory) {
                            if (pathinfo($repoitem, PATHINFO_DIRNAME) === $subdirectory) {
                                // This must already exist. (We've checked!).
                                // Skip upload.
                                continue;
                            }
                            // Strip base name from category name and replace with target category.
                            $qcategoryname = substr($qcategoryname, strlen($this->basecategoryname));
                            $newcategory = $this->targetcategoryname . $qcategoryname;
                        }
                    }
                    if ($newcategory) {
                        $tempcatfile = cli_helper::create_temp_category_file($repoitem, $this->tempfilepath, $newcategory);
                        if (!$tempcatfile) {
                            echo "Category {$repoitem} not imported.\n";
                            echo "Stopping before trying to import questions.\n";
                            $this->call_exit();
                        }
                    }
                    $content = file_get_contents(($newcategory ? $tempcatfile : $repoitem)->getPathname());
                    $results = $this->send_batch([['postsettings' => $this->postsettings, 'content' => $content]]);
                    if (!$results || !empty($results[0]->error)) {
                        echo $results ? "{$results[0]->error}\n" : '';
                        echo "{$repoitem->getPathname()} not imported.\n";
                        echo "Stopping before trying to import questions.\n";
                        $this->call_exit();
                    }
                }
            }
        }
    }

    /**
     * Import questions
     *
     * @return resource Temporary manifest file of added questions, one line per question.
     */
    public function import_questions() {
        if ($this->subdirectory) {
            $subdirectory = ($this->directory) ? $this->directory . '/' . $this->subdirectory : $this->subdirectory;
        } else {
            $subdirectory = $this->directory . '/top';
        }
        // See the matching check in import_categories() above - $subdirectory legitimately
        // not existing means there are no local questions to import (e.g. a quiz repo whose
        // questions are all referenced externally via "nonquizfilepath"), not an error.
        $this->subdirectoryiterator = new \RecursiveIteratorIterator(
            is_dir($subdirectory)
                ? new \RecursiveDirectoryIterator($subdirectory, \RecursiveDirectoryIterator::SKIP_DOTS)
                : new \RecursiveArrayIterator([]),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $tempfile = fopen($this->tempfilepath, 'w+');
        if ($tempfile === false) {
            echo "\nUnable to access temp file: {$this->tempfilepath}\nAborting.\n";
            $this->call_exit();
        }
        $existingentries = array_column($this->manifestcontents->questions, null, 'filepath');
        $batch = [];
        // Find all the question files and import them. Order is uncertain.
        $categorynames = [];
        foreach ($this->subdirectoryiterator as $repoitem) {
            if ($repoitem->isFile()) {
                if (
                    in_array(pathinfo($repoitem, PATHINFO_EXTENSION), ['xml', 'yml'])
                        && pathinfo($repoitem, PATHINFO_FILENAME) !== cli_helper::CATEGORY_FILE
                ) {
                    $currentdirectory = $this->subdirectoryiterator->getPath();
                    $qcategoryname = null;
                    if (isset($categorynames[$currentdirectory])) {
                        $qcategoryname = $categorynames[$currentdirectory];
                    } else if (dirname($this->manifestpath) . '/top' === $currentdirectory) {
                        if ($this->targetcategory) {
                            $qcategoryname = $this->targetcategoryname;
                        } else {
                            $qcategoryname = 'top';
                        }
                        $categorynames[$currentdirectory] = $qcategoryname;
                    } else {
                        $categoryfile = $currentdirectory . '/' . cli_helper::CATEGORY_FILE . '.xml';
                        $qcategoryname = cli_helper::get_question_category_from_file($categoryfile);
                        if ($this->targetcategory) {
                            // Strip base name from category name and replace with target category.
                            $qcategoryname = $this->targetcategoryname . substr($qcategoryname, strlen($this->basecategoryname));
                        }
                        $categorynames[$currentdirectory] = $qcategoryname;
                    }
                    $this->postsettings['qcategoryname'] = $qcategoryname;
                    // Path of file (without filename) relative to base $directory.
                    if ($qcategoryname) {
                        if ($this->ignorecat) {
                            $catparts = $this->split_category_path($qcategoryname);
                            foreach ($catparts as $catpart) {
                                if (preg_match($this->ignorecat, $catpart)) {
                                    continue 2;
                                }
                            }
                        }
                        $relpath = str_replace(dirname($this->manifestpath), '', $repoitem->getPathname());
                        $relpath = str_replace('\\', '/', $relpath);
                        $existingentry = $existingentries[$relpath] ?? false;
                        if ($existingentry) {
                            $this->postsettings['questionbankentryid'] = $existingentry->questionbankentryid;
                            $this->postsettings['importedversion'] = $existingentry->importedversion;
                            $this->postsettings['exportedversion'] = $existingentry->exportedversion;
                            if (
                                isset($existingentry->currentcommit)
                                    && isset($existingentry->moodlecommit)
                                    && $existingentry->currentcommit === $existingentry->moodlecommit
                                    && !$this->forceimport
                            ) {
                                continue;
                            }
                        } else {
                            $this->postsettings['questionbankentryid'] = null;
                            $this->postsettings['importedversion'] = null;
                            $this->postsettings['exportedversion'] = null;
                        }
                        $tempqfile = null;
                        if (pathinfo($repoitem, PATHINFO_EXTENSION) === 'yml' || $this->usefragments) {
                            // YAML files always need conversion. XML need conversion if fragments of STACK questions.
                            // We don't have file contents yet so can't check question type here.
                            $tempqfile = cli_helper::create_temp_question_file(
                                $repoitem,
                                $this->tempfilepath,
                                ($this->usefragments) ? $this->defaults : null,
                                $this->useyaml
                            );
                            if ($tempqfile === false) {
                                echo "{$repoitem->getPathname()} not imported.\n";
                                continue;
                            }
                        }

                        // Converted files reuse one temporary path, so keep the content.
                        $content = file_get_contents((($tempqfile) ? $tempqfile : $repoitem)->getPathname());
                        // Send the queued questions first if this one would make the request too large.
                        $queuedbytes = array_sum(array_map('strlen', array_column($batch, 'content')));
                        if ($batch && $queuedbytes + strlen($content) > self::MAX_BATCH_BYTES) {
                            $this->import_batch($batch, $tempfile);
                            $batch = [];
                        }
                        $batch[] = [
                            'repoitem' => $repoitem,
                            'existingentry' => $existingentry,
                            'postsettings' => $this->postsettings,
                            'content' => $content,
                        ];
                        if (count($batch) === $this->batchsize) {
                            $this->import_batch($batch, $tempfile);
                            $batch = [];
                        }
                    } else {
                        echo "Problem with the category file or file location.\n" .
                             "{$repoitem->getPathname()} not imported.\n";
                    }
                }
            }
        }
        if ($batch) {
            $this->import_batch($batch, $tempfile);
        }
        fclose($tempfile);
        return $tempfile;
    }

    /**
     * Import queued questions in one request and add the imported ones to the temporary manifest file.
     *
     * @param array $batch queued questions: repoitem, existingentry, postsettings and content
     * @param resource $tempfile temporary manifest file
     * @return void
     */
    public function import_batch(array $batch, $tempfile): void {
        // Note what is being sent, so that the next run can check these questions if the answer is lost.
        // The manifest record is prepared now, because the files can change before that run.
        $pending = array_map(function ($item) {
            $settings = $item['postsettings'];
            $categories = $this->split_category_path($settings['qcategoryname']);
            return [
                'record' => $this->manifest_record($item['repoitem']->getPathname(), $item['existingentry']),
                'questionbankentryid' => $settings['questionbankentryid'],
                'version' => max((int) $settings['importedversion'], (int) $settings['exportedversion']),
                'name' => self::question_name($item['content']),
                'category' => end($categories),
            ];
        }, $batch);
        file_put_contents($this->pending_path(), json_encode($pending));
        $results = $this->send_batch($batch);
        if ($results === null) {
            echo "Moodle may still have imported questions of this request. Stopping.\n";
            echo "Run the import again. It checks these questions in Moodle before it continues.\n";
            $this->call_exit();
            return;
        }
        unlink($this->pending_path());
        foreach ($batch as $index => $item) {
            $result = $results[$index];
            $filepath = $item['repoitem']->getPathname();
            if (!empty($result->error)) {
                echo "{$result->error}\n";
                echo "{$filepath} not imported.\n";
            } else if ($result->questionbankentryid) {
                $this->record_import($pending[$index]['record'], $result, $tempfile);
                if (!empty($result->validation)) {
                    echo "\n{$filepath} imported but has validation issues. " .
                        "Please exportrepofrommoodle to identify any changes which have been made.\n";
                    echo "{$result->validation}\n";
                }
            }
        }
    }

    /**
     * Manifest record of a question file, without the details of the question in Moodle.
     *
     * @param string $filepath question file in the repository
     * @param object|false $existingentry manifest entry of the question, if any
     * @return array
     */
    private function manifest_record(string $filepath, $existingentry): array {
        $record = ['filepath' => str_replace('\\', '/', $filepath), 'format' => 'xml'];
        if ($existingentry && isset($existingentry->currentcommit)) {
            $record['moodlecommit'] = $existingentry->currentcommit;
        }
        if ($this->usegit && !$existingentry) {
            $manifestdirname = dirname($this->manifestpath);
            chdir($manifestdirname);
            $commithash = exec('git log -n 1 --pretty=format:%H -- "' . $filepath . '"');
            $record['moodlecommit'] = $commithash;
            $record['currentcommit'] = $commithash;
        }
        return $record;
    }

    /**
     * Add an imported question to the temporary manifest file.
     *
     * @param array|object $record manifest record of the question file
     * @param object $question questionbankentryid and version of the question in Moodle
     * @param resource $tempfile temporary manifest file
     * @return void
     */
    private function record_import($record, object $question, $tempfile): void {
        $ids = ['questionbankentryid' => $question->questionbankentryid, 'version' => $question->version];
        fwrite($tempfile, json_encode($ids + (array) $record) . "\n");
    }

    /**
     * Path of the file listing the questions of an import request that Moodle has not answered yet.
     *
     * @return string
     */
    private function pending_path(): string {
        return str_replace('.tmp', '_pending.tmp', $this->tempfilepath);
    }

    /**
     * Name of the question in a Moodle XML file.
     *
     * @param string $content Moodle XML
     * @return string question name, empty if the XML cannot be read
     */
    private static function question_name(string $content): string {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_use_internal_errors($previous);
        return $xml ? trim((string) $xml->question->name->text) : '';
    }

    /**
     * Send questions with their XML to Moodle in one import request.
     *
     * @param array $batch items with postsettings and content
     * @return array|null one result per item, or null if the request failed
     */
    public function send_batch(array $batch): ?array {
        $request = array_intersect_key($this->postsettings, array_flip(['wstoken', 'wsfunction', 'moodlewsrestformat']));
        foreach ($batch as $index => $item) {
            $question = array_diff_key($item['postsettings'], $request);
            $question['content'] = $item['content'];
            foreach ($question as $key => $value) {
                $request["questions[{$index}][{$key}]"] = $value;
            }
        }
        $this->curlrequest->set_option(CURLOPT_POSTFIELDS, $request);
        $response = $this->curlrequest->execute();
        $results = json_decode($response);
        if (!is_array($results) || count($results) !== count($batch)) {
            if (is_object($results) && property_exists($results, 'message')) {
                echo "{$results->message}\n";
                echo property_exists($results, 'debuginfo') ? "{$results->debuginfo}\n" : '';
            } else {
                // A failed curl request returns false, which leaves nothing to decode.
                echo ($response === false) ? "No response from Moodle - the request may have failed or timed out.\n"
                    : "Broken JSON returned from Moodle:\n{$response}\n";
            }
            return null;
        }
        return $results;
    }

    /**
     * Use to update manifest file after CLI failure.
     *
     * @return void
     */
    public function recovery(): void {
        if (file_exists($this->pending_path())) {
            echo "The last run got no answer to an import request. Checking its questions in Moodle:\n";
            $moodle = json_decode($this->listcurlrequest->execute());
            if (!isset($moodle->questions)) {
                echo ($moodle->message ?? 'Broken JSON returned from Moodle.') . "\n";
                echo "Failed to check the questions.\n";
                $this->call_exit();
                return;
            }
            $tempfile = fopen($this->tempfilepath, 'a+');
            $recorded = array_map('json_decode', file($this->tempfilepath));
            $linked = array_column($this->manifestcontents->questions, null, 'questionbankentryid')
                + array_column($recorded, null, 'questionbankentryid');
            foreach (json_decode(file_get_contents($this->pending_path())) as $item) {
                // An existing question was imported if its version went up. A new question was
                // imported if exactly one question with its name and category is not linked yet.
                $matches = array_filter($moodle->questions, function ($question) use ($item, $linked) {
                    if ($item->questionbankentryid) {
                        return (string) $question->questionbankentryid === (string) $item->questionbankentryid
                            && (int) $question->version > $item->version;
                    }
                    return $question->name === $item->name && $question->questioncategory === $item->category
                        && !isset($linked[$question->questionbankentryid]);
                });
                if (count($matches) === 1) {
                    $match = reset($matches);
                    $this->record_import($item->record, $match, $tempfile);
                    $linked[$match->questionbankentryid] = $match;
                    echo "{$item->record->filepath} was imported.\n";
                }
            }
            fclose($tempfile);
            unlink($this->pending_path());
        }
        if (file_exists($this->tempfilepath)) {
            echo 'Attempting recovery from failure on previous run. Updating manifest:';
            $this->manifestcontents = cli_helper::create_manifest_file(
                $this->manifestcontents,
                $this->tempfilepath,
                $this->manifestpath,
                true
            );
            unlink($this->tempfilepath);
            echo 'Recovery successful. Continuing...';
        }
    }

    /**
     * Offer to delete questions from Moodle/manifest where the question is in the manifest
     * but there is no file in the repo.
     *
     * @param bool $deleteenabled Allows question delete if true, otherwise just lists applicable questions
     * @return void
     */
    public function delete_no_file_questions(bool $deleteenabled = false): void {
        // Get all manifest entries for imported subdirectory.
        // Filepath should equal subdirectory or path must be longer and continue with
        // one (and only) one slash.
        $manifestentries = array_filter($this->manifestcontents->questions, function ($value) {
            return (substr($value->filepath, 1, strlen($this->subdirectory)) === $this->subdirectory
                    && (strlen($value->filepath) === strlen($this->subdirectory) + 1
                        || preg_match('/^\/{1}(?!\/)/', substr($value->filepath, strlen($this->subdirectory) + 1))));
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
                file_put_contents($this->manifestpath, json_encode($this->manifestcontents, JSON_PRETTY_PRINT));
            } else {
                echo "Run deletefrommoodle for the option to delete.\n";
            }
        } else {
            echo "\nAll questions in the manifest have a matching file. Nothing to delete.\n";
        }
    }

    /**
     * Offer to delete questions from Moodle where the question is in Moodle
     * but not in the manifest.
     *
     * @param bool $deleteenabled Allows question delete if true, otherwise just lists applicable questions
     * @return void
     */
    public function delete_no_record_questions(bool $deleteenabled = false): void {
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
            if (property_exists($questionsinmoodle, 'debuginfo')) {
                echo "{$questionsinmoodle->debuginfo}\n";
            }
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
        } else {
            echo "\nAll selected questions in Moodle are linked to your repository. Nothing to delete.\n";
        }
    }

    /**
     * Prompt user whether to delete question
     *
     * @param object $question \stdClass question to be deleted
     * @return bool Was the question deleted
     */
    public function handle_delete(object $question): bool {
        $deleted = false;
        $handle = fopen("php://stdin", "r");
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
                echo "{$responsejson->message}\n";
                if (property_exists($responsejson, 'debuginfo')) {
                    echo "{$responsejson->debuginfo}\n";
                }
                echo "Not deleted\n";
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
    public function handle_abort(): void {
        global $usecontinue;
        echo ($usecontinue ? "Continue? y/n\n" : "Abort? y/n\n");
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        if (($usecontinue && trim($line) === 'y') || (!$usecontinue && trim($line) === 'n')) {
            return;
        }
        $this->call_exit();
    }

    /**
     * Check if the question versions in Moodle and the manifest match.
     *
     * @return void
     */
    public function check_question_versions(): void {
        if (count($this->manifestcontents->questions) === 0) {
            return;
        }
        $questionstocheck = [];
        $response = $this->listcurlrequest->execute();
        // Retrieve all the question info for questions in the context.
        $questionsinmoodle = json_decode($response);
        if (is_null($questionsinmoodle)) {
            echo "Broken JSON returned from Moodle:\n";
            echo $response . "\n";
            echo "Failed to check question versions.\n";
            $this->call_exit();
            $questionsinmoodle = json_decode('{"questions": []}'); // Required for unit tests.
        } else if (property_exists($questionsinmoodle, 'exception')) {
            if (isset($questionsinmoodle->errorcode) && $questionsinmoodle->errorcode === 'categoryerror') {
                $arguments = $this->clihelper->get_arguments();
                if (empty($arguments['subcall'])) {
                    echo "Target category {$this->listpostsettings['qcategoryname']} does not exist in Moodle.\n";
                    echo "This should only be the case if you're importing it for the first time and\n";
                    echo "want to create new questions in Moodle.\n";
                    $this->handle_abort();
                }
                return;
            } else {
                echo "{$questionsinmoodle->message}\n";
                echo "Failed to check question versions.\n";
                $this->call_exit();
                $questionsinmoodle = json_decode('{"questions": []}'); // Required for unit tests.
            }
        }
        // We also need to check questions that have been moved to another context.
        // We do this in a second pass to minimise the size of passed array.
        $retrievedentries = array_column($questionsinmoodle->questions, null, 'questionbankentryid');
        foreach ($this->manifestcontents->questions as $manifestquestion) {
            if (
                substr($manifestquestion->filepath, 1, strlen($this->subdirectory)) === $this->subdirectory
                && preg_match('/^\/{1}(?!\/)/', substr($manifestquestion->filepath, strlen($this->subdirectory) + 1))
            ) {
                // Start of filepath of question must match start of subdirectory to import.
                // Filepath must continue with one (and only) one slash.
                $retrievedalready = $retrievedentries["{$manifestquestion->questionbankentryid}"] ?? false;
                if (!$retrievedalready) {
                    array_push($questionstocheck, $manifestquestion->questionbankentryid);
                }
            }
        }
        if (count($questionstocheck) > 0) {
            foreach ($questionstocheck as $key => $id) {
                $this->listpostsettings["qbankentryids[{$key}]"] = $id;
            }
            $this->listcurlrequest->set_option(CURLOPT_POSTFIELDS, $this->listpostsettings);
            $secondresponse = $this->listcurlrequest->execute();
            $movedquestionsinmoodle = json_decode($secondresponse);
            if (is_null($movedquestionsinmoodle)) {
                echo "Broken JSON returned from Moodle:\n";
                echo $secondresponse . "\n";
                echo "Failed to check question versions.\n";
                $this->call_exit();
                $movedquestionsinmoodle = json_decode('{"questions": []}'); // Required for unit tests.
            } else if (property_exists($movedquestionsinmoodle, 'exception')) {
                echo "{$movedquestionsinmoodle->message}\n";
                if (property_exists($movedquestionsinmoodle, 'debuginfo')) {
                    echo "{$movedquestionsinmoodle->debuginfo}\n";
                }
                echo "Failed to check question versions.\n";
                $this->call_exit();
                $movedquestionsinmoodle = json_decode('{"questions": []}'); // Required for unit tests.
            }
            $questionsinmoodle->questions = array_merge(
                $questionsinmoodle->questions,
                $movedquestionsinmoodle->questions
            );
        }
        $manifestentries = array_column($this->manifestcontents->questions, null, 'questionbankentryid');
        $changes = false;
        // If the version in Moodle and in the importedversion in manifest don't match, the question has been updated in Moodle
        // since we created the repo or last imported to Moodle.
        // If the last exportedversion doesn't match either we haven't exported the changes from Moodle and dealt with
        // them locally. Instruct user to export.
        foreach ($questionsinmoodle->questions as $moodleq) {
            if (
                isset($manifestentries[$moodleq->questionbankentryid])
                    && $moodleq->version !== $manifestentries[$moodleq->questionbankentryid]->importedversion
                    && $moodleq->version !== $manifestentries[$moodleq->questionbankentryid]->exportedversion
            ) {
                echo "{$moodleq->questionbankentryid} - {$moodleq->questioncategory} - {$moodleq->name}\n";
                echo "Moodle question version: {$moodleq->version}\n";
                echo "Version on last import to Moodle: {$manifestentries[$moodleq->questionbankentryid]->importedversion}\n";
                echo "Version on last export from Moodle: {$manifestentries[$moodleq->questionbankentryid]->exportedversion}\n";
                $changes = true;
            }
        }
        if ($changes) {
            echo "Export questions from Moodle before proceeding.\n";
            $this->call_exit();
        }
        $this->listpostsettings['qbankentryids[]'] = null;
        $this->listcurlrequest->set_option(CURLOPT_POSTFIELDS, $this->listpostsettings);
    }

    /**
     * Create/update quizzes for whole course.
     * @param object $clihelper
     * @param string $scriptdirectory - directory of CLI scripts
     * @return void
     */
    public function update_quizzes($clihelper, $scriptdirectory) {
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
        $quizlocations = (isset($this->manifestcontents->quizzes)) ? $this->manifestcontents->quizzes : [];
        $topdircontents = scandir(dirname($basedirectory));
        foreach ($topdircontents as $quizdirectory) {
            if (!is_dir(dirname($basedirectory) . '/' . $quizdirectory) || strpos($quizdirectory, '_quiz_') === false) {
                // Don't import from anything that isn't a directory or has a name in the wrong format.
                continue;
            }
            $instanceid = array_column($quizlocations, null, 'directory')[$quizdirectory]->moduleid ?? false;
            $rootdirectory = dirname($basedirectory) . '/' . $quizdirectory;
            $quizfiles = scandir($rootdirectory);
            $structurefile = null;
            // Find the structure file.
            foreach ($quizfiles as $quizfile) {
                if (preg_match('/.*_quiz\.json/', $quizfile)) {
                    $structurefile = $quizfile;
                    break;
                }
            }
            if (!$structurefile) {
                echo "\nNo structure file in {$rootdirectory}\nQuiz not imported.\n";
                continue;
            }

            $structurefilepath = $rootdirectory . '/' . $structurefile;
            $contentsjson = file_get_contents($structurefilepath);
            $structurecontents = json_decode($contentsjson);
            $quizmanifestname = cli_helper::get_manifest_path(
                $moodleinstance,
                'module',
                null,
                $contextinfo->contextinfo->coursename,
                $structurecontents->quiz->name,
                ''
            );
            $quizmanifestpath = $rootdirectory . $quizmanifestname;
            $quizcmid = null;

            if (!is_file($quizmanifestpath)) {
                // The quiz does not exist in the targeted Moodle instance. We need to create it,
                // import questions and then add questions to the quiz.
                echo "\nCreating quiz: {$structurecontents->quiz->name}\n";
                $output = $this->call_import_quiz_data(
                    $moodleinstance,
                    $token,
                    $contextinfo->contextinfo->instanceid,
                    null,
                    $structurefilepath,
                    $scriptdirectory
                );
            }
            // Import quiz context questions.
            echo "\nImporting quiz context: {$structurecontents->quiz->name}\n";
            if (is_file($quizmanifestpath)) {
                $output = $this->call_import_repo(
                    $rootdirectory,
                    $moodleinstance,
                    $token,
                    $quizmanifestname,
                    null,
                    $ignorecat,
                    $scriptdirectory
                );
                echo $output;
            } else {
                // No quiz manifest. We need to import questions into context of created quiz.
                $newcontextinfo = $clihelper->check_context($this, false, true);
                $oldquizzes = array_column($contextinfo->quizzes, null, 'instanceid');
                foreach ($newcontextinfo->quizzes as $newquiz) {
                    if (!isset($oldquizzes[$newquiz->instanceid])) {
                        $quizcmid = $newquiz->instanceid;
                        break;
                    }
                }
                $contextinfo = $newcontextinfo;
                if (!$quizcmid) {
                    echo "\nQuiz was not created for some reason.\n Aborting.\n";
                    $this->call_exit();
                    $instanceid = 'Test'; // Required for unit tests.
                    $this->manifestcontents->quizzes = [];
                }
                $output = $this->call_import_repo(
                    $rootdirectory,
                    $moodleinstance,
                    $token,
                    null,
                    $quizcmid,
                    $ignorecat,
                    $scriptdirectory
                );
                echo $output;
            }
            if ($instanceid === false) {
                // Import quiz structure as it's not currenty in the nonquizmanifest.
                echo "\nImporting quiz structure: {$structurecontents->quiz->name}\n";
                $output = $this->call_import_quiz_data(
                    $moodleinstance,
                    $token,
                    $contextinfo->contextinfo->instanceid,
                    $quizmanifestpath,
                    $structurefilepath,
                    $scriptdirectory
                );
                echo $output;
                $quizlocation = new \StdClass();
                if (!$quizcmid) {
                    $quizmanifestcontents = json_decode(file_get_contents($quizmanifestpath));
                    if (!$quizmanifestcontents) {
                        echo "\nUnable to access or parse manifest file: {$this->quizmanifestpath}\nAborting.\n";
                        $this->call_exit();
                    }
                    $quizcmid = $quizmanifestcontents->context->instanceid;
                }
                $quizlocation->moduleid = $quizcmid;
                $quizlocation->directory = basename($rootdirectory);
                $quizlocations[] = $quizlocation;
                $this->manifestcontents->quizzes = $quizlocations;
                $success = file_put_contents($this->manifestpath, json_encode($this->manifestcontents, JSON_PRETTY_PRINT));
                if ($success === false) {
                    echo "\nUnable to update manifest file: {$this->manifestpath}\n Aborting.\n";
                    exit();
                }
            }
        }

        // Check for quizzes which are in Moodle but not in the manifest.
        $quizlocations = $this->manifestcontents->quizzes;
        $locarray = array_column($quizlocations, null, 'moduleid');
        foreach ($contextinfo->quizzes as $quiz) {
            $instanceid = (int) $quiz->instanceid;
            if (!isset($locarray[$instanceid])) {
                echo "\nQuiz {$quiz->name} is in Moodle but not in the manifest. " .
                "It should be exported from Moodle or manually deleted within Moodle.\n";
            }
        }
    }

    /**
     * Separate out exec call for mocking.
     *
     * @param string $rootdirectory
     * @param string $moodleinstance
     * @param string $token
     * @param string|null $quizmanifestname
     * @param string|null $cmid
     * @param string $ignorecat
     * @param string $scriptdirectory
     * @return string|null
     */
    public function call_import_repo(
        string $rootdirectory,
        string $moodleinstance,
        string $token,
        ?string $quizmanifestname,
        ?string $quizcmid,
        string $ignorecat,
        string $scriptdirectory
    ): string {
        $usegit = ($this->usegit) ? 'true' : 'false';
        $useyaml = ($this->useyaml) ? 'true' : 'false';
        $forceimport = ($this->forceimport) ? ' -z' : '';
        $defaults = ($this->usefragments) ? ' -b true -o "' . basename($this->defaultsfilepath) . '"' : '';
        if ($this->usefragments) {
            copy($this->defaultsfilepath, $rootdirectory . '/' . basename($this->defaultsfilepath));
        }
        chdir($scriptdirectory);
        if ($quizmanifestname) {
            return shell_exec('php importrepotomoodle.php -u ' . $usegit . ' -w -r "' . $rootdirectory .
                            '" -i "' . $moodleinstance . '" -f "' . $quizmanifestname . '" -t ' . $token . $ignorecat .
                            ' -y ' . $useyaml . $defaults . $forceimport);
        } else {
            return shell_exec('php importrepotomoodle.php -u ' . $usegit . ' -w -r "' . $rootdirectory .
                            '" -i "' . $moodleinstance . '" -l "module" -n ' . $quizcmid . ' -t ' . $token . $ignorecat .
                            ' -y ' . $useyaml . $defaults . $forceimport);
        }
    }

    /**
     * Separate out exec call for mocking.
     *
     * @param string $moodleinstance
     * @param string $token
     * @param string $instanceid
     * @param string|null $quizmanifestpath
     * @param string $structurefilepath
     * @param string $scriptdirectory
     * @return string|null
     */
    public function call_import_quiz_data(
        string $moodleinstance,
        string $token,
        string $instanceid,
        ?string $quizmanifestpath,
        string $structurefilepath,
        string $scriptdirectory
    ): ?string {
        chdir($scriptdirectory);
        $usegit = ($this->usegit) ? 'true' : 'false';
        if ($quizmanifestpath) {
            return shell_exec('php importquizstructuretomoodle.php -u ' . $usegit .
                    ' -w -r "" -i "' . $moodleinstance . '" -n ' .
                    $instanceid . ' -t ' . $token . ' -p "' . $this->manifestpath . '" -f "' .
                    $quizmanifestpath . '" -a "' . $structurefilepath . '"');
        } else {
            return shell_exec('php importquizstructuretomoodle.php -u ' . $usegit .
                    ' -w -r "" -i "' . $moodleinstance . '" -n ' .
                    $instanceid . ' -t ' . $token . ' -p "' . $this->manifestpath . '" -a "' . $structurefilepath . '"');
        }
    }

    /**
     * Mockable function that just exits code.
     *
     * Required to stop PHPUnit displaying output after exit.
     *
     * @return void
     */
    public function call_exit(): void {
        exit;
    }

    /**
     * Convert a string into an array of category names.
     *     *
     * @param string|null $path
     * @return array of category names.
     */
    public function split_category_path(?string $path): array {
        $rawnames = preg_split('~(?<!/)/(?!/)~', $path);
        $names = [];
        foreach ($rawnames as $rawname) {
            $names[] = trim(str_replace('//', '/', $rawname));
        }
        return $names;
    }
}
