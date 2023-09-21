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
 * Unit tests for export_question function of gitsync webservice
 *
 * @package    qbank_gitsync
 * @copyright  2023 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_gitsync;

defined('MOODLE_INTERNAL') || die();

global $CFG;
use advanced_testcase;

/**
 * Test cli_helper function.
 *
 * @covers \gitsync\cli_helper::class
 */
class cli_helper_test extends advanced_testcase {
    /** @var array defining options for a CLI script */
    protected array $options = [
        [
            'longopt' => 'moodleinstance',
            'shortopt' => 'i',
            'description' => 'Key of Moodle instance in $moodleinstances to use. ' .
                             'Should match end of instance URL.',
            'default' => 'edmundlocal',
            'variable' => 'moodleinstance',
            'valuerequired' => true,
        ],
        [
            'longopt' => 'contextlevel',
            'shortopt' => 'l',
            'description' => 'Context in which to place questions. Set to system, coursecategory, course or module',
            'default' => null,
            'variable' => 'contextlevel',
            'valuerequired' => true,
        ],
        [
            'longopt' => 'coursename',
            'shortopt' => 'c',
            'description' => 'Unique course name for course or module context.',
            'default' => null,
            'variable' => 'coursename',
            'valuerequired' => true,
        ],
        [
            'longopt' => 'modulename',
            'shortopt' => 'm',
            'description' => 'Unique (within course) module name for module context.',
            'default' => 'Default module',
            'variable' => 'modulename',
            'valuerequired' => true,
        ],
        [
            'longopt' => 'help',
            'shortopt' => 'h',
            'description' => '',
            'default' => false,
            'variable' => 'help',
            'valuerequired' => false,
        ],
        [
            'longopt' => 'fake',
            'shortopt' => 'f',
            'description' => '',
            'default' => false,
            'variable' => 'fake',
            'valuerequired' => false,
        ]
    ];

    /**
     * Test the defined options are parsed correctly to produce parameters for getopt().
     * @covers \gitsync\cli_helper\parse_options()
     */
    public function test_parse_options(): void {
        $helper = new cli_helper($this->options);
        $options = $helper->parse_options();
        $this->assertEquals($options['shortopts'], 'i:l:c:m:hf');
        $this->assertEquals($options['longopts'],
                            ['moodleinstance:', 'contextlevel:', 'coursename:', 'modulename:', 'help', 'fake']);
    }

    /**
     * Test the correct values are assigned to variables based on command line args and defined defaults.
     * @covers \gitsync\cli_helper\prioritise_options()
     */
    public function test_prioritise_options(): void {
        $helper = new cli_helper($this->options);
        $commandlineargs = [
                            'h' => false,
                            'i' => 'testmoodle',
                            'contextlevel' => 'module',
                            'coursename' => 'Course long',
                            'c' => 'Course short',
                        ];

        $options = $helper->prioritise_options($commandlineargs);
        // Test option without value when set.
        $this->assertEquals($options['help'], true);
        // Test option without value when not set.
        $this->assertEquals($options['fake'], false);
        // Test short option.
        $this->assertEquals($options['moodleinstance'], 'testmoodle');
        // Test long option.
        $this->assertEquals($options['contextlevel'], 'module');
        // Test long option prioritised over short.
        $this->assertEquals($options['coursename'], 'Course long');
        // Test option default returned when not set.
        $this->assertEquals($options['modulename'], 'Default module');
    }

    /**
     * Test the CLI script shows error if invalid context level is used.
     * @covers \gitsync\cli_helper\get_context_level()
     */
    public function test_incorrect_level(): void {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Context level 'wrong' is not valid.");
        cli_helper::get_context_level('wrong');
    }

    /**
     * Check XML formatted properly
     *
     * @return void
     */
    public function test_check_formatting(): void {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><quiz><!-- Unwanted comment --><question><questiontext format="html">' .
            '<text><![CDATA[<p>Paragraph 1<br><ul><li>Item 1</li><li>Item 2</li></ul></p>]]>' .
            '</text></questiontext></question></quiz>';
        $expectedresult = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<quiz>\n  <question>\n    <questiontext format=\"html\">" .
            "\n      <text>\n        <![CDATA[\n        <p>\n          Paragraph 1\n          <br />\n        </p>\n        <ul>" .
            "\n          <li>Item 1\n          </li>\n          <li>Item 2\n          </li>\n        </ul>\n        <p></p>" .
            "\n        ]]>\n      </text>\n    </questiontext>\n  </question>\n</quiz>";
        $result = cli_helper::reformat_question($xml);

        // Output will depend on Tidy being installed.
        if (!function_exists('tidy_repair_string')) {
            $this->assertEquals($result, $xml);
        } else {
            $this->assertEquals($result, $expectedresult);
        }
    }
}
