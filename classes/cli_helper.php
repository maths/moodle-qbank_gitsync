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
 * Helper methods for CLI scripts.
 *
 * Used outside Moodle.
 *
 * @package    qbank_gitsync
 * @copyright  2023 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_gitsync;

/**
 * Wrapper class for methods shared between CLI scripts.
 *
 * Allows mocking via PHPUnit.
 */
class cli_helper {
    /** @var array of command line option definitions for a CLI
     * Each option should be in the format:
     *     [
     *      'longopt' => 'moodleinstance',
     *      'shortopt' => 'i',
     *      'description' => 'Moodle instance name.',
     *      'default' => 'local',
     *      'variable' => 'moodleinstance', // Matching variable in the code.
     *      'valuerequired' => true, // Does this option take a value?
     *     ]
     *
     */
    protected array $options;
    /**
     * @var array|null Full set of options combining command line and defaults
     */
    public ?array $processedoptions = null;
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
     * TEMP_MANIFEST_FILE - File name ending for temporary manifest file.
     * Appended to name of moodle instance.
     */
    public const TEMP_MANIFEST_FILE = '_manifest_update.tmp';
    /**
     * BAD_CHARACTERS - Characters to remove for filename sanitisation
     */
    public const BAD_CHARACTERS = '/[\/\\\?\%*:|"<> .]+/';
    /**
     * Constructor
     *
     * @param array $options
     */
    public function __construct(array $options) {
        $this->options = $options;
    }

    /**
     * Creates an array of options for the CLI based on input command line
     * options and defined defaults.
     *
     * @return array of options and values.
     */
    public function get_arguments(): array {
        if ($this->processedoptions) {
            return $this->processedoptions;
        }
        $parsed = $this->parse_options();
        $shortopts = $parsed['shortopts'];
        $longopts = $parsed['longopts'];
        $commandlineargs = getopt($shortopts, $longopts);
        $this->processedoptions = $this->prioritise_options($commandlineargs);
        if ($this->processedoptions['help']) {
            $this->show_help();
            exit;
        }
        $this->validate_and_clean_args();
        return $this->processedoptions;
    }

    /**
     * Parse the CLI option definitions.
     *
     * @return array containing valid long and short options in format
     * suitable for getopt()
     */
    public function parse_options(): array {
        $shortopts = '';
        $longopts = [];

        foreach ($this->options as $option) {
            $shortopts .= $option['shortopt'];
            if ($option['valuerequired']) {
                $longopts[] = $option['longopt'] . ':';
                $shortopts .= ':';
            } else {
                $longopts[] = $option['longopt'];
            }
        }

        return ['shortopts' => $shortopts, 'longopts' => $longopts];
    }

