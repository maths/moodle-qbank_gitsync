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
 * Unit tests for create repo command line script for gitsync
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
 * Test the CLI script for creating a repo from questions in Moodle.
 * @group qbank_gitsync
 *
 * @covers \gitsync\create_repo::class
 */
class create_repo_test extends advanced_testcase {
    /** @var array mocked output of cli_helper->get_arguments */
    public array $options;
    /** @var array of instance names and URLs */
    public array $moodleinstances;
    /** @var cli_helper mocked cli_helper */
    public cli_helper $clihelper;
    /** @var curl_request mocked curl_request */
    public curl_request $curl;
    /** @var curl_request mocked curl_request for question list */
    public curl_request $listcurl;
    /** @var create_repo mocked create_repo */
    public create_repo $createrepo;
    /** @var string root of virtual file system */
    public string $rootpath;
    /** @var string used to store output of multiple calls to a function */
    const MOODLE = 'fakeexport';

    public function setUp(): void {
        global $CFG;
        $this->moodleinstances = [self::MOODLE => 'fakeurl.com'];
        vfsStream::setup();
        $this->rootpath = vfsStream::url('root');

        // Mock the combined output of command line options and defaults.
        $this->options = [
            'moodleinstance' => self::MOODLE,
            'rootdirectory' => $this->rootpath,
            'directory' => '',
            'subcategory' => null,
            'contextlevel' => 'system',
            'coursename' => 'Course 1',
            'modulename' => 'Test 1',
            'coursecategory' => 'Cat 1',
            'qcategoryid' => null,
            'instanceid' => null,
            'token' => 'XXXXXX',
            'help' => false,
            'ignorecat' => null,
        ];
        $this->clihelper = $this->getMockBuilder(\qbank_gitsync\cli_helper::class)->onlyMethods([
            'get_arguments', 'check_context',
        ])->setConstructorArgs([[]])->getMock();
        $this->clihelper->expects($this->any())->method('get_arguments')->will($this->returnValue($this->options));
        $this->clihelper->expects($this->exactly(1))->method('check_context')->willReturn(
            json_decode('{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                             "modulename":"Module 1", "instanceid":"", "qcategoryname":"top", "qcategoryid":123},
              "questions": []}')
        );
        // Mock call to webservice.
        $this->curl = $this->getMockBuilder(\qbank_gitsync\curl_request::class)->onlyMethods([
            'execute',
        ])->setConstructorArgs(['xxxx'])->getMock();
        $this->listcurl = $this->getMockBuilder(\qbank_gitsync\curl_request::class)->onlyMethods([
            'execute',
        ])->setConstructorArgs(['xxxx'])->getMock();;
        $this->createrepo = $this->getMockBuilder(\qbank_gitsync\create_repo::class)->onlyMethods([
            'get_curl_request',
        ])->setConstructorArgs([$this->clihelper, $this->moodleinstances])->getMock();
        $this->createrepo->curlrequest = $this->curl;
        $this->createrepo->listcurlrequest = $this->listcurl;

        $this->createrepo->listpostsettings = ['contextlevel' => '50', 'coursename' => 'Course 1',
                                               'modulename' => 'Module 1', 'coursecategory' => null,
                                               'qcategoryname' => 'top', 'qcategoryid' => '',
                                               'instanceid' => '',
                                               'contextonly' => 0,
                                            ];
        $this->createrepo->postsettings = [];
    }

    /**
     * Test the full process.
     */
    public function test_process(): void {
        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturn(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                             "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
              "questions": [{"questionbankentryid": "1", "name": "One", "questioncategory": ""},
                            {"questionbankentryid": "2", "name": "Two", "questioncategory": ""},
                            {"questionbankentryid": "3", "name": "Three", "questioncategory": ""},
                            {"questionbankentryid": "4", "name": "Four", "questioncategory": ""}]}'
        );
        $this->curl->expects($this->exactly(4))->method('execute')->willReturnOnConsecutiveCalls(
            '{"question": "<quiz><question type=\"category\"><category><text>top</text></category></question>' .
                          '<question><name><text>One</text></name></question></quiz>", "version": "10"}',
            '{"question": "<quiz><question type=\"category\"><category><text>top/Default for Test 1/sub 1' .
                          '</text></category></question><question><name><text>Two</text></name></question></quiz>"' .
                          ', "version": "1"}',
            '{"question": "<quiz><question type=\"category\"><category><text>top/Default for Test 1/sub 2' .
                          '</text></category></question><question><name><text>Three</text></name></question></quiz>"' .
                          ', "version": "1"}',
            '{"question": "<quiz><question type=\"category\"><category><text>top/Default for Test 1/sub 2' .
                          '</text></category></question><question><name><text>Four</text></name></question></quiz>"' .
                          ', "version": "1"}',
        );
        $this->createrepo->process($this->clihelper, $this->moodleinstances);

