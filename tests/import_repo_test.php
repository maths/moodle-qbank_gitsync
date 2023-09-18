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
    /** @var mocked curl_request for doc upload */
    public curl_request $uploadcurl;
    /** @var mocked import_repo */
    public import_repo $importrepo;
    /** @var root of virtual file system */
    public string $rootpath;
    /** @var used to store output of multiple calls to a function */
    public array $results;
    /** @var name of moodle instance for purpose of tests */
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
            'subdirectory' => '',
            'contextlevel' => 'system',
            'coursename' => 'Course 1',
            'modulename' => 'Test 1',
            'coursecategory' => 'Cat 1',
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
        $this->uploadcurl = $this->getMockBuilder(\qbank_gitsync\curl_request::class)->onlyMethods([
            'execute'
        ])->setConstructorArgs(['xxxx'])->getMock();;
        $this->importrepo = $this->getMockBuilder(\qbank_gitsync\import_repo::class)->onlyMethods([
            'get_curl_request', 'upload_file'
        ])->getMock();
        $this->importrepo->expects($this->any())->method('get_curl_request')->will($this->returnValue($this->curl));
        $this->importrepo->expects($this->any())->method('upload_file')->will($this->returnValue(true));

        $this->importrepo->directory = $this->rootpath;
        $this->importrepo->subdirectory = '';
        $this->importrepo->manifestcontents = new \StdClass();
        $this->importrepo->manifestcontents->context = null;
        $this->importrepo->manifestcontents->questions = [];
        $this->importrepo->postsettings = ['contextlevel' => null, 'coursename' => null, 'modulename' => null,
                                           'directory' => '', 'subdirectory' => '',
                                           'fileinfo[contextid]' => '', 'fileinfo[userid]' => '',
                                           'fileinfo[component]' => '', 'fileinfo[filearea]' => '',
                                           'fileinfo[itemid]' => '', 'fileinfo[filepath]' => '',
                                           'fileinfo[filename]' => '',
                                           'coursecategory' => ''];
    }

    /**
     * Test the full process.
     */
    public function test_process(): void {
        // The test repo has 2 categories and 1 subcategory. 1 question in each category and 2 in subcategory.
        // We expect 3 category calls to the webservice and 4 question calls.
        $this->curl->expects($this->exactly(7))->method('execute')->willReturnOnConsecutiveCalls(
            '{"questionbankentryid": null}',
            '{"questionbankentryid": null}',
            '{"questionbankentryid": null}',
            '{"questionbankentryid": 35001}',
            '{"questionbankentryid": 35002}',
            '{"questionbankentryid": 35004}',
            '{"questionbankentryid": 35003}',
        );

        $this->importrepo->process($this->clihelper, $this->moodleinstances);

        // Check manifest file created.
        $this->assertEquals(file_exists($this->rootpath . '/' . self::MOODLE . '_system' . cli_helper::MANIFEST_FILE), true);
    }


    /**
     * Test importing categories.
     * @covers \gitsync\import_repo\import_categories()
     */
    public function test_import_categories(): void {
        $this->results = [];
        $this->curl->expects($this->exactly(3))->method('execute')->will($this->returnCallback(
            function() {
                $this->results[] = $this->importrepo->repoiterator->getPathname();
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
            '{"questionbankentryid": 35001}',
            '{"questionbankentryid": 35002}',
            '{"questionbankentryid": 35004}',
            '{"questionbankentryid": 35003}',
        );
        $this->curl->expects($this->exactly(4))->method('execute')->will($this->returnCallback(
            function() {
                $this->results[] = [
                                    $this->importrepo->subdirectoryiterator->getPathname(),
                                    $this->importrepo->postsettings['qcategoryname']
                                   ];
            })
        );
        $this->importrepo->curlrequest = $this->curl;
        $this->importrepo->subdirectoryiterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->rootpath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $this->importrepo->postsettings = [
            'contextlevel' => '10',
            'coursename' => 'Course 1',
            'modulename' => 'Test 1',
            'coursecategory' => 'Cat 1',
        ];
        $this->importrepo->import_questions();
        $this->assertContains([$this->rootpath . '/top/cat 1/First Question.xml', 'top/cat 1'], $this->results);
        $this->assertContains([$this->rootpath .
                               '/top/cat 2/subcat 2_1/Third Question.xml', 'top/cat 2/subcat 2_1'], $this->results);
        $this->assertContains([$this->rootpath .
                               '/top/cat 2/subcat 2_1/Fourth Question.xml', 'top/cat 2/subcat 2_1'], $this->results);
        $this->assertContains([$this->rootpath . '/top/cat 2/Second Question.xml', 'top/cat 2'], $this->results);

        // Check temp manifest file created.
        $this->assertEquals(file_exists($this->importrepo->tempfilepath), true);
        $this->assertEquals(4, count(file($this->importrepo->tempfilepath)));
        $tempfile = fopen($this->importrepo->tempfilepath, 'r');
        $firstline = json_decode(fgets($tempfile));
        $this->assertStringContainsString('3500', $firstline->questionbankentryid);
        $this->assertEquals($firstline->contextlevel, '10');
        $this->assertStringContainsString($this->rootpath . '/top/cat ', $firstline->filepath);
        $this->assertEquals($firstline->coursename, 'Course 1');
        $this->assertEquals($firstline->modulename, 'Test 1');
        $this->assertEquals($firstline->coursecategory, 'Cat 1');
        $this->assertEquals($firstline->format, 'xml');
    }

    /**
     * Test message displayed when an invalid directory is used.
     * @covers \gitsync\import_repo\import_questions()
     */
    public function test_import_questions_wrong_directory(): void {
        $this->importrepo->tempfilepath = $this->rootpath . '/' . self::MOODLE . '_manifest_update.tmp';
        $this->curl->expects($this->any())->method('execute')->will($this->returnValue('{"questionbankentryid": 35001}'));
        $this->importrepo->curlrequest = $this->curl;
        $wrongfile = fopen($this->rootpath . '\wrong.xml', 'a+');
        fclose($wrongfile);

        $this->importrepo->subdirectoryiterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->rootpath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $this->importrepo->import_questions();
        $this->expectOutputRegex('/^Root directory should not contain XML files/');
    }

    /**
     * Test creation of manifest file.
     *
     * (Run the entire process and check the output to avoid lots of additonal setup of tempfile etc.)
     */
    public function test_manifest_file(): void {
        // The test repo has 2 categories and 1 subcategory. 1 question in each category and 2 in subcategory.
        // We expect 3 category calls to the webservice and 4 question calls.
        $this->curl->expects($this->exactly(7))->method('execute')->willReturnOnConsecutiveCalls(
            '{"questionbankentryid": null}',
            '{"questionbankentryid": null}',
            '{"questionbankentryid": null}',
            '{"questionbankentryid": 35001}',
            '{"questionbankentryid": 35002}',
            '{"questionbankentryid": 35004}',
            '{"questionbankentryid": 35003}',
        );

        $this->importrepo->process($this->clihelper, $this->moodleinstances);

        // Manifest file is a single array.
        $this->assertEquals(1, count(file($this->importrepo->manifestpath)));
        $manifestcontents = json_decode(file_get_contents($this->importrepo->manifestpath));
        $this->assertEquals(4, count($manifestcontents->questions));
        $questionbankentryids = array_map(function($q) {
            return $q->questionbankentryid;
        }, $manifestcontents->questions);
        $this->assertEquals(4, count($questionbankentryids));
        $this->assertContains(35001, $questionbankentryids);
        $this->assertContains(35002, $questionbankentryids);
        $this->assertContains(35003, $questionbankentryids);
        $this->assertContains(35004, $questionbankentryids);

        $context = $manifestcontents->context;
        $this->assertEquals($context->contextlevel, '10');
        $this->assertEquals($context->coursename, 'Course 1');
        $this->assertEquals($context->modulename, 'Test 1');
        $this->assertEquals($context->coursecategory, 'Cat 1');

        $samplerecords = array_filter($manifestcontents->questions, function($q) {
            return $q->questionbankentryid === 35004;
        });
        $samplerecord = reset($samplerecords);
        $this->assertStringContainsString($this->rootpath . '/top/cat ', $samplerecord->filepath);
        $this->assertEquals($samplerecord->format, 'xml');

    }
}
