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
 * Unit tests for export repo command line script for gitsync
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
class fake_export_cli_helper extends cli_helper {
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
 * Test the CLI script for exporting a repo from Moodle.
 * @group qbank_gitsync
 *
 * @covers \gitsync\export_repo::class
 */
class export_quiz_test extends advanced_testcase {
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
    /** @var string used to store output of multiple calls to a function */
    const MOODLE = 'fakeexportquiz';    /** Name of question to be generated and exported. */
    const QUIZNAME = 'Quiz 1';
    const QUIZINTRO = 'Quiz intro';
    const FEEDBACK = 'Quiz feedback';
    const HEADING1 = 'Heading 1';
    const HEADING2 = 'Heading 2';
    /** @var array input parameters */
    protected array $quizoutput = [
        'quiz' => [
            'name' => self::QUIZNAME,
            'intro' => self::QUIZINTRO,
            'introformat' => '0',
            'coursename' => null,
            'courseid' => null,
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
            ]
        ],
        'questions' => [
            [
                'questionbankentryid' => '36001',
                'slot' => '1',
                'page' => '1',
                'requireprevious' => 0,
                'maxmark' => '1.0000000',
            ]
        ],
        'feedback' => [
            [
                'feedbacktext' => self::FEEDBACK,
                'feedbacktextformat' => '0',
                'mingrade' => '0.0000000',
                'maxgrade' => '50.000000',
            ]
        ],
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
            'coursename' => null,
            'modulename' => null,
            'instanceid' => null,
            'token' => 'XXXXXX',
            'help' => false,
            'subcall' => false,
        ];
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
        // Will get questions in order from manifest file in testrepo.
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

}