        // Check question files exist.
        $this->assertStringContainsString('One', file_get_contents($this->rootpath . '/top/One.xml'));
        $this->assertStringContainsString('Two', file_get_contents($this->rootpath . '/top/Default-for-Test-1/sub-1/Two.xml'));
        $this->assertStringContainsString('Three', file_get_contents($this->rootpath . '/top/Default-for-Test-1/sub-2/Three.xml'));
        $this->assertStringContainsString('Four', file_get_contents($this->rootpath . '/top/Default-for-Test-1/sub-2/Four.xml'));

        // Check category files exist.
        $this->assertStringContainsString('top', file_get_contents($this->rootpath . '/top/'. cli_helper::CATEGORY_FILE . '.xml'));
        $this->assertStringContainsString('top/Default for Test 1/sub 1',
                    file_get_contents($this->rootpath . '/top/Default-for-Test-1/sub-1/' . cli_helper::CATEGORY_FILE . '.xml'));
        $this->assertStringContainsString('top/Default for Test 1/sub 2',
                    file_get_contents($this->rootpath . '/top/Default-for-Test-1/sub-2/' . cli_helper::CATEGORY_FILE . '.xml'));
        $this->assertStringContainsString('top/Default for Test 1/sub 2',
                    file_get_contents($this->rootpath . '/top/Default-for-Test-1/sub-2/' . cli_helper::CATEGORY_FILE . '.xml'));