    /**
     * Do some initial checks on command line arguments and tidy them up a bit.
     *
     * @return void
     */
    public function validate_and_clean_args(): void {
        $cliargs = $this->processedoptions;
        if (!isset($cliargs['token'])) {
            echo "\nYou will need a security token (--token).\n";
            static::call_exit();
        }
        if (isset($cliargs['directory'])) {
            $cliargs['directory'] = $this->trim_slashes($cliargs['directory']);
        }
        if (isset($cliargs['manifestpath'])) {
            if (isset($cliargs['directory']) && strlen($cliargs['directory']) > 0 ) {
                echo "\nYou have supplied a manifest file path and a directory. " .
                     "Please use only one.\n";
                static::call_exit();
            }
        }
        if (isset($cliargs['rootdirectory'])) {
            $cliargs['rootdirectory'] = str_replace( '\\', '/', $cliargs['rootdirectory']);
            if (substr($cliargs['rootdirectory'], strlen($cliargs['rootdirectory']) - 1, 1) === '/') {
                $cliargs['rootdirectory'] = substr($cliargs['rootdirectory'], 0, strlen($cliargs['rootdirectory']) - 1);
            }
        }
        if (isset($cliargs['subdirectory'])) {
            if (strlen($cliargs['subdirectory']) > 0 && isset($cliargs['questioncategoryid'])) {
                echo "\nYou have supplied a subdirectory to identify the required question category " .
                     "and a question category id. Please use only one.\n";
                static::call_exit();
            }
            if (strlen($cliargs['subdirectory']) > 0) {
                $cliargs['subdirectory'] = $this->trim_slashes($cliargs['subdirectory']);
                if (strlen($cliargs['subdirectory']) > 0 && substr($cliargs['subdirectory'], 0, 3) !== 'top') {
                    $cliargs['subdirectory'] = 'top/' . $cliargs['subdirectory'];
                }
            }
            if (strlen($cliargs['subdirectory']) === 0) {
                $cliargs['subdirectory'] = 'top';
            }
        }
        if (isset($cliargs['subcategory'])) {
            if (strlen($cliargs['subcategory']) > 0 && isset($cliargs['questioncategoryid'])) {
                echo "\nYou have supplied a subcategory to identify the required question category " .
                     "and a question category id. Please use only one.\n";
                static::call_exit();
            }
            if (strlen($cliargs['subcategory']) > 0) {
                $cliargs['subcategory'] = $this->trim_slashes($cliargs['subcategory']);
                if (strlen($cliargs['subcategory']) > 0 && substr($cliargs['subcategory'], 0, 3) !== 'top') {
                    $cliargs['subcategory'] = 'top/' . $cliargs['subcategory'];
                }
            }
            if (strlen($cliargs['subcategory']) === 0) {
                $cliargs['subcategory'] = 'top';
            }
        }
        if (isset($cliargs['manifestpath'])) {
            $cliargs['manifestpath'] = $this->trim_slashes($cliargs['manifestpath']);
            if (isset($cliargs['coursename']) || isset($cliargs['modulename'])
                        || isset($cliargs['coursecategory']) || (isset($cliargs['instanceid'])
                        || isset($cliargs['contextlevel']) )) {
                echo "\nYou have specified a manifest file. Contextlevel, instance id, " .
                        "course name, module name and/or course category are not needed. " .
                        "Context data can be extracted from the file.\n";
                static::call_exit();
            }
        }
        if (isset($cliargs['instanceid'])) {
            if (isset($cliargs['coursename']) || isset($cliargs['modulename'])
                    || isset($cliargs['coursecategory'])) {
                echo "\nIf instanceid is supplied, you do not require " .
                     "course name, module name and/or course category.\n";
                static::call_exit();
            }
        }
        if (isset($cliargs['contextlevel'])) {
            switch ($cliargs['contextlevel']) {
                case 'system':
                    if (isset($cliargs['coursename']) || isset($cliargs['modulename'])
                            || isset($cliargs['coursecategory']) || (isset($cliargs['instanceid']))) {
                        echo "\nYou have specified system level context. Instance id, " .
                            "course name, module name and/or course category are not needed.\n";
                        static::call_exit();
                    }
                    break;
                case 'coursecategory':
                    if (isset($cliargs['coursename']) || isset($cliargs['modulename'])) {
                        echo "\nYou have specified course category level context. " .
                            "Course name and/or module name are not needed.\n";
                        static::call_exit();
                    }
                    if (!isset($cliargs['coursecategory']) && !isset($cliargs['instanceid'])) {
                        echo "\nYou have specified course category level context. " .
                            "You must specify the category name (--coursecategory) or \n" .
                            "its Moodle id (--instanceid).\n";
                        static::call_exit();
                    }
                    break;
                case 'course':
                    if (isset($cliargs['coursecategory']) || isset($cliargs['modulename'])) {
                        echo "\nYou have specified course level context. " .
                            "Course category name and/or module name are not needed.\n";
                        static::call_exit();
                    }
                    if (!isset($cliargs['coursename']) && !isset($cliargs['instanceid'])) {
                        echo "\nYou have specified course level context. " .
                            "You must specify the full course name (--coursename) or \n" .
                            "its Moodle id (--instanceid).\n";
                        static::call_exit();
                    }
                    break;
                case 'module':
                    if (isset($cliargs['coursecategory'])) {
                        echo "\nYou have specified module level context. " .
                            "Course category name is not needed.\n";
                        static::call_exit();
                    }
                    if ((!isset($cliargs['coursename']) || !isset($cliargs['modulename']))
                                && !isset($cliargs['instanceid'])) {
                        echo "\nYou have specified module level context. " .
                            "You must specify the full course name (--coursename) and \n" .
                            "module name (--modulename) or give the module Moodle id (--instanceid).\n";
                        static::call_exit();
                    }
                    break;
                default:
                    echo "\nContextlevel should be 'system', 'coursecategory', 'course' or 'module'.\n";
                    static::call_exit();
                    break;
            }
        }
        if (!isset($cliargs['manifestpath']) && !isset($cliargs['contextlevel'])) {
            echo "\nYou have not specified context. " .
                 "You must specify context level (--contextlevel) unless \n" .
                 "using a function where this information can be read from a manifest file, in which case" .
                 "you could set a manifest path (--manifestpath) instead.\n";
            static::call_exit();
        }

        $this->processedoptions = $cliargs;
    }

