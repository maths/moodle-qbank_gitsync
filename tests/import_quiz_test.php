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
 * Unit tests for import quiz command line script for gitsync
 *
 * @package    qbank_gitsync
 * @copyright  2024 University of Edinburgh
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
class fake_import_cli_helper extends cli_helper {
    /**
     * Override so ignored during testing
     *
     * @return void
     */
    public static function call_exit():void {
        return;
    }

    /**
     * Override so ignored during testing
     *
     * @return void
     */
    public static function handle_abort():void {
        return;
    }
}


/**
 * Test the CLI script for importing a repo from Moodle.
 * @group qbank_gitsync
 *
 * @covers \gitsync\import_repo::class
 */
class import_quiz_test extends advanced_testcase {
    /** @var array mocked output of cli_helper->get_arguments */
    public array $options;
    /** @var array of instance names and URLs */
    public array $moodleinstances;
    /** @var cli_helper mocked cli_helper */
    public cli_helper $clihelper;
    /** @var curl_request mocked curl_request */
    public curl_request $curl;
    /** @var import_quiz mocked import_quiz */
    public import_quiz $importquiz;
    /** @var curl_request mocked curl_request for question list */
    public curl_request $listcurl;
    /** @var string root of virtual file system */
    public string $rootpath;
    /** @var string used to store output of multiple calls to a function */
    const MOODLE = 'fakeimportquiz';
    const QUIZNAME = 'Quiz 1';
    const QUIZINTRO = 'Quiz intro';
    const FEEDBACK = 'Quiz feedback';
    const HEADING1 = 'Heading 1';
    const HEADING2 = 'Heading 2';
    const COURSENAME = 'Course 1';
    protected array $quizoutput = [
        "wstoken" => "XXXXXX",
        "wsfunction" => "qbank_gitsync_import_quiz_data",
        "moodlewsrestformat" => "json",
        "quiz[coursename]" => "Course 1",
        "quiz[courseid]" => "5",
        "quiz[name]" => "Quiz 1",
        "quiz[intro]" => "Quiz intro",
        "quiz[introformat]" => "0",
        "quiz[questionsperpage]" => "0",
        "quiz[grade]" => "100.00000",
        "quiz[navmethod]" => "free",
        "sections[0][firstslot]" => "1",
        "sections[0][heading]" => "Heading 1",
        "sections[0][shufflequestions]" => 0,
        "sections[1][firstslot]" => "2",
        "sections[1][heading]" => "Heading 2",
        "sections[1][shufflequestions]" => 0,
        "questions[0][slot]" => "1",
        "questions[0][page]" => "1",
        "questions[0][requireprevious]" => 0,
        "questions[0][maxmark]" => "1.0000000",
        "questions[0][questionbankentryid]" => "36001",
        "feedback[0][feedbacktext]" => "Quiz feedback",
        "feedback[0][feedbacktextformat]" => "0",
        "feedback[0][mingrade]" => "0.0000000",
        "feedback[0][maxgrade]" => "50.000000"
    ];

