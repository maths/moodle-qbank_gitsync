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
    protected ?array $processedoptions = null;
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
                    $variables[$variablename] = false;
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
                throw new \Exception("Context level '{$level}' is not valid.");
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
                $filenamemod = $filenamemod . '_' . $coursecategory;
                break;
            case 'course':
                $filenamemod = $filenamemod . '_' . $coursename;
                break;
            case 'module':
                $filenamemod = $filenamemod . '_' . $coursename . '_' . $modulename;
                break;
        }

        return $directory . '/' . $moodleinstance . $filenamemod . self::MANIFEST_FILE;
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
        $tempfile = fopen($tempfilepath, 'r');
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
                    $questionentry->version = $questioninfo->version;
                    $questionentry->exportedversion = $questioninfo->version;
                    if (isset($questioninfo->moodlecommit)) {
                        $questionentry->moodlecommit = $questioninfo->moodlecommit;
                    }
                    array_push($manifestcontents->questions, $questionentry);
                } else {
                    $existingentries["{$questioninfo->questionbankentryid}"]->version = $questioninfo->version;
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
                    $manifestcontents->context->qcategoryname = $questioninfo->qcategoryname;
                    $manifestcontents->context->moodleurl = $moodleurl;
                }
            }
        }
        file_put_contents($manifestpath, json_encode($manifestcontents));

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
            'output-xhtml' => true
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
            'indent-cdata' => true
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
     * @param object activity e.g. import_repo
     * @return void
     */
    public function commit_hash_update(object $activity):void {
        foreach ($activity->manifestcontents->questions as $question) {
            $commithash = exec('git log -n 1 --pretty=format:%H -- "' . substr($question->filepath, 1) . '"');
            $question->currentcommit = $commithash;
        }
        file_put_contents($activity->manifestpath, json_encode($activity->manifestcontents));
    }

    /**
     * Updates the manifest file with the current commit hashes of question files in the repo.
     * Used on initial repo creation so also sets the moodle commit to be the same.
     *
     * @param object activity e.g. create_repo
     * @return void
     */
    public function commit_hash_setup(object $activity):void {
        $manifestdirname = dirname($activity->manifestpath);
        chdir($manifestdirname);
        exec('touch .gitignore');
        exec("printf '%s\n' '**/*_question_manifest.json' '**/*_manifest_update.tmp' > .gitignore");
        exec("git add .");
        exec('git commit -m "Initial Commit"');
        foreach ($activity->manifestcontents->questions as $question) {
            $commithash = exec('git log -n 1 --pretty=format:%H -- "' . substr($question->filepath, 1) . '"');
            $question->currentcommit = $commithash;
            $question->moodlecommit = $commithash;
        }
        file_put_contents($activity->manifestpath, json_encode($activity->manifestcontents));
    }

    /**
     * Check the git repo containing the manifest file to see if there
     * are any uncommited changes and stop if there are.
     *
     * @param string $fullmanifestpath
     * @return void
     */
    public function check_for_changes($fullmanifestpath) {
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
}
