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
final class export_trait_test extends advanced_testcase {
    /** @var array mocked output of cli_helper->get_arguments */
    public array $options;
    /** @var array of instance names and URLs */
    public array $moodleinstances;
    /** @var cli_helper mocked cli_helper */
    public cli_helper $clihelper;
    /** @var curl_request mocked curl_request */
    public curl_request $curl;
    /** @var export_repo mocked curl_request for doc upload */
    public export_repo $exportrepo;
    /** @var curl_request mocked curl_request for question list */
    public curl_request $listcurl;
    /** @var string root of virtual file system */
    public string $rootpath;
    /** @var string used to store output of multiple calls to a function */
    const MOODLE = 'fakeexport';

    public function setUp(): void {
        parent::setUp();
        global $CFG;
        $this->moodleinstances = [self::MOODLE => 'fakeurl.com'];
        // Copy test repo to virtual file stream.
        $root = vfsStream::setup();
        vfsStream::copyFromFileSystem($CFG->dirroot . '/question/bank/gitsync/testrepoparent/testrepo/', $root);
        $this->rootpath = vfsStream::url('root');

        // Mock the combined output of command line options and defaults.
        $this->options = [
            'moodleinstance' => self::MOODLE,
            'rootdirectory' => $this->rootpath,
            'subcategory' => null,
            'qcategoryid' => null,
            'manifestpath' => '/' . self::MOODLE . '_system' . cli_helper::MANIFEST_FILE,
            'token' => 'XXXXXX',
            'help' => false,
            'ignorecat' => null,
            'usegit' => true,
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
            'get_curl_request', 'call_exit',
        ])->setConstructorArgs([$this->clihelper, $this->moodleinstances])->getMock();
        $this->exportrepo->curlrequest = $this->curl;
        $this->exportrepo->listcurlrequest = $this->listcurl;

