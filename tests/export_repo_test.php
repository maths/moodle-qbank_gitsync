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
            'manifestpath' => $this->rootpath . '/' . self::MOODLE . '_system' . cli_helper::MANIFEST_FILE,
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
        $this->exportrepo = $this->getMockBuilder(\qbank_gitsync\export_repo::class)->onlyMethods([
            'get_curl_request'
        ])->getMock();
        $this->exportrepo->expects($this->any())->method('get_curl_request')->will($this->returnValue($this->curl));

        $this->exportrepo->postsettings = ['questionbankentryid' => null];
    }

    /**
     * Test the full process.
     */
    public function test_process(): void {
        // The test repo has 2 categories and 1 subcategory. 1 question in each category and 2 in subcategory.
        // We expect 3 category calls to the webservice and 4 question calls.
        $this->curl->expects($this->exactly(4))->method('execute')->willReturnOnConsecutiveCalls(
            '{"question": "<Question><Name>One</Name></Question>"}',
            '{"question": "<Question><Name>Three</Name></Question>"}',
            '{"question": "<Question><Name>Four</Name></Question>"}',
            '{"question": "<Question><Name>Two</Name></Question>"}'
        );

        $this->exportrepo->process($this->clihelper, $this->moodleinstances);

        // Check question files updated.
        $this->assertStringContainsString('One', file_get_contents($this->rootpath . '/top/cat 1/First Question.xml'));
        $this->assertStringContainsString('Two', file_get_contents($this->rootpath . '/top/cat 2/Second Question.xml'));
        $this->assertStringContainsString('Three', file_get_contents($this->rootpath . '/top/cat 2/subcat 2_1/Third Question.xml'));
        $this->assertStringContainsString('Four', file_get_contents($this->rootpath . '/top/cat 2/subcat 2_1/Fourth Question.xml'));
    }

    public function test_check_formatting(): void {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><quiz><!-- Unwanted comment --><question><questiontext format="html">' .
            '<text><![CDATA[<p>Paragraph 1<br><ul><li>Item 1</li><li>Item 2</li></ul></p>]]>' .
            '</text></questiontext></question></quiz>';
        $expectedresult = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<quiz>\n  <question>\n    <questiontext format=\"html\">" .
            "\n      <text>\n        <![CDATA[\n        <p>\n          Paragraph 1\n          <br />\n        </p>\n        <ul>" .
            "\n          <li>Item 1\n          </li>\n          <li>Item 2\n          </li>\n        </ul>\n        <p></p>" .
            "\n        ]]>\n      </text>\n    </questiontext>\n  </question>\n</quiz>";
        $result = $this->exportrepo->reformat_question($xml);

        // Output will depend on Tidy being installed.
        if (!function_exists('tidy_repair_string')) {
            $this->assertEquals($result, $xml);
        } else {
            $this->assertEquals($result, $expectedresult);
        }
    }
}
