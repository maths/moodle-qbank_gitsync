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
 * Test the CLI script for exporting a repo from Moodle.
 * @group qbank_gitsync
 *
 * @covers \gitsync\export_repo::class
 */
class export_repo_test extends advanced_testcase {
    /** @var array mocked output of cli_helper->get_arguments */
    public array $options;
    /** @var array of instance names and URLs */
    public array $moodleinstances;
    /** @var cli_helper mocked cli_helper */
    public cli_helper $clihelper;
    /** @var curl_request mocked curl_request */
    public curl_request $curl;
    /** @var export_repo mocked export_repo */
    public export_repo $exportrepo;
    /** @var string root of virtual file system */
    public string $rootpath;
    /** @var string used to store output of multiple calls to a function */
    const MOODLE = 'fakeexport';

    public function setUp(): void {
        global $CFG;
        $this->moodleinstances = [self::MOODLE => 'fakeurl.com'];
        // Copy test repo to virtual file stream.
        $root = vfsStream::setup();
        vfsStream::copyFromFileSystem($CFG->dirroot . '/question/bank/gitsync/testrepo/', $root);
        $this->rootpath = vfsStream::url('root');

        // Mock the combined output of command line options and defaults.
        $this->options = [
            'moodleinstance' => self::MOODLE,
            'rootdirectory' => $this->rootpath,
            'subcategory' => 'top',
            'qcategoryid' => null,
            'manifestpath' => '/' . self::MOODLE . '_system' . cli_helper::MANIFEST_FILE,
            'token' => 'XXXXXX',
            'help' => false,
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
        $this->exportrepo = $this->getMockBuilder(\qbank_gitsync\export_repo::class)->onlyMethods([
            'get_curl_request', 'call_exit', 'handle_abort',
        ])->setConstructorArgs([$this->clihelper, $this->moodleinstances])->getMock();
        $this->exportrepo->curlrequest = $this->curl;
        $this->exportrepo->listcurlrequest = $this->listcurl;

        $this->exportrepo->postsettings = ['questionbankentryid' => null];
    }

    /**
     * Test the full process.
     */
    public function test_process(): void {
        // Will get questions in order from manifest file in testrepo.
        $this->curl->expects($this->exactly(4))->method('execute')->willReturnOnConsecutiveCalls(
            '{"question": "<quiz><question><Name>One</Name></question></quiz>", "version": "10"}',
            '{"question": "<quiz><question><Name>Three</Name></question></quiz>", "version": "1"}',
            '{"question": "<quiz><question><Name>Four</Name></question></quiz>", "version": "1"}',
            '{"question": "<quiz><question><Name>Two</Name></question></quiz>", "version": "1"}'
        );

        $this->listcurl->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            '{"contextinfo": {"contextlevel": "module", "categoryname": "", "coursename": "Course 1",
                "modulename": "Module 1", "instanceid": "", "qcategoryname":"top"},
              "questions": [{"questionbankentryid": "35001", "name": "One", "questioncategory": ""},
              {"questionbankentryid": "35002", "name": "Two", "questioncategory": ""},
              {"questionbankentryid": "35003", "name": "Three", "questioncategory": ""},
              {"questionbankentryid": "35004", "name": "Four", "questioncategory": ""}]}',
            '{"contextinfo": {"contextlevel": "module", "categoryname": "", "coursename": "Course 1",
                "modulename": "Module 1", "instanceid": "", "qcategoryname":"top"},
              "questions": [{"questionbankentryid": "35001", "name": "One", "questioncategory": ""},
              {"questionbankentryid": "35002", "name": "Two", "questioncategory": ""},
              {"questionbankentryid": "35003", "name": "Three", "questioncategory": ""},
              {"questionbankentryid": "35004", "name": "Four", "questioncategory": ""}]}'
            );
        $manifestcontents = json_decode(file_get_contents($this->exportrepo->manifestpath));
        $this->exportrepo->process();

        // Check question files updated.
        $this->assertStringContainsString('One', file_get_contents($this->rootpath . '/top/cat-1/First-Question.xml'));
        $this->assertStringContainsString('Two', file_get_contents($this->rootpath . '/top/cat-2/Second-Question.xml'));
        $this->assertStringContainsString('Three', file_get_contents($this->rootpath . '/top/cat-2/subcat-2_1/Third-Question.xml'));
        $this->assertStringContainsString('Four', file_get_contents($this->rootpath . '/top/cat-2/subcat-2_1/Fourth-Question.xml'));

        // Check manifest file updated.
        $manifestcontents = json_decode(file_get_contents($this->exportrepo->manifestpath));
        $this->assertCount(4, $manifestcontents->questions);

        $existingentries = array_column($manifestcontents->questions, null, 'questionbankentryid');
        $this->assertArrayHasKey('35001', $existingentries);
        $this->assertArrayHasKey('35002', $existingentries);
        $this->assertArrayHasKey('35003', $existingentries);
        $this->assertArrayHasKey('35004', $existingentries);

        $this->assertEquals('1', $existingentries['35001']->importedversion);
        $this->assertEquals('10', $existingentries['35001']->exportedversion);

        $this->expectOutputRegex('/^\nExported 4 previously linked questions.*Added 0 questions.\n$/s');
    }

    /**
     * Test the export of questions which aren't in the manifest
     * @covers \gitsync\export_trait\export_to_repo()
     */
    public function test_export_to_repo(): void {
        // Will get questions in order from manifest file in testrepo.
        $this->curl->expects($this->exactly(4))->method('execute')->willReturnOnConsecutiveCalls(
            '{"question": "<quiz><question><Name>One</Name></question></quiz>", "version": "10"}',
            '{"question": "<quiz><question><Name>Three</Name></question></quiz>", "version": "1"}',
            '{"question": "<quiz><question><Name>Four</Name></question></quiz>", "version": "1"}',
            '{"question": "<quiz><question><Name>Two</Name></question></quiz>", "version": "1"}'
        );

        $this->listcurl->expects($this->exactly(3))->method('execute')->willReturn(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
              "questions": []}'
        );

        $this->exportrepo->process();

        // Check question files updated.
        $this->assertStringContainsString('One', file_get_contents($this->rootpath . '/top/cat-1/First-Question.xml'));
        $this->assertStringContainsString('Two', file_get_contents($this->rootpath . '/top/cat-2/Second-Question.xml'));
        $this->assertStringContainsString('Three', file_get_contents($this->rootpath . '/top/cat-2/subcat-2_1/Third-Question.xml'));
        $this->assertStringContainsString('Four', file_get_contents($this->rootpath . '/top/cat-2/subcat-2_1/Fourth-Question.xml'));
        $this->expectOutputRegex('/^\nExported 4 previously linked questions.*Added 0 questions.\n$/s');
    }

    /**
     * Test message if export JSON broken.
     */
    public function test_broken_json_on_export(): void {
        $this->curl->expects($this->any())->method('execute')->willReturn(
            '{"question": <Question><Name>One</Name></Question>", "version": "10"}'
        );

        $this->exportrepo->export_questions_in_manifest();

        $this->expectOutputRegex('/Broken JSON returned from Moodle:' .
                                 '.*{"question": <Question><Name>One<\/Name><\/Question>", "version": "10"}/s');
    }

    /**
     * Test message if export exception.
     */
    public function test_exception_on_export(): void {
        $this->curl->expects($this->any())->method('execute')->willReturn(
            '{"exception":"moodle_exception","message":"No token"}'
        );

        $this->exportrepo->export_questions_in_manifest();

        $this->expectOutputRegex('/No token/');
    }

    /**
     * Test message if manifest file update issue.
     */
    public function test_manifest_file_update_error(): void {
        $this->curl->expects($this->any())->method('execute')->willReturn(
            '{"question": "<Question><Name>One</Name></Question>", "version": "10"}'
        );

        chmod($this->exportrepo->manifestpath, 0000);

        @$this->exportrepo->export_questions_in_manifest();
        $this->expectOutputRegex('/\nUnable to update manifest file.*Aborting.\n$/s');
    }

    /**
     * Test message if manifest file open issue.
     */
    public function test_manifest_file_open_error(): void {
        chmod($this->exportrepo->manifestpath, 0000);
        @$this->exportrepo->__construct($this->clihelper, $this->moodleinstances);
        $this->expectOutputRegex('/^\nUnable to access or parse manifest file.*Aborting.\n$/s');
    }

    /**
     * Test message if question file update issue.
     */
    public function test_question_file_update_error(): void {
        $this->curl->expects($this->any())->method('execute')->willReturn(
            '{"question": "<Question><Name>One</Name></Question>", "version": "10"}'
        );

        chmod($this->rootpath . '/top/cat-1/First-Question.xml', 0000);

        @$this->exportrepo->export_questions_in_manifest();
        $this->expectOutputRegex('/^\nAccess issue.\n\/top\/cat-1\/First-Question.xml not updated.\n/s');
    }

    /**
     * Test message if question reformat issue.
     */
    public function test_reformat_error(): void {
        $this->curl->expects($this->any())->method('execute')->willReturnOnConsecutiveCalls(
            '{"question": "<quiz><question><Name>One</question></quiz>", "version": "10"}', // Broken.
            '{"question": "<quiz><question><Name>Three</Name></question></quiz>", "version": "1"}',
            '{"question": "<quiz><question><Name>Four</Name></question></quiz>", "version": "1"}',
            '{"question": "<quiz><question><Name>Two</Name></question></quiz>", "version": "1"}',
        );

        // Make sure no attempt is made to update first file.
        chmod($this->rootpath . '/top/cat-1/First-Question.xml', 0000);

        @$this->exportrepo->export_questions_in_manifest();
        $this->expectOutputRegex('/^\nBroken XML\n\/top\/cat-1\/First-Question.xml not updated.\n/s');
    }

    /**
     * Test the full process with subcategory name.
     */
    public function test_process_with_subcategory_name(): void {
        $this->options['subcategory'] = 'top/cat-2/subcat-2_1';
        $this->clihelper = $this->getMockBuilder(\qbank_gitsync\cli_helper::class)->onlyMethods([
            'get_arguments', 'check_context',
        ])->setConstructorArgs([[]])->getMock();
        $this->clihelper->expects($this->any())->method('get_arguments')->will($this->returnValue($this->options));
        $this->clihelper->expects($this->any())->method('check_context')->willReturn(
            json_decode('{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                             "modulename":"Module 1", "instanceid":"", "qcategoryname":"top/cat 2/subcat 2_1"},
              "questions": []}')
        );
        $this->exportrepo = $this->getMockBuilder(\qbank_gitsync\export_repo::class)->onlyMethods([
            'get_curl_request', 'call_exit', 'handle_abort',
        ])->setConstructorArgs([$this->clihelper, $this->moodleinstances])->getMock();

        $this->exportrepo->curlrequest = $this->curl;
        $this->exportrepo->listcurlrequest = $this->listcurl;

        // Will get questions in order from manifest file in testrepo.
        $this->curl->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            '{"question": "<quiz><question><Name>Three</Name></question></quiz>", "version": "1"}',
            '{"question": "<quiz><question><Name>Four</Name></question></quiz>", "version": "1"}'
        );

        $this->listcurl->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            '{"contextinfo": {"contextlevel": "module", "categoryname": "", "coursename": "Course 1",
                "modulename": "Module 1", "instanceid": "", "qcategoryname":"top/cat 2/subcat 2_1"},
              "questions": [{"questionbankentryid": "35003", "name": "Three", "questioncategory": ""},
              {"questionbankentryid": "35004", "name": "Four", "questioncategory": ""}]}',
            '{"contextinfo": {"contextlevel": "module", "categoryname": "", "coursename": "Course 1",
                "modulename": "Module 1", "instanceid": "", "qcategoryname":"top"},
              "questions": [{"questionbankentryid": "35001", "name": "One", "questioncategory": ""},
              {"questionbankentryid": "35002", "name": "Two", "questioncategory": ""},
              {"questionbankentryid": "35003", "name": "Three", "questioncategory": ""},
              {"questionbankentryid": "35004", "name": "Four", "questioncategory": ""}]}'
            );
        $manifestcontents = json_decode(file_get_contents($this->exportrepo->manifestpath));
        $this->exportrepo->process();

        // Check question files updated.
        $this->assertStringContainsString('First Question', file_get_contents($this->rootpath . '/top/cat-1/First-Question.xml'));
        $this->assertStringContainsString('Second Question', file_get_contents($this->rootpath . '/top/cat-2/Second-Question.xml'));
        $this->assertStringContainsString('Three', file_get_contents($this->rootpath . '/top/cat-2/subcat-2_1/Third-Question.xml'));
        $this->assertStringContainsString('Four', file_get_contents($this->rootpath . '/top/cat-2/subcat-2_1/Fourth-Question.xml'));

        // Check manifest file updated.
        $manifestcontents = json_decode(file_get_contents($this->exportrepo->manifestpath));
        $this->assertCount(4, $manifestcontents->questions);

        $existingentries = array_column($manifestcontents->questions, null, 'questionbankentryid');
        $this->assertArrayHasKey('35001', $existingentries);
        $this->assertArrayHasKey('35002', $existingentries);
        $this->assertArrayHasKey('35003', $existingentries);
        $this->assertArrayHasKey('35004', $existingentries);

        $this->expectOutputRegex('/^\nExported 2 previously linked questions.*Added 0 questions.\n$/s');
    }

    /**
     * Test the full process with subcategory id.
     */
    public function test_process_with_subcategory_id(): void {
        global $DB;
        $this->options['qcategoryid'] = $DB->get_field('question_categories', 'id', ['name' => 'subcat 2_1']);
        $this->clihelper = $this->getMockBuilder(\qbank_gitsync\cli_helper::class)->onlyMethods([
            'get_arguments', 'check_context',
        ])->setConstructorArgs([[]])->getMock();
        $this->clihelper->expects($this->any())->method('get_arguments')->will($this->returnValue($this->options));
        $this->clihelper->expects($this->any())->method('check_context')->willReturn(
            json_decode('{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                             "modulename":"Module 1", "instanceid":"", "qcategoryname":"top/cat 2/subcat 2_1"},
              "questions": []}')
        );
        $this->exportrepo = $this->getMockBuilder(\qbank_gitsync\export_repo::class)->onlyMethods([
            'get_curl_request', 'call_exit', 'handle_abort',
        ])->setConstructorArgs([$this->clihelper, $this->moodleinstances])->getMock();

        $this->exportrepo->curlrequest = $this->curl;
        $this->exportrepo->listcurlrequest = $this->listcurl;

        // Will get questions in order from manifest file in testrepo.
        $this->curl->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            '{"question": "<quiz><question><Name>Three</Name></question></quiz>", "version": "1"}',
            '{"question": "<quiz><question><Name>Four</Name></question></quiz>", "version": "1"}'
        );

        $this->listcurl->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            '{"contextinfo": {"contextlevel": "module", "categoryname": "", "coursename": "Course 1",
                "modulename": "Module 1", "instanceid": "", "qcategoryname":"top/cat 2/subcat 2_1"},
              "questions": [{"questionbankentryid": "35003", "name": "Three", "questioncategory": ""},
              {"questionbankentryid": "35004", "name": "Four", "questioncategory": ""}]}',
            '{"contextinfo": {"contextlevel": "module", "categoryname": "", "coursename": "Course 1",
                "modulename": "Module 1", "instanceid": "", "qcategoryname":"top"},
              "questions": [{"questionbankentryid": "35001", "name": "One", "questioncategory": ""},
              {"questionbankentryid": "35002", "name": "Two", "questioncategory": ""},
              {"questionbankentryid": "35003", "name": "Three", "questioncategory": ""},
              {"questionbankentryid": "35004", "name": "Four", "questioncategory": ""}]}'
            );
        $manifestcontents = json_decode(file_get_contents($this->exportrepo->manifestpath));
        $this->exportrepo->process();

        // Check question files updated.
        $this->assertStringContainsString('First Question', file_get_contents($this->rootpath . '/top/cat-1/First-Question.xml'));
        $this->assertStringContainsString('Second Question', file_get_contents($this->rootpath . '/top/cat-2/Second-Question.xml'));
        $this->assertStringContainsString('Three', file_get_contents($this->rootpath . '/top/cat-2/subcat-2_1/Third-Question.xml'));
        $this->assertStringContainsString('Four', file_get_contents($this->rootpath . '/top/cat-2/subcat-2_1/Fourth-Question.xml'));

        // Check manifest file updated.
        $manifestcontents = json_decode(file_get_contents($this->exportrepo->manifestpath));
        $this->assertCount(4, $manifestcontents->questions);

        $existingentries = array_column($manifestcontents->questions, null, 'questionbankentryid');
        $this->assertArrayHasKey('35001', $existingentries);
        $this->assertArrayHasKey('35002', $existingentries);
        $this->assertArrayHasKey('35003', $existingentries);
        $this->assertArrayHasKey('35004', $existingentries);

        $this->expectOutputRegex('/^\nExported 2 previously linked questions.*Added 0 questions.\n$/s');
    }
}
