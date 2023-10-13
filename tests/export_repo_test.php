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
            'get_curl_request'
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
            '{"question": "<Question><Name>One</Name></Question>", "version": "10"}',
            '{"question": "<Question><Name>Three</Name></Question>", "version": "1"}',
            '{"question": "<Question><Name>Four</Name></Question>", "version": "1"}',
            '{"question": "<Question><Name>Two</Name></Question>", "version": "1"}'
        );

        $this->listcurl->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            '[]',
            '[{"questionbankentryid": "35001", "name": "One", "questioncategory": ""},
              {"questionbankentryid": "35002", "name": "Two", "questioncategory": ""},
              {"questionbankentryid": "35003", "name": "Three", "questioncategory": ""},
              {"questionbankentryid": "35004", "name": "Four", "questioncategory": ""}]'
            );
        $manifestcontents = json_decode(file_get_contents($this->exportrepo->manifestpath));
        $this->exportrepo->process();

        // Check question files updated.
        $this->assertStringContainsString('One', file_get_contents($this->rootpath . '/top/cat 1/First Question.xml'));
        $this->assertStringContainsString('Two', file_get_contents($this->rootpath . '/top/cat 2/Second Question.xml'));
        $this->assertStringContainsString('Three', file_get_contents($this->rootpath . '/top/cat 2/subcat 2_1/Third Question.xml'));
        $this->assertStringContainsString('Four', file_get_contents($this->rootpath . '/top/cat 2/subcat 2_1/Fourth Question.xml'));

        // Check manifest file updated
        $manifestcontents = json_decode(file_get_contents($this->exportrepo->manifestpath));
        $this->assertCount(4, $manifestcontents->questions);

        $existingentries = array_column($manifestcontents->questions, null, 'questionbankentryid');
        $this->assertArrayHasKey('35001', $existingentries);
        $this->assertArrayHasKey('35002', $existingentries);
        $this->assertArrayHasKey('35003', $existingentries);
        $this->assertArrayHasKey('35004', $existingentries);

        $this->assertEquals('1', $existingentries['35001']->version);
        $this->assertEquals('10', $existingentries['35001']->exportedversion);
    }

    /**
     * Test the export of questions which aren't in the manifest
     * @covers \gitsync\export_trait\export_to_repo()
     */
    public function test_export_to_repo(): void {
        // Will get questions in order from manifest file in testrepo.
        $this->curl->expects($this->exactly(4))->method('execute')->willReturnOnConsecutiveCalls(
            '{"question": "<Question><Name>One</Name></Question>", "version": "10"}',
            '{"question": "<Question><Name>Three</Name></Question>", "version": "1"}',
            '{"question": "<Question><Name>Four</Name></Question>", "version": "1"}',
            '{"question": "<Question><Name>Two</Name></Question>", "version": "1"}'
        );

        $this->listcurl->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            '[]', '[]',
        );

        $this->exportrepo->process();

        // Check question files updated.
        $this->assertStringContainsString('One', file_get_contents($this->rootpath . '/top/cat 1/First Question.xml'));
        $this->assertStringContainsString('Two', file_get_contents($this->rootpath . '/top/cat 2/Second Question.xml'));
        $this->assertStringContainsString('Three', file_get_contents($this->rootpath . '/top/cat 2/subcat 2_1/Third Question.xml'));
        $this->assertStringContainsString('Four', file_get_contents($this->rootpath . '/top/cat 2/subcat 2_1/Fourth Question.xml'));
    }

    /**
     * Check entry is removed from manifest if question no longer in Moodle.
     * @covers \gitsync\export_repo\tidy_manifest()
     */
    public function test_tidy_manifest():void {
        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            '[{"questionbankentryid": "35001", "name": "One", "questioncategory": ""},
              {"questionbankentryid": "35003", "name": "Three", "questioncategory": ""},
              {"questionbankentryid": "35004", "name": "Four", "questioncategory": ""}]'
            );

        $this->exportrepo->tidy_manifest();

        $manifestcontents = json_decode(file_get_contents($this->exportrepo->manifestpath));
        $this->assertCount(3, $manifestcontents->questions);

        $existingentries = array_column($manifestcontents->questions, null, 'questionbankentryid');
        $this->assertArrayHasKey('35001', $existingentries);
        $this->assertArrayHasKey('35003', $existingentries);
        $this->assertArrayHasKey('35004', $existingentries);
    }
}
