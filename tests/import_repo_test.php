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
 * Unit tests for import repo command line script for gitsync
 *
 * @package    qbank_gitsync
 * @copyright  2023 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_gitsync;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
use advanced_testcase;
use org\bovigo\vfs\vfsStream;

/**
 * Test the CLI script for importing a repo to Moodle.
 *
 * @covers \gitsync\import_repo::class
 */
class import_repo_test extends advanced_testcase {
    /** @var mocked output of cli_helper->get_arguments */
    public array $options;
    /** @var array of instance names and URLs */
    public array $moodleinstances;
    /** @var mocked cli_helper */
    public cli_helper $clihelper;
    /** @var mocked curl_request */
    public curl_request $curl;
    /** @var mocked import_repo */
    public import_repo $importrepo;
    /** @var root of virtual file system */
    public string $rootpath;
    /** @var used to store output of multiple calls to a function */
    public array $results;
    /** @var name of moodle instance for urpose of tests */
    const MOODLE = 'fake';

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
            'directory' => $this->rootpath,
            'contextlevel' => 'system',
            'coursename' => 'Course 1',
            'modulename' => 'Test 1',
            'coursecategory' => null,
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
        ])->setConstructorArgs(['xxxx'])->getMock();;
        $this->importrepo = $this->getMockBuilder(\qbank_gitsync\import_repo::class)->onlyMethods([
            'get_curl_request'
        ])->getMock();
        $this->importrepo->expects($this->any())->method('get_curl_request')->will($this->returnValue($this->curl));

        $this->importrepo->postsettings = ['contextlevel' => null, 'coursename' => null, 'modulename' => null];
    }

    /**
     * Test the full process.
     */
    public function test_process(): void {
        // The test repo has 2 categories and 1 subcategory. 1 question in each category and 2 in subcategory.
        // We expect 3 category calls to the webservice and 4 question calls.
        $this->curl->expects($this->exactly(7))->method('execute')->willReturnOnConsecutiveCalls(
            '{"questionid": null}',
            '{"questionid": null}',
            '{"questionid": null}',
            '{"questionid": 35001}',
            '{"questionid": 35002}',
            '{"questionid": 35004}',
            '{"questionid": 35003}',
        );

        $this->importrepo->process($this->clihelper, $this->moodleinstances);

        // Check manifest file created.
        $this->assertEquals(file_exists($this->rootpath . '/' . self::MOODLE . import_repo::MANIFEST_FILE), true);
    }


    /**
     * Test importing categories.
     * @covers \gitsync\import_repo\import_categories()
     */
    public function test_import_categories(): void {
        $this->results = [];
        $this->curl->expects($this->exactly(3))->method('execute')->will($this->returnCallback(
            function() {
                $this->results[] = $this->importrepo->postsettings['filepath'];
            })
        );
        $this->importrepo->curlrequest = $this->curl;
        $this->importrepo->repoiterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->rootpath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $this->importrepo->import_categories();
        $this->assertContains($this->rootpath . '/top/cat 1/gitsync_category.xml', $this->results);
        $this->assertContains($this->rootpath . '/top/cat 2/gitsync_category.xml', $this->results);
        $this->assertContains($this->rootpath . '/top/cat 2/subcat 2_1/gitsync_category.xml', $this->results);
    }

    /**
     * Test importing questions.
     * @covers \gitsync\import_repo\import_questions()
     */
    public function test_import_questions(): void {
        $this->importrepo->tempfilepath = $this->rootpath . '/' . self::MOODLE . '_manifest_update.tmp';
        $this->results = [];
        $this->curl->expects($this->exactly(4))->method('execute')->willReturnOnConsecutiveCalls(
            '{"questionid": 35001}',
            '{"questionid": 35002}',
            '{"questionid": 35004}',
            '{"questionid": 35003}',
        );
        $this->curl->expects($this->exactly(4))->method('execute')->will($this->returnCallback(
            function() {
                $this->results[] = [
                                    $this->importrepo->postsettings['filepath'],
                                    $this->importrepo->postsettings['categoryname']
                                   ];
            })
        );
        $this->importrepo->curlrequest = $this->curl;
        $this->importrepo->repoiterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->rootpath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $this->importrepo->import_questions();
        $this->assertContains([$this->rootpath . '/top/cat 1/First Question.xml', 'top/cat 1'], $this->results);
        $this->assertContains([$this->rootpath .
                               '/top/cat 2/subcat 2_1/Third Question.xml', 'top/cat 2/subcat 2_1'], $this->results);
        $this->assertContains([$this->rootpath .
                               '/top/cat 2/subcat 2_1/Fourth Question.xml', 'top/cat 2/subcat 2_1'], $this->results);
        $this->assertContains([$this->rootpath . '/top/cat 2/Second Question.xml', 'top/cat 2'], $this->results);
    }

    /**
     * Test message displayed when an invalid directory is used.
     * @covers \gitsync\import_repo\import_questions()
     */
    public function test_import_questions_wrong_directory(): void {
        $this->importrepo->tempfilepath = $this->rootpath . '/' . self::MOODLE . '_manifest_update.tmp';
        $this->curl->expects($this->any())->method('execute')->will($this->returnValue('{"questionid": 35001}'));
        $this->importrepo->curlrequest = $this->curl;
        $this->importrepo->repoiterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->rootpath . '\top\cat 1', \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $this->importrepo->import_questions();
        $this->expectOutputRegex('/^Root directory should not contain XML files/');
    }
}