    /**
     * Remove beginning and end slashes from a path.
     * Convert all slashes to unix-friendly.
     *
     * @param string $path
     * @return string
     */
    public function trim_slashes(string $path):string {
        $path = str_replace( '\\', '/', $path);
        if (substr($path, 0, 1) === '/') {
            $path = substr($path, 1);
        }
        if (substr($path, strlen($path) - 1, 1) === '/') {
            $path = substr($path, 0, strlen($path) - 1);
        }
        return $path;
    }

    /**
     * Combine sanitised output of getopt() with defaults
     *
     * @param array $commandlineargs output from getopt()
     * @return array of options and values
     */
    public function prioritise_options($commandlineargs): array {
        $variables = [];

        foreach ($this->options as $option) {
            $variablename = $option['variable'];
            if ($option['valuerequired']) {
                if (isset($commandlineargs[$option['longopt']])) {
                    $variables[$variablename] = $commandlineargs[$option['longopt']];
                } else if (isset($commandlineargs[$option['shortopt']])) {
                    $variables[$variablename] = $commandlineargs[$option['shortopt']];
                } else {
                    $variables[$variablename] = $option['default'];
                }
            } else {
                if (isset($commandlineargs[$option['longopt']]) || isset($commandlineargs[$option['shortopt']])) {
                    $variables[$variablename] = true;
                } else {
                    $variables[$variablename] = $option['default'];
                }
            }
        }

        return $variables;

    }

    /**
     * Format output of CLI help.
     *
     * @return void
     */
    public function show_help() {
        foreach ($this->options as $option) {
            echo "-{$option['shortopt']} --{$option['longopt']}  \t{$option['description']}\n";
        }
        exit;
    }

    /**
     * Convert our contextlevel string to Moodle's internal code.
     *
     * @param string $level
     * @return integer
     */
    public static function get_context_level(string $level): int {
        switch ($level) {
            case 'system':
                return 10;
            case 'coursecategory':
                return 40;
            case 'course':
                return 50;
            case 'module':
                return 70;
            default:
                echo "Context level '{$level}' is not valid.\n";
                static::call_exit();
                return 0; // Required for PHPUnit.
        }
    }

    /**
     * Create manifest path
     *
     * @param string $moodleinstance
     * @param string $contextlevel
     * @param string|null $coursecategory
     * @param string|null $coursename
     * @param string|null $modulename
     * @param string $directory
     * @return string
     */
    public static function get_manifest_path(string $moodleinstance, string $contextlevel, ?string $coursecategory,
                            ?string $coursename, ?string $modulename, string $directory):string {
        $filenamemod = '_' . $contextlevel;
        switch ($contextlevel) {
            case 'coursecategory':
                $filenamemod = $filenamemod . '_' . substr($coursecategory, 0, 100);
                break;
            case 'course':
                $filenamemod = $filenamemod . '_' . substr($coursename, 0, 100);
                break;
            case 'module':
                $filenamemod = $filenamemod . '_' . substr($coursename, 0, 50) . '_' . substr($modulename, 0, 50);
                break;
        }

        $filename = $directory . '/' .
                    preg_replace(self::BAD_CHARACTERS, '-', strtolower(substr($moodleinstance, 0, 50) . $filenamemod)) .
                    self::MANIFEST_FILE;
        return $filename;
    }