    public function setUp(): void {
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
            'quizdatapath' => null,
            'coursename' => null,
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
                             "modulename":"Module 1", "instanceid":"5", "qcategoryname":"top"},
              "questions": []}')
        );
        // Mock call to webservice.
        $this->curl = $this->getMockBuilder(\qbank_gitsync\curl_request::class)->onlyMethods([
            'execute',
        ])->setConstructorArgs(['xxxx'])->getMock();
        $this->listcurl = $this->getMockBuilder(\qbank_gitsync\curl_request::class)->onlyMethods([
            'execute',
        ])->setConstructorArgs(['xxxx'])->getMock();
        $this->importquiz = $this->getMockBuilder(\qbank_gitsync\import_quiz::class)->onlyMethods([
            'get_curl_request', 'call_exit', 'handle_abort',
        ])->setConstructorArgs([$this->clihelper, $this->moodleinstances])->getMock();
        $this->importquiz->curlrequest = $this->curl;
        $this->importquiz->listcurlrequest = $this->listcurl;
    }

    /**
     * Test the full process.
     */
    public function test_process(): void {
        $this->set_up_mocks();
        $this->curl->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            '{"success": true}'
        );
        $this->importquiz->process();
        $this->assertEquals(json_encode($this->quizoutput), json_encode($this->importquiz->postsettings));
        $this->expectOutputRegex('/Quiz imported.\n$/s');
    }

    /**
     * Test message if import JSON broken.
     */
    public function test_broken_json_on_import(): void {
        $this->set_up_mocks();
        $this->curl->expects($this->any())->method('execute')->willReturn(
            '{"quiz": </Question>"}'
        );

        $this->importquiz->process();

        $this->expectOutputRegex('/Broken JSON returned from Moodle:' .
                                 '.*{"quiz": <\/Question>"}/s');
    }

    /**
     * Test message if import exception.
     */
    public function test_exception_on_import(): void {
        $this->set_up_mocks();
        $this->curl->expects($this->any())->method('execute')->willReturn(
            '{"exception":"moodle_exception","message":"No token"}'
        );

        $this->importquiz->process();

        $this->expectOutputRegex('/No token/');
    }

    /**
     * Test message if manifest file open issue.
     */
    public function test_manifest_file_open_error(): void {
        $this->set_up_mocks();
        chmod($this->importquiz->quizmanifestpath, 0000);
        @$this->importquiz->__construct($this->clihelper, $this->moodleinstances);
        $this->expectOutputRegex('/.*Unable to access or parse manifest file:.*testrepo_quiz_quiz-1\/fakeimportquiz_module_course-1_quiz-1_question_manifest.json.*Aborting.*$/s');
    }

    /**
     * Test message if manifest file open issue.
     */
    public function test_nonquiz_manifest_file_open_error(): void {
        $this->set_up_mocks();
        chmod($this->importquiz->nonquizmanifestpath, 0000);
        @$this->importquiz->__construct($this->clihelper, $this->moodleinstances);
        $this->expectOutputRegex('/.*Unable to access or parse manifest file:.*fakeimportquiz_course_course-1_question_manifest.json.*Aborting.*$/s');
    }

    /**
     * Test message if data file open issue.
     */
    public function test_data_file_open_error(): void {
        $this->options['quizdatapath']  = '/testrepo_quiz_quiz-1/' . 'import-quiz' . cli_helper::QUIZ_FILE;
        $this->set_up_mocks();
        chmod($this->importquiz->quizdatapath, 0000);
        @$this->importquiz->__construct($this->clihelper, $this->moodleinstances);
        $this->expectOutputRegex('/.*Unable to access or parse data file:.*testrepo_quiz_quiz-1\/import-quiz_quiz.json.*Aborting.*$/s');
    }

    /**
     * Test validation of supplied datapath info.
     */
    public function test_no_data_info(): void {
        $this->options['quizmanifestpath'] = null;
        $this->set_up_mocks();
        $this->expectOutputRegex('/^\nPlease supply a quiz manifest filepath or a quiz data filepath.*Aborting.\n$/s');
    }

    /**
     * Test validation of supplied course info.
     */
    public function test_no_course_info(): void {
        $this->options['nonquizmanifestpath'] = null;
        $this->set_up_mocks();
        $this->expectOutputRegex('/^\nYou must identify the course you wish to add the quiz to.*Aborting.\n$/s');
    }

    /**
     * Test if quiz context questions.
     */
    public function test_quiz_context_questions(): void {
        $questions = '[
            {
                "quizfilepath": "\/top\/Quiz-Question.xml",
                "slot": "1",
                "page": "1",
                "requireprevious": 0,
                "maxmark": "1.0000000"
            },
            {
                "quizfilepath": "\/top\/quiz-cat\/Quiz-Question-2.xml",
                "slot": "2",
                "page": "2",
                "requireprevious": 0,
                "maxmark": "1.0000000"
            }
        ]';
        $output = [
            "questions[0][slot]" => "1",
            "questions[0][page]" => "1",
            "questions[0][requireprevious]" => 0,
            "questions[0][maxmark]" => "1.0000000",
            "questions[0][questionbankentryid]" => "36001",
            "questions[1][slot]" => "2",
            "questions[1][page]" => "2",
            "questions[1][requireprevious]" => 0,
            "questions[1][maxmark]" => "1.0000000",
            "questions[1][questionbankentryid]" => "36002"
        ];
        $this->set_up_mocks();
        $this->curl->expects($this->any())->method('execute')->willReturn(
            json_encode($this->quizoutput)
        );
        $questions = json_decode($questions);
        $this->quizoutput = array_merge($this->quizoutput, $output);
        $this->importquiz->quizdatacontents->questions = $questions;
        $this->importquiz->process();
        $this->assertEquals([], array_diff_assoc($this->quizoutput, $this->importquiz->postsettings));
        $this->expectOutputRegex('/Quiz imported.\n$/s');
    }

    /**
     * Test if quiz context questions with no course.
     */
    public function test_quiz_context_questions_no_course(): void {
        $questions = '[
            {
                "quizfilepath": "\/top\/Quiz-Question.xml",
                "slot": "1",
                "page": "1",
                "requireprevious": 0,
                "maxmark": "1.0000000"
            },
            {
                "quizfilepath": "\/top\/quiz-cat\/Quiz-Question-2.xml",
                "slot": "2",
                "page": "2",
                "requireprevious": 0,
                "maxmark": "1.0000000"
            }
        ]';
        $output = [
            "questions[0][slot]" => "1",
            "questions[0][page]" => "1",
            "questions[0][requireprevious]" => 0,
            "questions[0][maxmark]" => "1.0000000",
            "questions[0][questionbankentryid]" => "36001",
            "questions[1][slot]" => "2",
            "questions[1][page]" => "2",
            "questions[1][requireprevious]" => 0,
            "questions[1][maxmark]" => "1.0000000",
            "questions[1][questionbankentryid]" => "36002"
        ];
        $this->options['nonquizmanifestpath'] = null;
        $this->set_up_mocks();
        $this->expectOutputRegex('/^\nYou must identify the course you wish to add the quiz to.*Aborting.\n$/s');
    }

    /**
     * Test if quiz context questions with no course manifest.
     */
    public function test_quiz_context_questions_no_course_file(): void {
        $questions = '[
            {
                "quizfilepath": "\/top\/Quiz-Question.xml",
                "slot": "1",
                "page": "1",
                "requireprevious": 0,
                "maxmark": "1.0000000"
            },
            {
                "quizfilepath": "\/top\/quiz-cat\/Quiz-Question-2.xml",
                "slot": "2",
                "page": "2",
                "requireprevious": 0,
                "maxmark": "1.0000000"
            }
        ]';
        $output = [
            "questions[0][slot]" => "1",
            "questions[0][page]" => "1",
            "questions[0][requireprevious]" => 0,
            "questions[0][maxmark]" => "1.0000000",
            "questions[0][questionbankentryid]" => "36001",
            "questions[1][slot]" => "2",
            "questions[1][page]" => "2",
            "questions[1][requireprevious]" => 0,
            "questions[1][maxmark]" => "1.0000000",
            "questions[1][questionbankentryid]" => "36002"
        ];
        $this->options['nonquizmanifestpath'] = null;
        $this->options['coursename'] = 'Course 1';
        $this->set_up_mocks();
        $this->curl->expects($this->any())->method('execute')->willReturn(
            json_encode($this->quizoutput)
        );
        $questions = json_decode($questions);
        $this->quizoutput = array_merge($this->quizoutput, $output);
        $this->importquiz->quizdatacontents->questions = $questions;
        $this->importquiz->process();
        $this->assertEquals([], array_diff_assoc($this->quizoutput, $this->importquiz->postsettings));
        $this->expectOutputRegex('/Quiz imported.\n$/s');
    }

    /**
     * Test if course context questions.
     */
    public function test_course_context_questions(): void {
        $questions = '[
            {
                "nonquizfilepath": "\/top\/cat-2\/subcat-2_1\/Fourth-Question.xml",
                "slot": "1",
                "page": "1",
                "requireprevious": 0,
                "maxmark": "1.0000000"
            },
            {
                "nonquizfilepath": "\/top\/cat-1\/First-Question.xml",
                "slot": "2",
                "page": "2",
                "requireprevious": 0,
                "maxmark": "1.0000000"
            }
        ]';
        $output = [
            "questions[0][slot]" => "1",
            "questions[0][page]" => "1",
            "questions[0][requireprevious]" => 0,
            "questions[0][maxmark]" => "1.0000000",
            "questions[0][questionbankentryid]" => "35004",
            "questions[1][slot]" => "2",
            "questions[1][page]" => "2",
            "questions[1][requireprevious]" => 0,
            "questions[1][maxmark]" => "1.0000000",
            "questions[1][questionbankentryid]" => "35001"
        ];
        $this->set_up_mocks();
        $this->curl->expects($this->any())->method('execute')->willReturn(
            json_encode($this->quizoutput)
        );
        $questions = json_decode($questions);
        $this->quizoutput = array_merge($this->quizoutput, $output);
        $this->importquiz->quizdatacontents->questions = $questions;
        $this->importquiz->process();
        $this->assertEquals([], array_diff_assoc($this->quizoutput, $this->importquiz->postsettings));
        $this->expectOutputRegex('/Quiz imported.\n$/s');
    }

    /**
     * Test if course context questions but no quiz manifest.
     */
    public function test_course_context_questions_no_quiz_manifest(): void {
        $questions = '[
            {
                "nonquizfilepath": "\/top\/cat-2\/subcat-2_1\/Fourth-Question.xml",
                "slot": "1",
                "page": "1",
                "requireprevious": 0,
                "maxmark": "1.0000000"
            },
            {
                "nonquizfilepath": "\/top\/cat-1\/First-Question.xml",
                "slot": "2",
                "page": "2",
                "requireprevious": 0,
                "maxmark": "1.0000000"
            }
        ]';
        $output = [
            "questions[0][slot]" => "1",
            "questions[0][page]" => "1",
            "questions[0][requireprevious]" => 0,
            "questions[0][maxmark]" => "1.0000000",
            "questions[0][questionbankentryid]" => "35004",
            "questions[1][slot]" => "2",
            "questions[1][page]" => "2",
            "questions[1][requireprevious]" => 0,
            "questions[1][maxmark]" => "1.0000000",
            "questions[1][questionbankentryid]" => "35001"
        ];
        $this->options['quizmanifestpath'] = null;
        $this->options['quizdatapath']  = '/testrepo_quiz_quiz-1/' . 'import-quiz' . cli_helper::QUIZ_FILE;
        $this->set_up_mocks();
        $this->curl->expects($this->any())->method('execute')->willReturn(
            json_encode($this->quizoutput)
        );
        $questions = json_decode($questions);
        $this->quizoutput = array_merge($this->quizoutput, $output);
        $this->importquiz->quizdatacontents->questions = $questions;
        $this->importquiz->process();
        $this->assertEquals([], array_diff_assoc($this->quizoutput, $this->importquiz->postsettings));
        $this->expectOutputRegex('/Quiz imported.\n$/s');
    }

    /**
     * Test if missing questions.
     */
    public function test_missing_questions(): void {
        $questions = '[
            {
                "nonquizfilepath": "\/top\/cat-2\/subcat-2_1\/Fake-Question.xml",
                "slot": "1",
                "page": "1",
                "requireprevious": 0,
                "maxmark": "1.0000000"
            },
            {
                "nonquizfilepath": "\/top\/cat-1\/First-Question.xml",
                "slot": "2",
                "page": "2",
                "requireprevious": 0,
                "maxmark": "1.0000000"
            }
        ]';
        $this->set_up_mocks();
        $this->curl->expects($this->any())->method('execute')->willReturn(
            json_encode($this->quizoutput)
        );
        $questions = json_decode($questions);
        $this->importquiz->quizdatacontents->questions = $questions;
        $this->importquiz->process();
        $this->expectOutputRegex('/.*Question: Non-quiz repo: \/top\/cat-2\/subcat-2_1\/Fake-Question.xml\nThis question is in the quiz but not in the supplied manifest file.*/s');
    }

    /**
     * Test if mixed context questions.
     */
    public function test_mixed_context_questions(): void {
        $questions = '[
            {
                "quizfilepath": "\/top\/Quiz-Question.xml",
                "slot": "1",
                "page": "1",
                "requireprevious": 0,
                "maxmark": "1.0000000"
            },
            {
                "quizfilepath": "\/top\/quiz-cat\/Quiz-Question-2.xml",
                "slot": "2",
                "page": "2",
                "requireprevious": 0,
                "maxmark": "1.0000000"
            },
            {
                "nonquizfilepath": "\/top\/cat-2\/subcat-2_1\/Fourth-Question.xml",
                "slot": "3",
                "page": "3",
                "requireprevious": 0,
                "maxmark": "1.0000000"
            },
            {
                "nonquizfilepath": "\/top\/cat-1\/First-Question.xml",
                "slot": "4",
                "page": "4",
                "requireprevious": 0,
                "maxmark": "1.0000000"
            }
        ]';
        $output = [
            "questions[0][slot]" => "1",
            "questions[0][page]" => "1",
            "questions[0][requireprevious]" => 0,
            "questions[0][maxmark]" => "1.0000000",
            "questions[0][questionbankentryid]" => "36001",
            "questions[1][slot]" => "2",
            "questions[1][page]" => "2",
            "questions[1][requireprevious]" => 0,
            "questions[1][maxmark]" => "1.0000000",
            "questions[1][questionbankentryid]" => "36002",
            "questions[2][slot]" => "3",
            "questions[2][page]" => "3",
            "questions[2][requireprevious]" => 0,
            "questions[2][maxmark]" => "1.0000000",
            "questions[2][questionbankentryid]" => "35004",
            "questions[3][slot]" => "4",
            "questions[3][page]" => "4",
            "questions[3][requireprevious]" => 0,
            "questions[3][maxmark]" => "1.0000000",
            "questions[3][questionbankentryid]" => "35001"
        ];
        $this->set_up_mocks();
        $this->curl->expects($this->any())->method('execute')->willReturn(
            json_encode($this->quizoutput)
        );
        $questions = json_decode($questions);
        $this->quizoutput = array_merge($this->quizoutput, $output);
        $this->importquiz->quizdatacontents->questions = $questions;
        $this->importquiz->process();
        $this->assertEquals([], array_diff_assoc($this->quizoutput, $this->importquiz->postsettings));
        $this->expectOutputRegex('/Quiz imported.\n$/s');
    }
}
