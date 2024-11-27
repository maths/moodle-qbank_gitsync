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
 * Unit tests for export quiz command line script for gitsync
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
 * Test the CLI script for exporting a repo from Moodle.
 * @group qbank_gitsync
 *
 * @covers \gitsync\export_repo::class
 */
final class export_quiz_test extends advanced_testcase {
    /** @var array mocked output of cli_helper->get_arguments */
    public array $options;
    /** @var array of instance names and URLs */
    public array $moodleinstances;
    /** @var cli_helper mocked cli_helper */
    public cli_helper $clihelper;
    /** @var curl_request mocked curl_request */
    public curl_request $curl;
    /** @var export_quiz mocked export_quiz */
    public export_quiz $exportquiz;
    /** @var curl_request mocked curl_request for question list */
    public curl_request $listcurl;
    /** @var string root of virtual file system */
    public string $rootpath;
    /** MOODLE - Moodle instance value. */
    const MOODLE = 'fakeexportquiz';
    /** QUIZNAME - Moodle quiz name value. */
    const QUIZNAME = 'Quiz 1';
    /** QUIZINTRO - Moodle quiz intro value. */
    const QUIZINTRO = 'Quiz intro';
    /** FEEDBACK - Quiz feedback value. */
    const FEEDBACK = 'Quiz feedback';
    /** HEADING1 - heading value. */
    const HEADING1 = 'Heading 1';
    /** HEADING2 - heading value. */
    const HEADING2 = 'Heading 2';
    /** COURSENAME - course name value. */
    const COURSENAME = 'Course 1';
    /** @var array expected quiz details output */
    protected array $quizoutput = [
        'quiz' => [
            'name' => self::QUIZNAME,
            'intro' => self::QUIZINTRO,
            'introformat' => '0',
            'questionsperpage' => '0',
            'grade' => '100.00000',
            'navmethod' => 'free',
        ],
        'sections' => [
            [
                'firstslot' => '1',
                'heading' => self::HEADING1,
                'shufflequestions' => 0,
            ],
            [
                'firstslot' => '2',
                'heading' => self::HEADING2,
                'shufflequestions' => 0,
            ],
        ],
        'questions' => [
            [
                'questionbankentryid' => '36001',
                'slot' => '1',
                'page' => '1',
                'requireprevious' => 0,
                'maxmark' => '1.0000000',
            ],
        ],
        'feedback' => [
            [
                'feedbacktext' => self::FEEDBACK,
                'feedbacktextformat' => '0',
                'mingrade' => '0.0000000',
                'maxgrade' => '50.000000',
            ],
        ],
    ];

    public function setUp(): void {
        parent::setUp();
        global $CFG;
        $this->moodleinstances = [self::MOODLE => 'fakeurl.com'];
        // Copy test repo to virtual file stream.
        $root = vfsStream::setup();
        vfsStream::copyFromFileSystem($CFG->dirroot . '/question/bank/gitsync/testrepoparent/', $root);
        $this->rootpath = vfsStream::url('root');

        // Mock the combined output of command line options and defaults.
        $this->options = [
            'moodleinstance' => self::MOODLE,
            'rootdirectory' => $this->rootpath,
            'nonquizmanifestpath' => '/testrepo/' . self::MOODLE . '_course_course-1' . cli_helper::MANIFEST_FILE,
            'quizmanifestpath' => '/testrepo_quiz_quiz-1/' . self::MOODLE . '_module_course-1_quiz-1' . cli_helper::MANIFEST_FILE,
            'coursename' => null,
            'modulename' => null,
            'instanceid' => null,
            'token' => 'XXXXXX',
            'help' => false,
            'subcall' => false,
        ];

    }

    /**
     * Mock set up
     *
     * @return void
     */
    public function set_up_mocks() {
        $this->clihelper = $this->getMockBuilder(\qbank_gitsync\cli_helper::class)->onlyMethods([
            'get_arguments', 'check_context',
        ])->setConstructorArgs([[]])->getMock();
        $this->clihelper->expects($this->any())->method('get_arguments')->will($this->returnValue($this->options));
        $this->clihelper->expects($this->any())->method('check_context')->willReturn(
            json_decode('{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                             "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
              "questions": []}')
        );
        // Mock call to webservice.
        $this->curl = $this->getMockBuilder(\qbank_gitsync\curl_request::class)->onlyMethods([
            'execute',
        ])->setConstructorArgs(['xxxx'])->getMock();
        $this->listcurl = $this->getMockBuilder(\qbank_gitsync\curl_request::class)->onlyMethods([
            'execute',
        ])->setConstructorArgs(['xxxx'])->getMock();
        $this->exportquiz = $this->getMockBuilder(\qbank_gitsync\export_quiz::class)->onlyMethods([
            'get_curl_request', 'call_exit',
        ])->setConstructorArgs([$this->clihelper, $this->moodleinstances])->getMock();
        $this->exportquiz->curlrequest = $this->curl;
        $this->exportquiz->listcurlrequest = $this->listcurl;
    }

