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
     * QUIZ_FILE - File name ending for quiz structure file.
     */
    public const QUIZ_FILE = '_quiz.json';
    /**
     * TEMP_MANIFEST_FILE - File name ending for temporary manifest file.
     * Appended to name of moodle instance.
     */
    public const TEMP_MANIFEST_FILE = '_manifest_update.tmp';
    /**
     * BAD_CHARACTERS - Characters to remove for filename sanitisation
     */
    public const BAD_CHARACTERS = '/[\/\\\?\%*:|"<> .$!]+/';
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
        $argcount = count($commandlineargs);
        if (!isset($commandlineargs['w'])) {
            echo "\nProcessed {$argcount} valid command line argument" .
                    (($argcount !== 1) ? 's' : '') . ".\n";
        }
        $this->processedoptions = $this->prioritise_options($commandlineargs);
        if (!empty($this->processedoptions['help'])) {
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
        if (!isset($cliargs['usegit'])) {
            echo "\nAre you using Git? You will need to specify true or false for --usegit.\n";
            static::call_exit();
        }
        if (!isset($cliargs['token'])) {
            echo "\nYou will need a security token (--token).\n";
            static::call_exit();
        }
        if (isset($cliargs['directory'])) {
            $cliargs['directory'] = $this->trim_slashes($cliargs['directory']);
        }
        if (isset($cliargs['manifestpath'])) {
            if (isset($cliargs['directory']) && strlen($cliargs['directory']) > 0 ) {
                echo "\nYou have supplied a manifest file path (possibly as a default in your config file) " .
                     "and a directory. Please use only one.\n";
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
                $cliargs['subdirectory'] = null;
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
                echo "\nYou have specified a manifest file (possibly as a default in your config file). " .
                        "Contextlevel, instance id, course name, module name and/or course category are not needed. " .
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
        if (isset($cliargs['ignorecat'])) {
            // Allow escaping of forward slash at start in Windows.
            if (strlen($cliargs['ignorecat']) > 1 && substr($cliargs['ignorecat'], 0, 2) === '//') {
                $cliargs['ignorecat'] = substr($cliargs['ignorecat'], 1);
            }
            if (@preg_match($cliargs['ignorecat'], 'zzzzzzzz') === false) {
                echo "\nThere is a problem with your regular expression for ignoring categories:\n";
                echo error_get_last()["message"] . "\n";
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
        if (!(isset($cliargs['manifestpath']) || isset($cliargs['quizmanifestpath'])
                || (isset($cliargs['nonquizmanifestpath']) && isset($cliargs['instanceid']))) && !isset($cliargs['contextlevel'])) {
            echo "\nYou have not specified context. " .
                 "You must specify context level (--contextlevel) unless " .
                 "using a function where this information can be read from a manifest file, in which case " .
                 "you could set a manifest path (--manifestpath) instead. If using exportrepofrommoodle, you " .
                 "must set manifest path only. If dealing with export of quizzes, you must specify --quizmanifestpath. " .
                 "If you still see this message, you may be using invalid arguments.\n";
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
    public function trim_slashes(string $path): string {
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
     * Combine sanitised output of getopt() with defaults.
     *
     * @param array $commandlineargs output from getopt()
     * @return array of options and values
     */
    public function prioritise_options($commandlineargs): array {
        $variables = [];

        foreach ($this->options as $option) {
            $variablename = $option['variable'];
            if ($option['valuerequired']) {
                if (isset($option['hidden'])) {
                    $variables[$variablename] = $option['default'];
                } else if (isset($commandlineargs[$option['longopt']])) {
                    $variables[$variablename] = $commandlineargs[$option['longopt']];
                } else if (isset($commandlineargs[$option['shortopt']])) {
                    $variables[$variablename] = $commandlineargs[$option['shortopt']];
                } else {
                    $variables[$variablename] = $option['default'];
                }
                if (in_array($variablename, ['usegit'])) {
                    $variables[$variablename] = ($variables[$variablename] === 'true') ? true : $variables[$variablename];
                    $variables[$variablename] = ($variables[$variablename] === 'false') ? false : $variables[$variablename];
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
    public function show_help(): void {
        foreach ($this->options as $option) {
            if (!isset($option['hidden'])) {
                echo "-{$option['shortopt']} --{$option['longopt']}  \t{$option['description']}\n";
            }
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
     * Create manifest path.
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
                            ?string $coursename, ?string $modulename, string $directory): string {
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
     * Create quiz structure path.
     *
     * @param string|null $modulename
     * @param string $directory
     * @return string
     */
    public static function get_quiz_structure_path(string $modulename, string $directory): string {
        $filename = substr($modulename, 0, 100);
        $filename = $directory . '/' .
                    preg_replace(self::BAD_CHARACTERS, '-', strtolower($filename)) .
                    self::QUIZ_FILE;
        return $filename;
    }

    /**
     * Create quiz directory name.
     *
     * @param string $basedirectory
     * @param string $directory
     * @return string
     */
    public static function get_quiz_directory(string $basedirectory, string $quizname): string {
        $quizname = substr($quizname, 0, 100);
        $directoryname = $basedirectory . '_quiz_' .
                    preg_replace(self::BAD_CHARACTERS, '-', strtolower($quizname));
        return $directoryname;
    }

    /**
     * Create manifest file from temporary file.
     *
     * @param object $manifestcontents \stdClass Current contents of manifest file
     * @param string $tempfilepath
     * @param string $manifestpath
     * @param bool $showupdated
     * @return object
     */
    public static function create_manifest_file(object $manifestcontents,
                                                string $tempfilepath,
                                                string $manifestpath,
                                                bool $showupdated=true): object {
        // Read in temp file a question at a time, process and add to manifest.
        // No actual processing at the moment so could simplify to write straight
        // to manifest in the first place if no processing materialises.
        $manifestdir = dirname($manifestpath);
        $manifestdir = str_replace( '\\', '/', $manifestdir);
        $tempfile = fopen($tempfilepath, 'r');
        $updatedcount = 0;
        $addedcount = 0;
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
                    $addedcount++;
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
                    $updatedcount++;
                    $existingentries["{$questioninfo->questionbankentryid}"]->importedversion = $questioninfo->version;
                    if (isset($questioninfo->moodlecommit)) {
                        $existingentries["{$questioninfo->questionbankentryid}"]->moodlecommit = $questioninfo->moodlecommit;
                    }
                }
            }
        }
        echo "\nAdded {$addedcount} question" . (($addedcount !== 1) ? 's' : '') . ".\n";
        if ($showupdated) {
            echo "Updated {$updatedcount} question" . (($updatedcount !== 1) ? 's' : '') . ".\n";
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
     * Remove unwanted comments from question.
     *
     * @param string $question original question XML
     * @return string tidied question XML
     */
    public static function reformat_question(string $question): string {
        $quiz = simplexml_load_string($question);
        if ($quiz === false) {
            throw new \Exception('Broken XML');
        }
        $questionnode = $quiz->question;
        $cleanedquiz = simplexml_load_string('<?xml version="1.0" encoding="UTF-8"?><quiz>&#10;  ' .
                                                $questionnode->asXML() . '&#10;</quiz>');
        $result = $cleanedquiz->asXML();
        return $result;
    }

    /**
     * Updates the manifest file with the current commit hashes of question files in the repo.
     *
     * @param object $activity e.g. import_repo
     * @return void
     */
    public function commit_hash_update(object $activity): void {
        if (!$this->get_arguments()['usegit']) {
            return;
        }
        foreach ($activity->manifestcontents->questions as $question) {
            $commithash = exec('git log -n 1 --pretty=format:%H -- "' . substr($question->filepath, 1) . '"');
            if ($commithash) {
                $question->currentcommit = $commithash;
            }
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
    public function commit_hash_setup(object $activity): void {
        if (!$this->get_arguments()['usegit']) {
            return;
        }
        $this->create_gitignore($activity->manifestpath);
        $manifestdirname = dirname($activity->manifestpath);
        chdir($manifestdirname);
        if (empty($this->get_arguments()['subcall'])) {
            exec("git add --all");
            exec('git commit -m "Initial Commit - ' . basename($activity->manifestpath)  . '"');
        }
        foreach ($activity->manifestcontents->questions as $question) {
            $commithash = exec('git log -n 1 --pretty=format:%H -- "' . substr($question->filepath, 1) . '"');
            if ($commithash) {
                $question->currentcommit = $commithash;
                $question->moodlecommit = $commithash;
            }
        }
        // Happens last so no need to abort on failure.
        file_put_contents($activity->manifestpath, json_encode($activity->manifestcontents));
    }

    /**
     * Can be used to tidy questions in a repo.
     * Requires Michael Kallweit's Python prettify script:
     * https://github.com/m-r-k/stack2023/blob/main/Preparing%20questions%20for%20version%20control/prettify_cli.py
     *
     * @param object $activity e.g. create_repo
     * @return void
     */
    public function tidy_repo_xml(object $activity): void {
        if ($activity->subdirectory) {
            $subdirectory = $activity->directory . '/' . $activity->subdirectory;
        } else {
            $subdirectory = $activity->directory;
        }
        $subdirectoryiterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($subdirectory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($subdirectoryiterator as $repoitem) {
            if ($repoitem->isFile()) {
                if (pathinfo($repoitem, PATHINFO_EXTENSION) === 'xml') {
                    $path = $repoitem->getPathname();
                    exec('python3 prettify_cli.py ' . $path);
                }
            }
        }
    }

    /**
     * Add manifest and tmp files to .gitignore
     *
     * @param string $manifestpath
     * @return void
     */
    public function create_gitignore(string $manifestpath): void {
        if (!$this->get_arguments()['usegit']) {
            return;
        }
        $manifestdirname = dirname($manifestpath);
        if (!is_file($manifestdirname . '/.gitignore')) {
            $ignore = fopen($manifestdirname . '/.gitignore', 'a');

            $contents = "**/*" . self::MANIFEST_FILE . "\n**/*" .
                self::TEMP_MANIFEST_FILE . "\n";
            fwrite($ignore, $contents);
            fclose($ignore);
        }
    }

    /**
     * Check if the repository has been initialised
     *
     * @param string $fullmanifestpath
     * @return void
     */
    public function check_repo_initialised(string $fullmanifestpath): void {
        if (!$this->get_arguments()['usegit']) {
            return;
        }
        $manifestdirname = dirname($fullmanifestpath);
        if (chdir($manifestdirname)) {
            // Will give path of .git if in repo or error.
            if (substr(exec('git rev-parse --git-dir'), -4) !== '.git') {
                echo "The Git repository has not been initialised.\n";
                exit;
            }
        } else {
            echo "Cannot find the directory of the manifest file.\n";
            exit;
        }
    }

    /**
     * Create directory.
     *
     * @param string $directory
     * @return string updated directory name
     */
    public function create_directory(string $directory): string {
        $basename = $directory;
        $i = 0;
        while (is_dir($directory)) {
            $i++;
            $directory = $basename . '_' . $i;
        }
        mkdir($directory);
        return $directory;
    }

    /**
     * Check the git repo containing the manifest file to see if there
     * are any uncommited changes and stop if there are.
     *
     * @param string $fullmanifestpath
     * @return void
     */
    public function check_for_changes(string $fullmanifestpath): void {
        if (!$this->get_arguments()['usegit'] || !empty($this->get_arguments()['subcall'])) {
            return;
        }
        $this->check_repo_initialised($fullmanifestpath);
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
            echo "Cannot find the directory of the manifest file.\n";
            exit;
        }
    }

    /**
     * Make a copy of the manifest file.
     *
     * @param string $fullmanifestpath
     * @return void
     */
    public function backup_manifest(string $fullmanifestpath): void {
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
    public static function call_exit(): void {
        exit;
    }

    /**
     * Contact Moodle to check that the user supplied context (either instance names or instanceid)
     * is valid and then confirm with user it's the right one.
     *
     * @param object $activity
     * @param bool $defaultwarning If true, display using subcat default warning
     * @param bool $silent If true, don't display returned info
     * @return object
     */
    public function check_context(object $activity, bool $defaultwarning=false, bool $silent=false): object {
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
        } else if (!$silent && empty($arguments['subcall'])) {
            $activityname = get_class($activity);
            switch ($activityname) {
                case 'qbank_gitsync\export_repo':
                    echo "\nPreparing to update your local repository from Moodle:\n";
                    break;
                case 'qbank_gitsync\import_repo':
                    echo "\nPreparing to update Moodle from your local repository:\n";
                    break;
                case 'qbank_gitsync\create_repo':
                    echo "\nPreparing to create a local repository from Moodle:\n";
                    break;
                default:
                    echo "\nPreparing to {$activityname}:\n";
                    break;
            }
            echo "Moodle URL: {$activity->moodleurl}\n";
            echo "Context level: {$moodlequestionlist->contextinfo->contextlevel}\n";
            if ($moodlequestionlist->contextinfo->categoryname) {
                echo "Course category: {$moodlequestionlist->contextinfo->categoryname}\n";
            }
            if ($moodlequestionlist->contextinfo->coursename) {
                echo "Course: {$moodlequestionlist->contextinfo->coursename}\n";
            }
            if ($moodlequestionlist->contextinfo->modulename) {
                echo "Quiz: {$moodlequestionlist->contextinfo->modulename}\n";
            }
            if (isset($activity->ignorecat)) {
                echo "Ignoring categories (and their descendants) in form: {$activity->ignorecat}\n";
            }
            if (isset($activity->subdirectory)) {
                echo "Question subdirectory: {$activity->subdirectory}\n";
                if ($defaultwarning) {
                    echo "\nUsing default subdirectory from manifest file.\n";
                    echo "Set --subdirectory to override.\n";
                }
            } else {
                echo "Question category: {$moodlequestionlist->contextinfo->qcategoryname}\n";
            }
            if ($defaultwarning && !isset($activity->subdirectory)) {
                echo "\nUsing default question category from manifest file.\n";
                echo "Set --subcategory or --questioncategoryid to override.\n";
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
    public static function handle_abort(): void {
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
    public static function get_question_category_from_file($filename): ?string {
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
