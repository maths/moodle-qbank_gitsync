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
        $parsed = $this->parse_options();
        $shortopts = $parsed['shortopts'];
        $longopts = $parsed['longopts'];
        $commandlineargs = getopt($shortopts, $longopts);
        return $this->prioritise_options($commandlineargs);
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
    public function showhelp() {
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
}