        $this->exportrepo->postsettings = ['questionbankentryid' => null];
    }

    /**
     * Set valid output for web service calls.
     *
     * @return void
     */
    public function set_curl_output(): void {
        $this->curl->expects($this->exactly(4))->method('execute')->willReturnOnConsecutiveCalls(
            '{"question": "<quiz><question type=\"category\"><category>' .
                          '<text>top/Source 2/cat 2/subcat 2_1</text></category></question>' .
                          '<question><name><text>Five</text></name></question></quiz>", "version": "10"}',
            '{"question": "<quiz><question type=\"category\"><category><text>top/Source 2/cat 3</text></category></question>' .
                          '<question><name><text>Six</text></name></question></quiz>"' .
                          ', "version": "1"}',
            '{"question": "<quiz><question type=\"category\"><category><text>top/Source 2</text></category></question>' .
                          '<question type=\"category\"><category><text>top/Source 2/cat 3</text></category></question>' .
                          '<question><name><text>Seven</text></name></question></quiz>"' .
                          ', "version": "1"}',
            '{"question": "<quiz><question type=\"category\"><category><text>top/Source 2/cat 2</text></category></question>' .
                          '<question type=\"category\"><category><text>top/Source 2/cat 2/subcat 2_1</text></category></question>' .
                          '<question><name><text>Eight</text></name></question></quiz>"' .
                          ', "version": "1"}',
        );

        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
              "questions": [{"questionbankentryid": "5", "name": "Five", "questioncategory": "subcat 2_1"},
              {"questionbankentryid": "6", "name": "Six", "questioncategory": "cat 3"},
              {"questionbankentryid": "7", "name": "Seven", "questioncategory": "cat 3"},
              {"questionbankentryid": "8", "name": "Eight", "questioncategory": "subcat 2_1"}]}'
        );
    }

    /**
     * Set valid output for web service calls with questions with matching names.
     *
     * @return void
     */
    public function set_curl_output_same_name(): void {
        $this->curl->expects($this->exactly(5))->method('execute')->willReturnOnConsecutiveCalls(
            '{"question": "<quiz><question type=\"category\"><category>' .
                          '<text>top/Source 2/cat 2/subcat 2_1</text></category></question>' .
                          '<question><name><text>Five</text></name></question></quiz>", "version": "10"}',
            '{"question": "<quiz><question type=\"category\"><category><text>top/Source 2/cat 3</text></category></question>' .
                          '<question><name><text>Five</text></name></question></quiz>"' .
                          ', "version": "1"}',
            '{"question": "<quiz><question type=\"category\"><category><text>top/Source 2</text></category></question>' .
                          '<question type=\"category\"><category><text>top/Source 2/cat 3</text></category></question>' .
                          '<question><name><text>Five</text></name></question></quiz>"' .
                          ', "version": "1"}',
            '{"question": "<quiz><question type=\"category\"><category><text>top/Source 2/cat 2</text></category></question>' .
                          '<question type=\"category\"><category><text>top/Source 2/cat 2/subcat 2_1</text></category></question>' .
                          '<question><name><text>Five</text></name></question></quiz>"' .
                          ', "version": "1"}',
            '{"question": "<quiz><question type=\"category\"><category><text>top/Source 2/cat 2</text></category></question>' .
                          '<question type=\"category\"><category><text>top/Source 2/cat 2/subcat 2_1</text></category></question>' .
                          '<question><name><text>Five</text></name></question></quiz>"' .
                          ', "version": "1"}',
        );

        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
              "questions": [{"questionbankentryid": "5", "name": "Five", "questioncategory": "subcat 2_1"},
              {"questionbankentryid": "6", "name": "Five", "questioncategory": "cat 3"},
              {"questionbankentryid": "7", "name": "Five", "questioncategory": "cat 3"},
              {"questionbankentryid": "8", "name": "Five", "questioncategory": "subcat 2_1"},
              {"questionbankentryid": "9", "name": "Five", "questioncategory": "subcat 2_1"}]}'
        );
    }

    /**
     * Test the export of questions which aren't in the manifest
     * @covers \gitsync\export_trait\export_to_repo()
     */
    public function test_export_to_repo(): void {
        $this->set_curl_output();
        $this->exportrepo->export_to_repo();

        // Check question files created.
        // New question in existing folder.
        $this->assertStringContainsString('Five', file_get_contents($this->rootpath . '/top/Source-2/cat-2/subcat-2_1/Five.xml'));
        // New question in new folder.
        $this->assertStringContainsString('Six', file_get_contents($this->rootpath . '/top/Source-2/cat-3/Six.xml'));
        $this->assertStringContainsString('top/Source 2/cat 3',
            file_get_contents($this->rootpath . '/top/Source-2/cat-3/' . cli_helper::CATEGORY_FILE . '.xml'));
        // New question in existing folder - 2 category questions.
        $this->assertStringContainsString('Seven', file_get_contents($this->rootpath . '/top/Source-2/cat-3/Seven.xml'));
        // New question in new folder - 2 category questions.
        $this->assertStringContainsString('Eight', file_get_contents($this->rootpath . '/top/Source-2/cat-2/subcat-2_1/Eight.xml'));

        // Check temp file.
        $tempfile = fopen($this->exportrepo->tempfilepath, 'r');
        $firstline = json_decode(fgets($tempfile));
        $this->assertEquals('5', $firstline->questionbankentryid);
        $this->assertEquals($this->rootpath . '/top/Source-2/cat-2/subcat-2_1/Five.xml', $firstline->filepath);
        $this->assertEquals($firstline->version, '10');
    }

    /**
     * Test message if export JSON broken.
     */
    public function test_broken_json_on_export(): void {
        $questions = json_decode('{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
            "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
            "questions": [{"questionbankentryid": "5", "name": "Fifth Question", "questioncategory": "subcat 2_1"}]}');
        $this->curl->expects($this->any())->method('execute')->willReturn(
            '{"question": <Question><Name>One</Name></Question>", "version": "10"}'
        );

        $this->exportrepo->export_to_repo_main_process($questions);
        $this->expectOutputRegex('/Broken JSON returned from Moodle:' .
                                 '.*{"question": <Question><Name>One<\/Name><\/Question>", "version": "10"}/s');
    }

    /**
     * Test message if export exception.
     */
    public function test_exception_on_export(): void {
        $questions = json_decode('{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
            "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
            "questions": [{"questionbankentryid": "5", "name": "Fifth Question", "questioncategory": "subcat 2_1"}]}');
        $this->curl->expects($this->any())->method('execute')->willReturn(
            '{"exception":"moodle_exception","message":"No token"}'
        );

        $this->exportrepo->export_to_repo_main_process($questions);
        $this->expectOutputRegex('/No token/');
    }

    /**
     * Test message if list JSON broken.
     */
    public function test_broken_json_on_get_list(): void {
        $this->exportrepo = $this->getMockBuilder(\qbank_gitsync\export_repo::class)->onlyMethods([
            'get_curl_request', 'call_exit', 'export_to_repo_main_process',
        ])->setConstructorArgs([$this->clihelper, $this->moodleinstances])->getMock();
        $this->exportrepo->listcurlrequest = $this->listcurl;
        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            '[{"questionbankentryid": "35001", "name": "One", "questioncategory": "}]'
        );

        $this->exportrepo->export_to_repo();
        $this->expectOutputRegex('/Broken JSON returned from Moodle:' .
                                 '.*[{"questionbankentryid": "35001", "name": "One", "questioncategory": "}]/s');
    }

    /**
     * Test message if list retrieve exception.
     */
    public function test_exception_on_get_list(): void {
        $this->exportrepo = $this->getMockBuilder(\qbank_gitsync\export_repo::class)->onlyMethods([
            'get_curl_request', 'call_exit', 'export_to_repo_main_process',
        ])->setConstructorArgs([$this->clihelper, $this->moodleinstances])->getMock();
        $this->exportrepo->listcurlrequest = $this->listcurl;
        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            '{"exception":"moodle_exception","message":"No token"}'
        );
        $this->exportrepo->export_to_repo();
        $this->expectOutputRegex('/No token/');
    }

    /**
     * Test message if temp file error.
     */
    public function test_temp_file_open(): void {
        $questions = json_decode('{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                                "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
                                "questions": [{"questionbankentryid": "5", "name": "Fifth Question",
                                "questioncategory": "subcat 2_1"}]}');
        $tempfile = fopen($this->exportrepo->tempfilepath, 'w+');
        fclose($tempfile);
        chmod($this->exportrepo->tempfilepath, 0000);

        @$this->exportrepo->export_to_repo_main_process($questions);
        $this->expectOutputRegex('/^\nUnable to access temp file.*Aborting.\n$/s');
    }

    /**
     * Test message if category file creation issue.
     */
    public function test_category_file_creation_error(): void {
        $questions = json_decode('{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                                "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
                                "questions": [{"questionbankentryid": "5", "name": "Another Question",
                                "questioncategory": "subcat 2_1"}]}');
        $this->curl->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            '{"question": "<quiz><question type=\"category\"><category>' .
                          '<text>top/cat 2/subcat 2_1</text></category></question>' .
                          '<question><name><text>Another Question</text></name></question></quiz>", "version": "10"}',
        );

        unlink($this->rootpath . '/top/cat-2/subcat-2_1/' . cli_helper::CATEGORY_FILE . '.xml');
        chmod($this->rootpath . '/top/cat-2/subcat-2_1', 0000);
        @$this->exportrepo->export_to_repo_main_process($questions);
        $this->expectOutputRegex('/^\nFile creation unsuccessful:.*subcat-2_1\/' . cli_helper::CATEGORY_FILE . '.xml.*$/s');
    }

    /**
     * Test message if category file XML issue.
     */
    public function test_category_xml_error(): void {
        $questions = json_decode('{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                                 "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
                                 "questions": [{"questionbankentryid": "5", "name": "Third Question",
                                 "questioncategory": "subcat 2_1"}]}');
        $this->curl->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            '{"question": "<quiz><question type=\"category\"><category>' .
                          '<text>top/Source 2/cat 2/subcat 2_1</text></category></question>' .
                          '<question><name><text>Third Question</name></question></quiz>", "version": "10"}',
        );

        @$this->exportrepo->export_to_repo_main_process($questions);
        $this->expectOutputRegex('/^\nBroken XML.\nsubcat 2_1 - Third Question not downloaded.\n$/s');
    }

    /**
     * Test the export of questions which aren't in the manifest and have the same name
     * @covers \gitsync\export_trait\export_to_repo()
     */
    public function test_export_to_repo_same_name(): void {
        $this->set_curl_output_same_name();
        $this->exportrepo->export_to_repo();

        // Check question files created.
        $this->assertStringContainsString('Five', file_get_contents($this->rootpath . '/top/Source-2/cat-2/subcat-2_1/Five.xml'));
        $this->assertStringContainsString('Five', file_get_contents($this->rootpath . '/top/Source-2/cat-2/subcat-2_1/Five_2.xml'));
        $this->assertStringContainsString('Five', file_get_contents($this->rootpath . '/top/Source-2/cat-2/subcat-2_1/Five_3.xml'));
        $this->assertStringContainsString('top/Source 2/cat 3',
            file_get_contents($this->rootpath . '/top/Source-2/cat-3/' . cli_helper::CATEGORY_FILE . '.xml'));
        $this->assertStringContainsString('Five', file_get_contents($this->rootpath . '/top/Source-2/cat-3/Five.xml'));
        $this->assertStringContainsString('Five', file_get_contents($this->rootpath . '/top/Source-2/cat-3/Five_2.xml'));

        // Check temp file.
        $tempfile = fopen($this->exportrepo->tempfilepath, 'r');
        $firstline = json_decode(fgets($tempfile));
        $this->assertEquals('5', $firstline->questionbankentryid);
        $this->assertEquals($this->rootpath . '/top/Source-2/cat-2/subcat-2_1/Five.xml', $firstline->filepath);
        $this->assertEquals($firstline->version, '10');
    }
}