    /**
     * Create manifest file from temporary file.
     *
     * @param object $manifestcontents \stdClass Current contents of manifest file
     * @param string $tempfilepath
     * @param string $manifestpath
     * @param string $moodleurl
     * @return object
     */
    public static function create_manifest_file(object $manifestcontents, string $tempfilepath,
                                                string $manifestpath, string $moodleurl):object {
        // Read in temp file a question at a time, process and add to manifest.
        // No actual processing at the moment so could simplify to write straight
        // to manifest in the first place if no processing materialises.
        $manifestdir = dirname($manifestpath);
        $manifestdir = str_replace( '\\', '/', $manifestdir);
        $tempfile = fopen($tempfilepath, 'r');
        if ($tempfile === false) {
            echo "\nUnable to access temp file: {$tempfilepath}\n Aborting.\n";
            static::call_exit();
            return new \stdClass(); // Required for PHPUnit.
        }
        $existingentries = array_column($manifestcontents->questions, null, 'questionbankentryid');
        while (!feof($tempfile)) {
            $questioninfo = json_decode(fgets($tempfile));
            if ($questioninfo) {
                $existingentry = $existingentries["{$questioninfo->questionbankentryid}"] ?? false;
                if (!$existingentry) {
                    $questionentry = new \stdClass();
                    $questionentry->questionbankentryid = $questioninfo->questionbankentryid;
                    $questionentry->filepath = str_replace($manifestdir, '', $questioninfo->filepath);
                    $questionentry->format = $questioninfo->format;
                    $questionentry->importedversion = $questioninfo->version;
                    $questionentry->exportedversion = $questioninfo->version;
                    if (isset($questioninfo->moodlecommit)) {
                        $questionentry->moodlecommit = $questioninfo->moodlecommit;
                    }
                    if (isset($questioninfo->currentcommit)) {
                        $questionentry->currentcommit = $questioninfo->currentcommit;
                    }
                    array_push($manifestcontents->questions, $questionentry);
                } else {
                    $existingentries["{$questioninfo->questionbankentryid}"]->importedversion = $questioninfo->version;
                    if (isset($questioninfo->moodlecommit)) {
                        $existingentries["{$questioninfo->questionbankentryid}"]->moodlecommit = $questioninfo->moodlecommit;
                    }
                }
                if ($manifestcontents->context === null) {
                    $manifestcontents->context = new \stdClass();
                    $manifestcontents->context->contextlevel = $questioninfo->contextlevel;
                    $manifestcontents->context->coursename = $questioninfo->coursename;
                    $manifestcontents->context->modulename = $questioninfo->modulename;
                    $manifestcontents->context->coursecategory = $questioninfo->coursecategory;
                    $manifestcontents->context->instanceid = $questioninfo->instanceid;
                    $manifestcontents->context->moodleurl = $moodleurl;
                }
            }
        }
        $success = file_put_contents($manifestpath, json_encode($manifestcontents));
        if ($success === false) {
            echo "\nUnable to update manifest file: {$manifestpath}\n Aborting.\n";
            static::call_exit();
        }
        fclose($tempfile);
        return $manifestcontents;
    }

    /**
     * Tidy up question formatting and remove unwanted comment
     *
     * @param string $question original question XML
     * @return string tidied question XML
     */
    public static function reformat_question(string $question):string {
        $locale = setlocale(LC_ALL, 0);
        // Options for HTML Tidy.
        // If in doubt, set to false to avoid unexpected 'repairs'.
        $sharedoptions = [
            'break-before-br' => true,
            'show-body-only' => true,
            'wrap' => '0',
            'indent' => true,
            'coerce-endtags' => false,
            'drop-empty-elements' => false,
            'drop-empty-paras' => false,
            'fix-backslash' => false,
            'fix-bad-comments' => false,
            'merge-emphasis' => false,
            'quote-ampersand' => false,
            'quote-nbsp' => false,
        ];
        if (!function_exists('tidy_repair_string')) {
            // Tidy not installed.
            return $question;
        }
        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = true;
        $dom->loadXML($question);

        $xpath = new \DOMXpath($dom);
        $tidyoptions = array_merge($sharedoptions, [
            'output-xhtml' => true,
        ]);
        $tidy = new \tidy();

        // Find CDATA sections and format nicely.
        foreach ($xpath->evaluate("//*[@format='html']/text/text()") as $cdata) {
            if ($cdata->data) {
                $tidy->parseString($cdata->data, $tidyoptions);
                $tidy->cleanRepair();
                $output = tidy_get_output($tidy);
                $cdata->data = "\n{$output}\n";
            }
        }

        $cdataprettyxml = $dom->saveXML();

        // Remove question id comment.
        $xml = simplexml_load_string($cdataprettyxml);
        if ($xml === false) {
            setlocale(LC_ALL, $locale);
            throw new \Exception('Broken XML');
        }
        if (get_class($xml->comment) === 'SimpleXMLElement') {
            unset($xml->comment);
        }

        $noidxml = $xml->asXML();

        // Tidy the whole thing, cluding indenting CDATA as a whole.
        $tidyoptions = array_merge($sharedoptions, [
            'input-xml' => true,
            'output-xml' => true,
            'indent-cdata' => true,
        ]);
        $tidy->parseString($noidxml, $tidyoptions);
        $tidy->cleanRepair();
        $result = tidy_get_output($tidy);
        // HTML Tidy switches to the default locale for the system. PHPUnit uses en_AU.
        // PHPUnit throws a warning unless we switch back to en_AU.
        setlocale(LC_ALL, $locale);

        return $result;
    }