    /**
     * Test the full process.
     */
    public function test_process(): void {
        $this->set_up_mocks();
        $this->curl->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            json_encode($this->quizoutput)
        );

        $this->exportquiz->process();

        $quizstructure = file_get_contents($this->rootpath . '/testrepo_quiz_quiz-1/' . 'quiz-1' . cli_helper::QUIZ_FILE);
        // Check question files updated.
        $quizstructure = json_decode($quizstructure);
        $this->assertEquals('/top/Quiz-Question.xml', $quizstructure->questions[0]->quizfilepath);

        $this->expectOutputRegex('/^Quiz data exported to:\n.*testrepo_quiz_quiz-1\/quiz-1_quiz.json\n$/s');
    }

    /**
     * Test message if export JSON broken.
     */
    public function test_broken_json_on_export(): void {
        $this->set_up_mocks();
        $this->curl->expects($this->any())->method('execute')->willReturn(
            '{"quiz": </Question>"}'
        );

        $this->exportquiz->process();

        $this->expectOutputRegex('/Broken JSON returned from Moodle:' .
                                 '.*{"quiz": <\/Question>"}/s');
    }

    /**
     * Test message if export exception.
     */
    public function test_exception_on_export(): void {
        $this->set_up_mocks();
        $this->curl->expects($this->any())->method('execute')->willReturn(
            '{"exception":"moodle_exception","message":"No token"}'
        );

        $this->exportquiz->process();

        $this->expectOutputRegex('/No token/');
    }

    /**
     * Test message if manifest file update issue.
     */
    public function test_manifest_file_update_error(): void {
        $this->set_up_mocks();
        $this->curl->expects($this->any())->method('execute')->willReturn(
            json_encode($this->quizoutput)
        );
        $filepath = cli_helper::get_quiz_structure_path(self::QUIZNAME, dirname($this->exportquiz->quizmanifestpath));
        file_put_contents($filepath, '');
        chmod($filepath, 0000);

        @$this->exportquiz->process();
        $this->expectOutputRegex('/\nUnable to update quiz structure file.*Aborting.*$/s');
    }

    /**
     * Test if quiz context questions.
     */
    public function test_quiz_context_questions(): void {
        $this->quizoutput['questions'][] =
            [
                'questionbankentryid' => '36002',
                'slot' => '2',
                'page' => '2',
                'requireprevious' => 0,
                'maxmark' => '1.0000000',
            ];
        $this->set_up_mocks();
        $this->curl->expects($this->any())->method('execute')->willReturn(
            json_encode($this->quizoutput)
        );
        $this->exportquiz->process();
        $structurecontents = json_decode(file_get_contents($this->exportquiz->filepath));
        $this->assertEquals(2, count($structurecontents->questions));
        $this->assertEquals(false, isset($structurecontents->questions[0]->questionbankentryid));
        $this->assertEquals(false, isset($structurecontents->questions[0]->nonquizfilepath));
        $this->assertEquals("/top/Quiz-Question.xml", $structurecontents->questions[0]->quizfilepath);
        $this->assertEquals(false, isset($structurecontents->questions[1]->questionbankentryid));
        $this->assertEquals(false, isset($structurecontents->questions[1]->nonquizfilepath));
        $this->assertEquals("/top/quiz-cat/Quiz-Question-2.xml", $structurecontents->questions[1]->quizfilepath);
        $this->expectOutputRegex('/Quiz data exported to.*testrepo_quiz_quiz-1\/quiz-1_quiz.json.*$/s');
    }

    /**
     * Test if course context questions.
     */
    public function test_course_context_questions(): void {
        $this->quizoutput['questions'] = [
            [
                'questionbankentryid' => '35002',
                'slot' => '2',
                'page' => '2',
                'requireprevious' => 0,
                'maxmark' => '1.0000000',
            ],
            [
                'questionbankentryid' => '35003',
                'slot' => '2',
                'page' => '2',
                'requireprevious' => 0,
                'maxmark' => '1.0000000',
            ],
        ];
        $this->set_up_mocks();
        $this->curl->expects($this->any())->method('execute')->willReturn(
            json_encode($this->quizoutput)
        );
        $this->exportquiz->process();
        $structurecontents = json_decode(file_get_contents($this->exportquiz->filepath));
        $this->assertEquals(2, count($structurecontents->questions));
        $this->assertEquals(false, isset($structurecontents->questions[0]->questionbankentryid));
        $this->assertEquals(false, isset($structurecontents->questions[0]->quizfilepath));
        $this->assertEquals("/top/cat-2/subcat-2_1/Third-Question.xml", $structurecontents->questions[0]->nonquizfilepath);
        $this->assertEquals(false, isset($structurecontents->questions[1]->questionbankentryid));
        $this->assertEquals(false, isset($structurecontents->questions[1]->quizfilepath));
        $this->assertEquals("/top/cat-2/Second-Question.xml", $structurecontents->questions[1]->nonquizfilepath);
        $this->expectOutputRegex('/Quiz data exported to.*testrepo_quiz_quiz-1\/quiz-1_quiz.json.*$/s');
    }

    /**
     * Test if missing questions.
     */
    public function test_missing_questions(): void {
        $this->quizoutput['questions'] = [
            [
                'questionbankentryid' => '35002',
                'slot' => '2',
                'page' => '2',
                'requireprevious' => 0,
                'maxmark' => '1.0000000',
            ],
            [
                'questionbankentryid' => '36001',
                'slot' => '2',
                'page' => '2',
                'requireprevious' => 0,
                'maxmark' => '1.0000000',
            ],
            [
                'questionbankentryid' => '37001',
                'slot' => '2',
                'page' => '2',
                'requireprevious' => 0,
                'maxmark' => '1.0000000',
            ],
        ];
        $this->set_up_mocks();
        $this->curl->expects($this->any())->method('execute')->willReturn(
            json_encode($this->quizoutput)
        );
        $this->exportquiz->process();
        $this->assertEquals(false, is_file($this->exportquiz->filepath));
        $this->expectOutputRegex('/\nQuestion: 37001\nThis question is in the quiz but not in the supplied manifest files\n' .
                            'Questions must either be in the repo.*testrepo_quiz_quiz-1\/quiz-1_quiz.json not updated.\n$/s');
    }

    /**
     * Test if mixed questions.
     */
    public function test_mixed_questions(): void {
        $this->quizoutput['questions'] = [
            [
                'questionbankentryid' => '35001',
                'slot' => '2',
                'page' => '2',
                'requireprevious' => 0,
                'maxmark' => '1.0000000',
            ],
            [
                'questionbankentryid' => '35002',
                'slot' => '2',
                'page' => '2',
                'requireprevious' => 0,
                'maxmark' => '1.0000000',
            ],
            [
                'questionbankentryid' => '36001',
                'slot' => '3',
                'page' => '3',
                'requireprevious' => 0,
                'maxmark' => '1.0000000',
            ],
            [
                'questionbankentryid' => '36002',
                'slot' => '4',
                'page' => '4',
                'requireprevious' => 0,
                'maxmark' => '1.0000000',
            ],
        ];
        $this->set_up_mocks();
        $this->curl->expects($this->any())->method('execute')->willReturn(
            json_encode($this->quizoutput)
        );
        $this->exportquiz->process();
        $structurecontents = json_decode(file_get_contents($this->exportquiz->filepath));
        $this->assertEquals(4, count($structurecontents->questions));
        $this->assertEquals(false, isset($structurecontents->questions[0]->questionbankentryid));
        $this->assertEquals(false, isset($structurecontents->questions[0]->quizfilepath));
        $this->assertEquals("/top/cat-1/First-Question.xml", $structurecontents->questions[0]->nonquizfilepath);
        $this->assertEquals(false, isset($structurecontents->questions[1]->questionbankentryid));
        $this->assertEquals(false, isset($structurecontents->questions[1]->quizfilepath));
        $this->assertEquals("/top/cat-2/subcat-2_1/Third-Question.xml", $structurecontents->questions[1]->nonquizfilepath);
        $this->assertEquals(false, isset($structurecontents->questions[2]->questionbankentryid));
        $this->assertEquals(false, isset($structurecontents->questions[2]->nonquizfilepath));
        $this->assertEquals("/top/Quiz-Question.xml", $structurecontents->questions[2]->quizfilepath);
        $this->assertEquals(false, isset($structurecontents->questions[3]->questionbankentryid));
        $this->assertEquals(false, isset($structurecontents->questions[3]->nonquizfilepath));
        $this->assertEquals("/top/quiz-cat/Quiz-Question-2.xml", $structurecontents->questions[3]->quizfilepath);
        $this->expectOutputRegex('/Quiz data exported to.*testrepo_quiz_quiz-1\/quiz-1_quiz.json.*$/s');
    }
}