        $this->expectOutputRegex('/^\nAdded 4 questions.\n$/s');
        // No specified categoryname or id.
        $manifest = $this->createrepo->manifestcontents;
        $this->assertEquals("top", $manifest->context->defaultsubdirectory);
        $this->assertEquals(123, $manifest->context->defaultsubcategoryid);
    }

    /**
     * Test temp file creation.
     */
    public function test_temp_file_creation(): void {
        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturn(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                             "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
              "questions": [{"questionbankentryid": "1", "name": "One", "questioncategory": ""},
                            {"questionbankentryid": "2", "name": "Two", "questioncategory": ""},
                            {"questionbankentryid": "3", "name": "Three", "questioncategory": ""},
                            {"questionbankentryid": "4", "name": "Four", "questioncategory": ""}]}'
        );
        $this->curl->expects($this->exactly(4))->method('execute')->willReturnOnConsecutiveCalls(
            '{"question": "<quiz><question type=\"category\"><category><text>top</text></category></question>' .
                          '<question><name><text>One</text></name></question></quiz>", "version": "10"}',
            '{"question": "<quiz><question type=\"category\"><category><text>top/Default for Test 1/sub 1' .
                          '</text></category></question><question><name><text>Two</text></name></question></quiz>"' .
                          ', "version": "1"}',
            '{"question": "<quiz><question type=\"category\"><category><text>top/Default for Test 1/sub 2' .
                          '</text></category></question><question><name><text>Three</text></name></question></quiz>"' .
                          ', "version": "1"}',
            '{"question": "<quiz><question type=\"category\"><category><text>top/Default for Test 1/sub 2' .
                          '</text></category></question><question><name><text>Four</text></name></question></quiz>"' .
                          ', "version": "1"}',
        );
        $this->createrepo->directory = $this->rootpath;
        $this->createrepo->manifestcontents = new \stdClass();
        $this->createrepo->manifestcontents->context = null;
        $this->createrepo->manifestcontents->questions = [];
        $this->createrepo->export_to_repo();

        $tempfile = fopen($this->createrepo->tempfilepath, 'r');
        $firstline = json_decode(fgets($tempfile));
        $this->assertEquals('1', $firstline->questionbankentryid);
        $this->assertEquals($firstline->contextlevel, '50');
        $this->assertEquals($this->rootpath . '/top/One.xml', $firstline->filepath);
        $this->assertEquals($firstline->coursename, 'Course 1');
        $this->assertEquals($firstline->modulename, 'Module 1');
        $this->assertEquals($firstline->coursecategory, null);
        $this->assertEquals($firstline->version, '10');
        $this->assertEquals($firstline->format, 'xml');
    }

    /**
     * Test the full process with named subcategory.
     */
    public function test_process_with_named_subcategory(): void {
        $this->options['subcategory'] = 'top/Default for Test 1/sub 2';
        $this->clihelper = $this->getMockBuilder(\qbank_gitsync\cli_helper::class)->onlyMethods([
            'get_arguments', 'check_context',
        ])->setConstructorArgs([[]])->getMock();
        $this->clihelper->expects($this->any())->method('get_arguments')->will($this->returnValue($this->options));
        $this->clihelper->expects($this->any())->method('check_context')->willReturn(
            json_decode('{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                             "modulename":"Module 1", "instanceid":"", "qcategoryname":"top/Default for Test 1/sub 2",
                             "qcategoryid":123},
              "questions": []}')
        );
        $this->createrepo = $this->getMockBuilder(\qbank_gitsync\create_repo::class)->onlyMethods([
            'get_curl_request', 'call_exit',
        ])->setConstructorArgs([$this->clihelper, $this->moodleinstances])->getMock();

        $this->createrepo->curlrequest = $this->curl;
        $this->createrepo->listcurlrequest = $this->listcurl;

        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturn(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                             "modulename":"Module 1", "instanceid":"", "qcategoryname":"top/Default for Test 1/sub 2"},
              "questions": [{"questionbankentryid": "3", "name": "Three", "questioncategory": ""},
                            {"questionbankentryid": "4", "name": "Four", "questioncategory": ""}]}'
        );
        $this->curl->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            '{"question": "<quiz><question type=\"category\"><category><text>top/Default for Test 1/sub 2' .
                          '</text></category></question><question><name><text>Three</text></name></question></quiz>"' .
                          ', "version": "1"}',
            '{"question": "<quiz><question type=\"category\"><category><text>top/Default for Test 1/sub 2' .
                          '</text></category></question><question><name><text>Four</text></name></question></quiz>"' .
                          ', "version": "1"}',
        );
        $this->createrepo->process($this->clihelper, $this->moodleinstances);

        // Check question files exist.
        $this->assertStringContainsString('Three', file_get_contents($this->rootpath . '/top/Default-for-Test-1/sub-2/Three.xml'));
        $this->assertStringContainsString('Four', file_get_contents($this->rootpath . '/top/Default-for-Test-1/sub-2/Four.xml'));

        // Check category files exist.
        $this->assertStringContainsString('top/Default for Test 1/sub 2',
                    file_get_contents($this->rootpath . '/top/Default-for-Test-1/sub-2/' . cli_helper::CATEGORY_FILE . '.xml'));
        $this->assertStringContainsString('top/Default for Test 1/sub 2',
                    file_get_contents($this->rootpath . '/top/Default-for-Test-1/sub-2/' . cli_helper::CATEGORY_FILE . '.xml'));

        $this->expectOutputRegex('/^\nAdded 2 questions.\n$/s');
        $manifest = $this->createrepo->manifestcontents;
        $this->assertEquals("top/Default-for-Test-1/sub-2", $manifest->context->defaultsubdirectory);
        $this->assertEquals(123, $manifest->context->defaultsubcategoryid);
    }

    /**
     * Test the full process with subcategoryid.
     */
    public function test_process_with_subcategory_id(): void {
        $this->options['qcategoryid'] = 123;
        $this->clihelper = $this->getMockBuilder(\qbank_gitsync\cli_helper::class)->onlyMethods([
            'get_arguments', 'check_context',
        ])->setConstructorArgs([[]])->getMock();
        $this->clihelper->expects($this->any())->method('get_arguments')->will($this->returnValue($this->options));
        $this->clihelper->expects($this->any())->method('check_context')->willReturn(
            json_decode('{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                             "modulename":"Module 1", "instanceid":"", "qcategoryname":"top/Default for Test 1/sub 2",
                             "qcategoryid":123},
              "questions": []}')
        );
        $this->createrepo = $this->getMockBuilder(\qbank_gitsync\create_repo::class)->onlyMethods([
            'get_curl_request', 'call_exit',
        ])->setConstructorArgs([$this->clihelper, $this->moodleinstances])->getMock();

        $this->createrepo->curlrequest = $this->curl;
        $this->createrepo->listcurlrequest = $this->listcurl;

        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturn(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                             "modulename":"Module 1", "instanceid":"", "qcategoryname":"top/Default for Test 1/sub 2"},
              "questions": [{"questionbankentryid": "3", "name": "Three", "questioncategory": ""},
                            {"questionbankentryid": "4", "name": "Four", "questioncategory": ""}]}'
        );
        $this->curl->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            '{"question": "<quiz><question type=\"category\"><category><text>top/Default for Test 1/sub 2' .
                          '</text></category></question><question><name><text>Three</text></name></question></quiz>"' .
                          ', "version": "1"}',
            '{"question": "<quiz><question type=\"category\"><category><text>top/Default for Test 1/sub 2' .
                          '</text></category></question><question><name><text>Four</text></name></question></quiz>"' .
                          ', "version": "1"}',
        );
        $this->createrepo->process($this->clihelper, $this->moodleinstances);

        // Check question files exist.
        $this->assertStringContainsString('Three', file_get_contents($this->rootpath . '/top/Default-for-Test-1/sub-2/Three.xml'));
        $this->assertStringContainsString('Four', file_get_contents($this->rootpath . '/top/Default-for-Test-1/sub-2/Four.xml'));

        // Check category files exist.
        $this->assertStringContainsString('top/Default for Test 1/sub 2',
                    file_get_contents($this->rootpath . '/top/Default-for-Test-1/sub-2/' . cli_helper::CATEGORY_FILE . '.xml'));
        $this->assertStringContainsString('top/Default for Test 1/sub 2',
                    file_get_contents($this->rootpath . '/top/Default-for-Test-1/sub-2/' . cli_helper::CATEGORY_FILE . '.xml'));

        $this->expectOutputRegex('/^\nAdded 2 questions.\n$/s');
        $manifest = $this->createrepo->manifestcontents;
        $this->assertEquals("top/Default-for-Test-1/sub-2", $manifest->context->defaultsubdirectory);
        $this->assertEquals(123, $manifest->context->defaultsubcategoryid);
    }
}