    /**
     * Updates the manifest file with the current commit hashes of question files in the repo.
     *
     * @param object $activity e.g. import_repo
     * @return void
     */
    public function commit_hash_update(object $activity):void {
        if (!$this->get_arguments()['usegit']) {
            return;
        }
        foreach ($activity->manifestcontents->questions as $question) {
            $commithash = exec('git log -n 1 --pretty=format:%H -- "' . substr($question->filepath, 1) . '"');
            $question->currentcommit = $commithash;
        }
        $success = file_put_contents($activity->manifestpath, json_encode($activity->manifestcontents));
        if ($success === false) {
            echo "\nUnable to update manifest file: {$activity->manifestpath}\n Aborting.\n";
            exit;
        }
    }

    /**
     * Updates the manifest file with the current commit hashes of question files in the repo.
     * Used on initial repo creation so also sets the moodle commit to be the same.
     *
     * @param object $activity e.g. create_repo
     * @return void
     */
    public function commit_hash_setup(object $activity):void {
        if (!$this->get_arguments()['usegit']) {
            return;
        }
        $this->create_gitignore($activity->manifestpath);
        $manifestdirname = dirname($activity->manifestpath);
        chdir($manifestdirname);
        exec("git add .");
        exec('git commit -m "Initial Commit"');
        foreach ($activity->manifestcontents->questions as $question) {
            $commithash = exec('git log -n 1 --pretty=format:%H -- "' . substr($question->filepath, 1) . '"');
            $question->currentcommit = $commithash;
            $question->moodlecommit = $commithash;
        }
        // Happens last so no need to abort on failure.
        file_put_contents($activity->manifestpath, json_encode($activity->manifestcontents));
    }

    /**
     * Add manifest and tmp files to .gitignore.
     *
     * @param string manifestpath
     * @return void
     */
    public function create_gitignore(string $manifestpath):void {
        $manifestdirname = dirname($manifestpath);
        if (!is_file($manifestdirname . '/.gitignore')) {
            $ignore = fopen($manifestdirname . '/.gitignore', 'a');
            $contents = "**/*_question_manifest.json\n**/*_manifest_update.tmp\n";
            fwrite($ignore, $contents);
            fclose($ignore);
        }
    }

    /**
     * Check the git repo containing the manifest file to see if there
     * are any uncommited changes and stop if there are.
     *
     * @param string $fullmanifestpath
     * @return void
     */
    public function check_for_changes($fullmanifestpath) {
        if (!$this->get_arguments()['usegit']) {
            return;
        }
        $this->create_gitignore($fullmanifestpath);
        $manifestdirname = dirname($fullmanifestpath);
        if (chdir($manifestdirname)) {
            exec('git add .'); // Make sure everything changed has been staged.
            exec('git update-index --refresh'); // Removes false positives due to timestamp changes.
            if (exec('git diff-index --quiet HEAD -- || echo "changes"')) {
                echo "There are changes to the repository.\n";
                echo "Either commit these or revert them before proceeding.\n";
                exit;
            }
        } else {
            echo "Cannot find the directory of the manifest file.";
            exit;
        }
    }

