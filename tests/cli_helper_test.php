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
 * Allows testing of errors that lead to an exit.
 */
class fake_cli_helper extends cli_helper {
    /**
     * Override so ignored during testing
     *
     * @return void
     */
    public static function call_exit():void {
        return;
    }
}

/**
 * Test cli_helper function.
 * @group qbank_gitsync
 *
 * Some tests are also done in import_repo_test where mocking has
 * already been set up.
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
        ],
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
        fake_cli_helper::get_context_level('wrong');
        $this->expectOutputRegex("/^Context level 'wrong' is not valid.$/");
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

    /**
     * Manifest path name creation
     * @covers \gitsync\cli_helper\get_manifest_path()
     */
    public function test_manifest_path(): void {
        $helper = new cli_helper($this->options);
        // Module level, including replacements.
        $manifestpath = $helper->get_manifest_path('moodleinstanc<name', 'module', 'cat<goryname',
                                                   'cours<nam>', 'Modul<name', 'directoryname');
        $this->assertEquals('directoryname/moodleinstanc-name_module_cours-nam-_modul-name' . cli_helper::MANIFEST_FILE,
                            $manifestpath);
        // Category level, including replacements.
        $manifestpath = $helper->get_manifest_path('moodleinstanc<name', 'coursecategory', 'cat<goryname',
                                                   'cours<nam<', 'Modul<name', 'directoryname');
        $this->assertEquals('directoryname/moodleinstanc-name_coursecategory_cat-goryname' . cli_helper::MANIFEST_FILE,
                            $manifestpath);
        // Course level, including replacements.
        $manifestpath = $helper->get_manifest_path('moodleinstanc<name', 'course', 'cat<goryname',
                                                   'cours<nam>', 'Modul<name', 'directoryname');
        $this->assertEquals('directoryname/moodleinstanc-name_course_cours-nam-' . cli_helper::MANIFEST_FILE,
                            $manifestpath);
        // System level.
        $manifestpath = $helper->get_manifest_path('moodleinstanc<name', 'system', 'cat<goryname',
                                                    'cours<nam<', 'Modul<name', 'directoryname');
        $this->assertEquals('directoryname/moodleinstanc-name_system' . cli_helper::MANIFEST_FILE,
                            $manifestpath);
        // Shortening.
        // Module level, including replacements.
        $manifestpath = $helper->get_manifest_path('moodleinstanc<name', 'module', 'cat<goryname',
            'cours<nam<thatisverylongandsoneedstobeshortened and has a space in it just to make sure',
            'Modulename that is also very long and we want to chop up a bit as well hopefully', 'directoryname');
        $this->assertEquals('directoryname/moodleinstanc-name_module_cours-nam-thatisverylongandsoneedstobeshortened' .
                            '-an_modulename-that-is-also-very-long-and-we-want-to-c' . cli_helper::MANIFEST_FILE,
                            $manifestpath);
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_token(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = [];
        $helper->validate_and_clean_args();
        $this->expectOutputRegex('/need a security token/');
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_subdirectory(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'system',
                                     'subdirectory' => 'cat1', 'questioncategoryid' => 3,
                                    ];
        $helper->validate_and_clean_args();
        $this->expectOutputRegex('/use only one/');
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_subdirectory_format(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'system', 'subdirectory' => 'cat1/subcat'];
        $helper->validate_and_clean_args();
        $this->assertEquals('top/cat1/subcat', $helper->processedoptions['subdirectory']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'system', 'subdirectory' => '/top/cat1'];
        $helper->validate_and_clean_args();
        $this->assertEquals('top/cat1', $helper->processedoptions['subdirectory']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'system', 'subdirectory' => '/top/cat1/'];
        $helper->validate_and_clean_args();
        $this->assertEquals('top/cat1', $helper->processedoptions['subdirectory']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'system', 'subdirectory' => 'top/cat1/'];
        $helper->validate_and_clean_args();
        $this->assertEquals('top/cat1', $helper->processedoptions['subdirectory']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'system', 'subdirectory' => '/cat1/'];
        $helper->validate_and_clean_args();
        $this->assertEquals('top/cat1', $helper->processedoptions['subdirectory']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'system', 'subdirectory' => ''];
        $helper->validate_and_clean_args();
        $this->assertEquals('top', $helper->processedoptions['subdirectory']);
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_subcategory_format(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'system', 'subcategory' => 'cat1/subcat'];
        $helper->validate_and_clean_args();
        $this->assertEquals('top/cat1/subcat', $helper->processedoptions['subcategory']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'system', 'subcategory' => '/top/cat1'];
        $helper->validate_and_clean_args();
        $this->assertEquals('top/cat1', $helper->processedoptions['subcategory']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'system', 'subcategory' => '/top/cat1/'];
        $helper->validate_and_clean_args();
        $this->assertEquals('top/cat1', $helper->processedoptions['subcategory']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'system', 'subcategory' => 'top/cat1/'];
        $helper->validate_and_clean_args();
        $this->assertEquals('top/cat1', $helper->processedoptions['subcategory']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'system', 'subcategory' => '/cat1/'];
        $helper->validate_and_clean_args();
        $this->assertEquals('top/cat1', $helper->processedoptions['subcategory']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'system', 'subcategory' => ''];
        $helper->validate_and_clean_args();
        $this->assertEquals('top', $helper->processedoptions['subcategory']);
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_manifestpath(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'system', 'manifestpath' => '/path/subpath/'];
        $helper->validate_and_clean_args();
        $this->assertEquals('path/subpath', $helper->processedoptions['manifestpath']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'system', 'manifestpath' => '/path/subpath/',
                                     'coursename' => 'course1', 'instanceid' => '2',
                                    ];
        $helper->validate_and_clean_args();
        $this->expectOutputRegex('/specified a manifest file/');

    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_instanceid(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'system',
                                     'coursename' => 'course1', 'instanceid' => '2',
                                    ];
        $helper->validate_and_clean_args();
        $this->expectOutputRegex('/If instanceid is supplied/');

    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_contextlevel_system(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'system',
                                     'coursename' => 'course1', 'instanceid' => '2',
                                    ];
        $helper->validate_and_clean_args();
        $this->expectOutputRegex('/You have specified system level.*not needed/');

    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_contextlevel_coursecategory(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'coursecategory',
                                     'coursecategory' => 'cat1',
                                     'coursename' => 'course1', 'modulename' => '2',
                                    ];
        $helper->validate_and_clean_args();
        $this->expectOutputRegex('/^\nYou have specified course category level.*not needed.\n$/s');

    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_contextlevel_course(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'course',
                                     'coursecategory' => 'cat1',
                                     'coursename' => 'course1', 'modulename' => '2',
                                    ];
        $helper->validate_and_clean_args();
        $this->expectOutputRegex('/^\nYou have specified course level.*not needed.\n$/s');

    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_contextlevel_modul(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'module',
                                     'coursecategory' => 'cat1',
                                     'coursename' => 'course1', 'modulename' => '2',
                                    ];
        $helper->validate_and_clean_args();
        $this->expectOutputRegex('/^\nYou have specified module level.*not needed.\n$/s');

    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_contextlevel_coursecategory_missing(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'coursecategory'];
        $helper->validate_and_clean_args();
        $this->expectOutputRegex('/^\nYou have specified course category level.*instanceid\).\n$/s');
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_contextlevel_course_missing(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'course'];
        $helper->validate_and_clean_args();
        $this->expectOutputRegex('/^\nYou have specified course level.*instanceid\).\n$/s');
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_contextlevel_module_missing(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'module', 'modulename' => 'mod1'];
        $helper->validate_and_clean_args();
        $this->expectOutputRegex('/^\nYou have specified module level.*instanceid\).\n$/s');
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_contextlevel_coursecategory_instanceid(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'coursecategory', 'instanceid' => 3];
        $helper->validate_and_clean_args();
        $this->expectOutputString('');
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_contextlevel_course_instanceid(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'course', 'instanceid' => 3];
        $helper->validate_and_clean_args();
        $this->expectOutputString('');
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_contextlevel_module_instanceid(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'module', 'instanceid' => 3];
        $helper->validate_and_clean_args();
        $this->expectOutputString('');
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_contextlevel_missing(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X'];
        $helper->validate_and_clean_args();
        $this->expectOutputRegex('/You have not specified context/');
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_contextlevel_manifestpath(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'manifestpath' => 'path/subpath'];
        $helper->validate_and_clean_args();
        $this->expectOutputString('');
    }


    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_contextlevel_wrong(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'contextlevel' => 'lama'];
        $helper->validate_and_clean_args();
        $this->expectOutputRegex('/Contextlevel should be/');
    }
}
