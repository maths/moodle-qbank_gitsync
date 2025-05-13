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
use org\bovigo\vfs\vfsStream;

/**
 * Allows testing of errors that lead to an exit.
 */
class fake_cli_helper extends cli_helper {
    /**
     * Override so ignored during testing
     *
     * @return void
     */
    public static function call_exit(): void {
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
final class cli_helper_test extends advanced_testcase {
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
        [
            'longopt' => 'usegit',
            'shortopt' => 'u',
            'description' => 'Is the repo controlled using Git?',
            'default' => true,
            'variable' => 'usegit',
            'valuerequired' => true,
        ],
        [
            'longopt' => 'hidey',
            'shortopt' => 'q',
            'description' => 'Not settable',
            'default' => 'Sneaky',
            'variable' => 'hidey',
            'valuerequired' => true,
            'hidden' => true,
        ],
    ];

    /**
     * Test the defined options are parsed correctly to produce parameters for getopt().
     * @covers \gitsync\cli_helper\parse_options()
     */
    public function test_parse_options(): void {
        $helper = new cli_helper($this->options);
        $options = $helper->parse_options();
        $this->assertEquals($options['shortopts'], 'i:l:c:m:hfu:q:');
        $this->assertEquals($options['longopts'],
                            ['moodleinstance:', 'contextlevel:', 'coursename:',
                            'modulename:', 'help', 'fake',
                            'usegit:', 'hidey:']);
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
                            'usegit' => 'false',
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
        // Test usegit is false when command line set to 'false.
        $this->assertEquals($options['usegit'], false);
        // Test hidden option set to default.
        $this->assertEquals($options['hidey'], 'Sneaky');
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
        $xml = '<quiz><!-- Unwanted comment --><question><questiontext format="html">' .
            '<text><![CDATA[<p>Paragraph 1<br><ul><li>Item 1</li><li><!-- Wanted comment -->Item 2</li></ul></p>]]>' .
            '</text></questiontext></question></quiz>';
        $expectedresult = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<quiz>\n  <question><questiontext format=\"html\">" .
            "<text><![CDATA[<p>Paragraph 1<br><ul><li>Item 1</li><li><!-- Wanted comment -->Item 2</li></ul></p>]]>" .
            "</text></questiontext></question>\n</quiz>\n";
        $result = cli_helper::reformat_question($xml);
        $this->assertEquals($result, $expectedresult);
    }

    /**
     * Manifest path name creation
     * @covers \gitsync\cli_helper\get_manifest_path()
     */
    public function test_manifest_path(): void {
        $helper = new cli_helper($this->options);
        // Module level, including replacements.
        $manifestpath = $helper->get_manifest_path('mood$l!einstanc<name', 'module', 'cat<goryname',
                                                   'cours<nam>', 'Modul<name', 'directoryname');
        $this->assertEquals('directoryname/mood-l-einstanc-name_module_cours-nam-_modul-name' . cli_helper::MANIFEST_FILE,
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
     * Manifest path name creation
     * @covers \gitsync\cli_helper\get_manifest_path_targeted()
     */
    public function test_manifest_path_targeted(): void {
        $helper = new cli_helper($this->options);
        // Module level, including replacements.
        $manifestpath = $helper->get_manifest_path_targeted('mood$l!einstanc<name', 'module', 'cat<goryname',
                                                   'cours<nam>', 'Modul<name', 'top/Level 1/Level 2', '88',
                                                   '/Dir 1/Dir 2/Dir 3', 'directoryname');
        $this->assertEquals('directoryname/mood-l-einstanc-name_module_cours-nam-_modul-name_dir-2_dir-3_top-level-1-level-2_88'
                             . cli_helper::MANIFEST_FILE, $manifestpath);
        // Category level, including replacements.
        $manifestpath = $helper->get_manifest_path_targeted('moodleinstanc<name', 'coursecategory', 'cat<goryname',
                                                   'cours<nam<', 'Modul<name', 'top/Level 1/Level 2', '88',
                                                   '/Dir 1/Dir 2/Dir 3', 'directoryname');
        $this->assertEquals('directoryname/moodleinstanc-name_coursecategory_cat-goryname_dir-2_dir-3_top-level-1-level-2_88'
                             . cli_helper::MANIFEST_FILE, $manifestpath);
        // Course level, including replacements.
        $manifestpath = $helper->get_manifest_path_targeted('moodleinstanc<name', 'course', 'cat<goryname',
                                                   'cours<nam>', 'Modul<name', 'top/Level 1/Level 2', '88',
                                                   '/Dir 1/Dir 2/Dir 3', 'directoryname');
        $this->assertEquals('directoryname/moodleinstanc-name_course_cours-nam-_dir-2_dir-3_top-level-1-level-2_88'
                             . cli_helper::MANIFEST_FILE, $manifestpath);
        // System level.
        $manifestpath = $helper->get_manifest_path_targeted('moodleinstanc<name', 'system', 'cat<goryname',
                                            'cours<nam<', 'Modul<name', 'top/Level 1/Level 2', '88',
                                            '/Dir 1/Dir 2/Dir 3', 'directoryname');
        $this->assertEquals('directoryname/moodleinstanc-name_system_dir-2_dir-3_top-level-1-level-2_88'
                             . cli_helper::MANIFEST_FILE, $manifestpath);
        // Shortening.
        // Module level, including replacements.
        $manifestpath = $helper->get_manifest_path_targeted('moodleinstanc<name', 'module', 'cat<goryname',
            'cours<nam<thatisverylongandsoneedstobeshortened and has a space in it just to make sure',
            'Modulename that is also very long and we want to chop up a bit as well hopefully',
            'top/Level 1/Level 2 with a very long category name that needs to be cut', '88',
            '/Dir 1/Dir 2 honestly who makes their directories this long/Dir 3 and this one too its bound to cause problems',
            'directoryname');
        $this->assertEquals('directoryname/moodleinstanc-name_module_cours-nam-thatisverylongandson' .
                            '_modulename-that-is-also-very-l_dir-2-honestly-who-makes-_dir-3-and-this-one-too-it_' .
                            'top-level-1-level-2-with-a-very-long-category-name_88' . cli_helper::MANIFEST_FILE,
                            $manifestpath);

        // Module level, top subdirectory only.
        $manifestpath = $helper->get_manifest_path_targeted('mood$l!einstanc<name', 'module', 'cat<goryname',
                                                   'cours<nam>', 'Modul<name', 'top/Level 1/Level 2', '88',
                                                   'top', 'directoryname');
        $this->assertEquals('directoryname/mood-l-einstanc-name_module_cours-nam-_modul-name_top_top-level-1-level-2_88'
                             . cli_helper::MANIFEST_FILE, $manifestpath);
    }

    /**
     * Quiz structure path name creation
     * @covers \gitsync\cli_helper\get_quiz_structure_path()
     */
    public function test_quiz_structure_path(): void {
        $helper = new cli_helper($this->options);
        // Module level, including replacements.
        $datapath = $helper->get_quiz_structure_path('Modul<name', 'directoryname');
        $this->assertEquals('directoryname/modul-name' . cli_helper::QUIZ_FILE, $datapath);
    }

    /**
     * Quiz directory name creation
     * @covers \gitsync\cli_helper\get_quiz_directory()
     */
    public function test_get_quiz_directory(): void {
        $helper = new cli_helper($this->options);
        // Module level, including replacements.
        $quizdir = $helper->get_quiz_directory('directoryname', 'Modul<name');
        $this->assertEquals('directoryname_quiz_modul-name', $quizdir);
    }

    /**
     * Create directory
     * @covers \gitsync\cli_helper\get_quiz_directory()
     */
    public function test_create_directory(): void {
        global $CFG;
        $root = vfsStream::setup();
        vfsStream::copyFromFileSystem($CFG->dirroot . '/question/bank/gitsync/testrepoparent/', $root);
        $rootpath = vfsStream::url('root');
        $helper = new cli_helper($this->options);
        // Module level, including replacements.
        $quizdir = $helper->get_quiz_directory($rootpath . '/below', 'Modul<name');
        $helper->create_directory($quizdir);
        $helper->create_directory($quizdir);
        $helper->create_directory($quizdir);
        $this->assertEquals(true, is_dir($quizdir));
        $this->assertEquals(true, is_dir($quizdir . '_1'));
        $this->assertEquals(true, is_dir($quizdir . '_2'));
        $this->assertEquals(false, is_dir($quizdir . '_3'));
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
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'system',
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
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'system', 'subdirectory' => 'cat1/subcat'];
        $helper->validate_and_clean_args();
        $this->assertEquals('top/cat1/subcat', $helper->processedoptions['subdirectory']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'system', 'subdirectory' => '/top/cat1'];
        $helper->validate_and_clean_args();
        $this->assertEquals('top/cat1', $helper->processedoptions['subdirectory']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'system', 'subdirectory' => '/top/cat1/'];
        $helper->validate_and_clean_args();
        $this->assertEquals('top/cat1', $helper->processedoptions['subdirectory']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'system', 'subdirectory' => 'top/cat1/'];
        $helper->validate_and_clean_args();
        $this->assertEquals('top/cat1', $helper->processedoptions['subdirectory']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'system', 'subdirectory' => '/cat1/'];
        $helper->validate_and_clean_args();
        $this->assertEquals('top/cat1', $helper->processedoptions['subdirectory']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'system', 'subdirectory' => ''];
        $helper->validate_and_clean_args();
        $this->assertEquals(null, $helper->processedoptions['subdirectory']);
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_subcategory_format(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'system', 'subcategory' => 'cat1/subcat'];
        $helper->validate_and_clean_args();
        $this->assertEquals('top/cat1/subcat', $helper->processedoptions['subcategory']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'system', 'subcategory' => '/top/cat1'];
        $helper->validate_and_clean_args();
        $this->assertEquals('top/cat1', $helper->processedoptions['subcategory']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'system', 'subcategory' => '/top/cat1/'];
        $helper->validate_and_clean_args();
        $this->assertEquals('top/cat1', $helper->processedoptions['subcategory']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'system', 'subcategory' => 'top/cat1/'];
        $helper->validate_and_clean_args();
        $this->assertEquals('top/cat1', $helper->processedoptions['subcategory']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'system', 'subcategory' => '/cat1/'];
        $helper->validate_and_clean_args();
        $this->assertEquals('top/cat1', $helper->processedoptions['subcategory']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'system', 'subcategory' => ''];
        $helper->validate_and_clean_args();
        $this->assertEquals('top', $helper->processedoptions['subcategory']);
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_manifestpath(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'system',
                                     'manifestpath' => '/path/subpath/'];
        $helper->validate_and_clean_args();
        $this->assertEquals('path/subpath', $helper->processedoptions['manifestpath']);

        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'system',
                                     'manifestpath' => '/path/subpath/',
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
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'system',
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
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'system',
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
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'coursecategory',
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
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'course',
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
    public function test_validation_contextlevel_module(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'module',
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
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'coursecategory'];
        $helper->validate_and_clean_args();
        $this->expectOutputRegex('/^\nYou have specified course category level.*instanceid\).\n$/s');
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_contextlevel_course_missing(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'course'];
        $helper->validate_and_clean_args();
        $this->expectOutputRegex('/^\nYou have specified course level.*instanceid\).\n$/s');
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_contextlevel_module_missing(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'module', 'modulename' => 'mod1'];
        $helper->validate_and_clean_args();
        $this->expectOutputRegex('/^\nYou have specified module level.*instanceid\).\n$/s');
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_contextlevel_coursecategory_instanceid(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'coursecategory', 'instanceid' => 3];
        $helper->validate_and_clean_args();
        $this->expectOutputString('');
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_contextlevel_course_instanceid(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'course', 'instanceid' => 3];
        $helper->validate_and_clean_args();
        $this->expectOutputString('');
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_contextlevel_module_instanceid(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'module', 'instanceid' => 3];
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
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'manifestpath' => 'path/subpath'];
        $helper->validate_and_clean_args();
        $this->expectOutputString('');
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_contextlevel_wrong(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'contextlevel' => 'lama'];
        $helper->validate_and_clean_args();
        $this->expectOutputRegex('/Contextlevel should be/');
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_ignorecat(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'manifestpath' => 'path/subpath', 'ignorecat' => '/hello/'];
        $helper->validate_and_clean_args();
        $this->expectOutputString('');
        $this->assertEquals('/hello/', $helper->processedoptions['ignorecat']);
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_ignorecat_error(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'manifestpath' => 'path/subpath', 'ignorecat' => '/hello'];
        @$helper->validate_and_clean_args();
        $this->expectOutputRegex('/problem with your regular expression/');
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_validation_ignorecat_replace(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'usegit' => true, 'manifestpath' => 'path/subpath',
                                     'ignorecat' => '//hello\//', ];
        $helper->validate_and_clean_args();
        $this->expectOutputString('');
        $this->assertEquals('/hello\//', $helper->processedoptions['ignorecat']);
    }

    /**
     * Validation
     * @covers \gitsync\cli_helper\validate_and_clean_args()
     */
    public function test_usegit(): void {
        $helper = new fake_cli_helper([]);
        $helper->processedoptions = ['token' => 'X', 'manifestpath' => 'path/subpath'];
        $helper->validate_and_clean_args();
        $this->expectOutputRegex('/^\nAre you using Git?/s');
    }
}