    /**
     * Make a copy of the manifest file.
     *
     * @param string $fullmanifestpath
     * @return void
     */
    public function backup_manifest($fullmanifestpath) {
        $manifestdirname = dirname($fullmanifestpath);
        $manifestfilename = basename($fullmanifestpath);
        $backupdir = $manifestdirname . '/manifest_backups';
        if (!file_exists($backupdir)) {
            mkdir($backupdir);
        }
        copy($fullmanifestpath, $backupdir . '/' . date('YmdHis', time()) . '_' . $manifestfilename);
    }

    /**
     * Mockable function that just exits code.
     *
     * Required to stop PHPUnit displaying output after exit.
     *
     * @return void
     */
    public static function call_exit():void {
        exit;
    }

    /**
     * Contact Moodle to check that the user supplied context (either instance names or instanceid)
     * is valid and then confirm with user it's the right one.
     *
     * @param object $activity
     * @param string|null $message Any additional message to display
     * @return object
     */
    public function check_context(object $activity, ?string $message=null):object {
        $activity->listpostsettings['contextonly'] = 1;
        $activity->listcurlrequest->set_option(CURLOPT_POSTFIELDS, $activity->listpostsettings);
        $response = $activity->listcurlrequest->execute();
        $moodlequestionlist = json_decode($response);
        if (is_null($moodlequestionlist)) {
            echo "Broken JSON returned from Moodle:\n";
            echo $response . "\n";
            static::call_exit();
            return new \stdClass(); // Required for PHPUnit.
        } else if (property_exists($moodlequestionlist, 'exception')) {
            echo "{$moodlequestionlist->message}\n";
            if (property_exists($moodlequestionlist, 'debuginfo')) {
                echo "{$moodlequestionlist->debuginfo}\n";
            }
            echo "Failed to get list of questions from Moodle.\n";
            static::call_exit();
            return new \stdClass(); // Required for PHPUnit.
        } else {
            $activityname = get_class($activity);
            echo "\nAbout to {$activityname}:\n";
            echo "Moodle URL: {$activity->moodleurl}\n";
            echo "Context level: {$moodlequestionlist->contextinfo->contextlevel}\n";
            if ($moodlequestionlist->contextinfo->categoryname || $moodlequestionlist->contextinfo->coursename) {
                echo "Instance: {$moodlequestionlist->contextinfo->categoryname}{$moodlequestionlist->contextinfo->coursename}\n";
            }
            if ($moodlequestionlist->contextinfo->modulename) {
                echo "{$moodlequestionlist->contextinfo->modulename}\n";
            }
            echo "Question category: {$moodlequestionlist->contextinfo->qcategoryname}\n";
            if ($message) {
                echo $message;
            }
            static::handle_abort();
        }
        $activity->listpostsettings['contextonly'] = 0;
        $activity->listcurlrequest->set_option(CURLOPT_POSTFIELDS, $activity->listpostsettings);
        return $moodlequestionlist;
    }

    /**
     * Prompt user whether they want to continue.
     *
     * @return void
     */
    public static function handle_abort():void {
        echo "Abort? y/n\n";
        $handle = fopen ("php://stdin", "r");
        $line = fgets($handle);
        if (trim($line) === 'y') {
            static::call_exit();
        }
        fclose($handle);
    }

    /**
     * Given a filepath for a category question file, extract the Moodle
     * category path from the file. (This will vary from the filepath
     * as the filepath will have potentially had characters sanitised.)
     *
     * @param [type] $filename
     * @return string|null $qcategoryname Question category name in format top/cat1/subcat1
     */
    public static function get_question_category_from_file($filename):?string {
        if (!is_file($filename)) {
            echo "\nRequired category file does not exist: {$filename}\n";
            return null;
        }
        $contents = file_get_contents($filename);
        if ($contents === false) {
            echo "\nUnable to access file: {$filename}\n";
            return null;
        }
        $categoryxml = simplexml_load_string($contents);
        if ($categoryxml === false) {
            echo "\nBroken category XML.\n";
            echo "{$filename}.\n";
            return null;
        }
        $qcategoryname = $categoryxml->question->category->text->__toString();
        return $qcategoryname;
    }
}
