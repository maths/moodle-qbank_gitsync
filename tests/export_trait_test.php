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
class export_trait_test extends advanced_testcase {
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
            'manifestpath' => '/' . self::MOODLE . '_system' . cli_helper::MANIFEST_FILE,
            'token' => 'XXXXXX',
            'help' => false
        ];
        $this->clihelper = $this->getMockBuilder(\qbank_gitsync\cli_helper::class)->onlyMethods([
            'get_arguments'
        ])->setConstructorArgs([[]])->getMock();
        $this->clihelper->expects($this->any())->method('get_arguments')->will($this->returnValue($this->options));

        // Mock call to webservice.
        $this->curl = $this->getMockBuilder(\qbank_gitsync\curl_request::class)->onlyMethods([
            'execute'
        ])->setConstructorArgs(['xxxx'])->getMock();
        $this->listcurl = $this->getMockBuilder(\qbank_gitsync\curl_request::class)->onlyMethods([
            'execute'
        ])->setConstructorArgs(['xxxx'])->getMock();
        $this->exportrepo = $this->getMockBuilder(\qbank_gitsync\export_repo::class)->onlyMethods([
            'get_curl_request', 'call_exit'
        ])->setConstructorArgs([$this->clihelper, $this->moodleinstances])->getMock();
        $this->exportrepo->curlrequest = $this->curl;
        $this->exportrepo->listcurlrequest = $this->listcurl;

        $this->exportrepo->postsettings = ['questionbankentryid' => null];
    }

    /**
     * Test the export of questions which aren't in the manifest
     * @covers \gitsync\export_trait\export_to_repo()
     */
    public function test_export_to_repo(): void {
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
            '[{"questionbankentryid": "5", "name": "Fifth Question", "questioncategory": "subcat 2_1"},
              {"questionbankentryid": "6", "name": "Fifth Question", "questioncategory": "cat 3"},
              {"questionbankentryid": "7", "name": "Fifth Question", "questioncategory": "cat 3"},
              {"questionbankentryid": "8", "name": "Fifth Question", "questioncategory": "subcat 2_1"}]'
        );

        $this->exportrepo->export_to_repo();

        // Check question files created.
        // New question in existing folder.
        $this->assertStringContainsString('Five', file_get_contents($this->rootpath . '/top/Source 2/cat 2/subcat 2_1/Five.xml'));
        // New question in new folder.
        $this->assertStringContainsString('Six', file_get_contents($this->rootpath . '/top/Source 2/cat 3/Six.xml'));
        $this->assertStringContainsString('top/Source 2/cat 3',
            file_get_contents($this->rootpath . '/top/Source 2/cat 3/' . cli_helper::CATEGORY_FILE . '.xml'));
        // New question in existing folder - 2 category questions.
        $this->assertStringContainsString('Seven', file_get_contents($this->rootpath . '/top/Source 2/cat 3/Seven.xml'));
        // New question in new folder - 2 category questions.
        $this->assertStringContainsString('Eight', file_get_contents($this->rootpath . '/top/Source 2/cat 2/subcat 2_1/Eight.xml'));

        // Check temp file.
        $tempfile = fopen($this->exportrepo->tempfilepath, 'r');
        $firstline = json_decode(fgets($tempfile));
        $this->assertEquals('5', $firstline->questionbankentryid);
        $this->assertEquals($this->rootpath . '/top/Source 2/cat 2/subcat 2_1/Five.xml', $firstline->filepath);
        $this->assertEquals($firstline->version, '10');
        $this->assertEquals($firstline->exportedversion, '10');
    }

    /**
     * Test message if export JSON broken.
     */
    public function test_broken_json_on_import(): void {
        $questions = json_decode('[{"questionbankentryid": "5", "name": "Fifth Question", "questioncategory": "subcat 2_1"}]');
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
    public function test_exception_on_import(): void {
        $questions = json_decode('[{"questionbankentryid": "5", "name": "Fifth Question", "questioncategory": "subcat 2_1"}]');
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
            'get_curl_request', 'call_exit', 'export_to_repo_main_process'
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
            'get_curl_request', 'call_exit', 'export_to_repo_main_process'
        ])->setConstructorArgs([$this->clihelper, $this->moodleinstances])->getMock();
        $this->exportrepo->listcurlrequest = $this->listcurl;
        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            '{"exception":"moodle_exception","message":"No token"}'
        );
        $this->exportrepo->export_to_repo();
        $this->expectOutputRegex('/No token/');
    }

}
