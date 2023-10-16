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
 * @group qbank_gitsync
 *
 * @covers \gitsync\import_repo::class
 */
class import_repo_test extends advanced_testcase {
    /** @var array mocked output of cli_helper->get_arguments */
    public array $options;
    /** @var array of instance names and URLs */
    public array $moodleinstances;
    /** @var cli_helper mocked cli_helper */
    public cli_helper $clihelper;
    /** @var curl_request mocked curl_request */
    public curl_request $curl;
    /** @var curl_request mocked curl_request for doc upload */
    public curl_request $uploadcurl;
     /** @var curl_request mocked curl_request for question list */
    public curl_request $listcurl;
    /** @var import_repo mocked for question delete */
    public curl_request $deletecurl;
    /** @var import_repo mocked import_repo */
    public import_repo $importrepo;
    /** @var string root of virtual file system */
    public string $rootpath;
    /** @var array used to store output of multiple calls to a function */
    public array $results;
    /** name of moodle instance for purpose of tests */
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
            'directory' => '',
            'subdirectory' => '/top',
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
        ])->setConstructorArgs(['xxxx'])->getMock();
        $this->uploadcurl = $this->getMockBuilder(\qbank_gitsync\curl_request::class)->onlyMethods([
            'execute'
        ])->setConstructorArgs(['xxxx'])->getMock();
        $this->deletecurl = $this->getMockBuilder(\qbank_gitsync\curl_request::class)->onlyMethods([
            'execute'
        ])->setConstructorArgs(['xxxx'])->getMock();
        $this->listcurl = $this->getMockBuilder(\qbank_gitsync\curl_request::class)->onlyMethods([
            'execute'
        ])->setConstructorArgs(['xxxx'])->getMock();
        $this->importrepo = $this->getMockBuilder(\qbank_gitsync\import_repo::class)->onlyMethods([
            'upload_file', 'handle_delete', 'call_exit'
        ])->setConstructorArgs([$this->clihelper, $this->moodleinstances])->getMock();
        $this->importrepo->curlrequest = $this->curl;
        $this->importrepo->deletecurlrequest = $this->deletecurl;
        $this->importrepo->listcurlrequest = $this->listcurl;
        $this->importrepo->uploadcurlrequest = $this->uploadcurl;
        $this->importrepo->expects($this->any())->method('upload_file')->will($this->returnValue(true));
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
            '{"questionbankentryid": "35001", "version": "2"}',
            '{"questionbankentryid": "35002", "version": "2"}',
            '{"questionbankentryid": "35004", "version": "2"}',
            '{"questionbankentryid": "35003", "version": "2"}',
        );

        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            '[]',
        );

        $this->importrepo->process();

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
                return '{"questionbankentryid": null, "version" : null}';
            })
        );
        $this->importrepo->import_categories();
        $this->assertContains($this->rootpath . '/top/cat 1/gitsync_category.xml', $this->results);
        $this->assertContains($this->rootpath . '/top/cat 2/gitsync_category.xml', $this->results);
        $this->assertContains($this->rootpath . '/top/cat 2/subcat 2_1/gitsync_category.xml', $this->results);
    }

    /**
     * Test importing categories broken JSON.
     * @covers \gitsync\import_repo\import_categories()
     */
    public function test_import_categories_broken_json(): void {
        $this->curl->expects($this->any())->method('execute')->willReturn(
            '{broken'
        );
        $this->importrepo->import_categories();
        $this->expectOutputRegex('/Broken JSON returned from Moodle:' .
                                 '.*{broken/s');
    }

    /**
     * Test importing categories exception.
     * @covers \gitsync\import_repo\import_categories()
     */
    public function test_import_categories_exception(): void {
        $this->curl->expects($this->any())->method('execute')->willReturn(
            '{"exception":"moodle_exception","message":"No token"}'
        );
        $this->importrepo->import_categories();
        $this->expectOutputRegex('/No token/');
    }

    /**
     * Test importing questions.
     * @covers \gitsync\import_repo\import_questions()
     */
    public function test_import_questions(): void {
        $this->results = [];
        $this->curl->expects($this->exactly(4))->method('execute')->willReturnOnConsecutiveCalls(
            '{"questionbankentryid": "35001", "version": "2"}',
            '{"questionbankentryid": "35002", "version": "2"}',
            '{"questionbankentryid": "35004", "version": "2"}',
            '{"questionbankentryid": "35003", "version": "2"}',
        );
        $this->curl->expects($this->exactly(4))->method('execute')->will($this->returnCallback(
            function() {
                $this->results[] = [
                                    $this->importrepo->subdirectoryiterator->getPathname(),
                                    $this->importrepo->postsettings['qcategoryname']
                                   ];
            })
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
     * Test importing questions broken JSON.
     * @covers \gitsync\import_repo\import_questions()
     */
    public function test_import_questions_broken_json(): void {
        $this->curl->expects($this->any())->method('execute')->willReturn(
            '{broken'
        );
        $this->importrepo->import_questions();
        $this->expectOutputRegex('/Broken JSON returned from Moodle:' .
                                 '.*{broken/s');
    }

    /**
     * Test importing questions exception.
     * @covers \gitsync\import_repo\import_questions()
     */
    public function test_import_questions_exception(): void {
        $this->curl->expects($this->any())->method('execute')->willReturn(
            '{"exception":"moodle_exception","message":"No token"}'
        );
        $this->importrepo->import_questions();
        $this->expectOutputRegex('/No token/');
    }

    /**
     * Test importing questions from only a subdirectory of questions
     * @covers \gitsync\import_repo\import_questions()
     */
    public function test_import_subdirectory_questions(): void {
        $this->results = [];
        $this->curl->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            '{"questionbankentryid": "35001", "version": "2"}',
            '{"questionbankentryid": "35002", "version": "2"}',
        );
        $this->curl->expects($this->exactly(2))->method('execute')->will($this->returnCallback(
            function() {
                $this->results[] = [
                                    $this->importrepo->subdirectoryiterator->getPathname(),
                                    $this->importrepo->postsettings['qcategoryname']
                                   ];
            })
        );
        $this->importrepo->subdirectory = '/top/cat 2/subcat 2_1';
        $this->importrepo->postsettings = [
            'contextlevel' => '10',
            'coursename' => 'Course 1',
            'modulename' => 'Test 1',
            'coursecategory' => 'Cat 1',
        ];
        $this->importrepo->import_questions();
        $this->assertContains([$this->rootpath .
                               '/top/cat 2/subcat 2_1/Third Question.xml', 'top/cat 2/subcat 2_1'], $this->results);
        $this->assertContains([$this->rootpath .
                               '/top/cat 2/subcat 2_1/Fourth Question.xml', 'top/cat 2/subcat 2_1'], $this->results);

        // Check temp manifest file created.
        $this->assertEquals(file_exists($this->importrepo->tempfilepath), true);
        $this->assertEquals(2, count(file($this->importrepo->tempfilepath)));
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
     * Test importing existing questions.
     * @covers \gitsync\import_repo\import_questions()
     */
    public function test_import_existing_questions(): void {
        $manifestcontents = '{"context":{"contextlevel":70,"coursename":"Course 1","modulename":"Test 1","coursecategory":null},
                             "questions":[{
                                "questionbankentryid":"1",
                                "filepath":"/top/cat 1/First Question.xml",
                                "format":"xml"
                            }, {
                                "questionbankentryid":"2",
                                "filepath":"/top/cat 2/subcat 2_1/Third Question.xml",
                                "format":"xml"
                            }]}';
        $this->importrepo->manifestcontents = json_decode($manifestcontents);
        $this->results = [];
        $this->curl->expects($this->exactly(4))->method('execute')->willReturnOnConsecutiveCalls(
            '{"questionbankentryid": "35001", "version": "2"}',
            '{"questionbankentryid": "35002", "version": "2"}',
            '{"questionbankentryid": "1", "version": "2"}',
            '{"questionbankentryid": "2", "version": "2"}',
        );
        $this->curl->expects($this->exactly(4))->method('execute')->will($this->returnCallback(
            function() {
                $this->results[] = [
                                    $this->importrepo->subdirectoryiterator->getPathname(),
                                    $this->importrepo->postsettings['qcategoryname'],
                                    $this->importrepo->postsettings['questionbankentryid']
                                   ];
            })
        );
        $this->importrepo->postsettings = [
            'contextlevel' => '10',
            'coursename' => 'Course 1',
            'modulename' => 'Test 1',
            'coursecategory' => 'Cat 1',
        ];
        $this->importrepo->import_questions();
        // Check questions in manifest pass questionbankentryid to webservice but the others don't.
        $this->assertContains([$this->rootpath . '/top/cat 1/First Question.xml', 'top/cat 1', '1'], $this->results);
        $this->assertContains([$this->rootpath .
                               '/top/cat 2/subcat 2_1/Third Question.xml', 'top/cat 2/subcat 2_1', '2'], $this->results);
        $this->assertContains([$this->rootpath .
                               '/top/cat 2/subcat 2_1/Fourth Question.xml', 'top/cat 2/subcat 2_1', null], $this->results);
        $this->assertContains([$this->rootpath . '/top/cat 2/Second Question.xml', 'top/cat 2', null], $this->results);
    }

    /**
     * Test importing existing questions only occurs if commit hashes don't match.
     * @covers \gitsync\import_repo\import_questions()
     */
    public function test_import_with_commit_hashes(): void {
        $manifestcontents = '{"context":{"contextlevel":70,"coursename":"Course 1","modulename":"Test 1","coursecategory":null},
                              "questions":[{
                                 "questionbankentryid":"1",
                                 "currentcommit":"matched",
                                 "moodlecommit":"matched",
                                 "filepath":"/top/cat 1/First Question.xml",
                                 "format":"xml"
                             }, {
                                "questionbankentryid":"2",
                                "filepath":"/top/cat 2/subcat 2_1/Third Question.xml",
                                "currentcommit":"notmatched",
                                "format":"xml"
                            }, {
                                "questionbankentryid":"3",
                                "filepath":"/top/cat 2/subcat 2_1/Fourth Question.xml",
                                "currentcommit":"notmatched",
                                "moodlecommit":"notmatched!",
                                "format":"xml"
                            }]}';
        $this->importrepo->manifestcontents = json_decode($manifestcontents);
        $this->results = [];
        $this->curl->expects($this->exactly(3))->method('execute')->willReturnOnConsecutiveCalls(
            '{"questionbankentryid": "35002", "version": "2"}',
            '{"questionbankentryid": "2", "version": "2"}',
            '{"questionbankentryid": "3", "version": "2"}',
        );
        $this->curl->expects($this->exactly(3))->method('execute')->will($this->returnCallback(
            function() {
                $this->results[] = [
                                    $this->importrepo->subdirectoryiterator->getPathname(),
                                    $this->importrepo->postsettings['qcategoryname'],
                                    $this->importrepo->postsettings['questionbankentryid']
                                ];
            })
        );
        $this->importrepo->postsettings = [
            'contextlevel' => '10',
            'coursename' => 'Course 1',
            'modulename' => 'Test 1',
            'coursecategory' => 'Cat 1',
        ];
        $this->importrepo->import_questions();
        // Check question with matching hashes wasn't imported.
        $this->assertNotContains([$this->rootpath . '/top/cat 1/First Question.xml', 'top/cat 1', '1'], $this->results);
        $this->assertContains([$this->rootpath .
                            '/top/cat 2/subcat 2_1/Third Question.xml', 'top/cat 2/subcat 2_1', '2'], $this->results);
        $this->assertContains([$this->rootpath .
                            '/top/cat 2/subcat 2_1/Fourth Question.xml', 'top/cat 2/subcat 2_1', '3'], $this->results);
        $this->assertContains([$this->rootpath . '/top/cat 2/Second Question.xml', 'top/cat 2', null], $this->results);
    }


    /**
     * Test message displayed when an invalid directory is used.
     * @covers \gitsync\import_repo\import_questions()
     */
    public function test_import_questions_wrong_directory(): void {
        $this->importrepo->directory = $this->rootpath;
        $this->importrepo->subdirectory = '';
        $this->curl->expects($this->any())->method('execute')->will(
            $this->returnValue('{"questionbankentryid": "35001", "version": "2"}'));
        $wrongfile = fopen($this->rootpath . '\wrong.xml', 'a+');
        fclose($wrongfile);

        $this->importrepo->import_questions();
        $this->expectOutputRegex('/^Root directory should not contain XML files/');
    }

    /**
     * Test creation of manifest file.
     * @covers \gitsync\cli_helper\create_manifest_file()
     *
     * (Run the entire process and check the output to avoid lots of additonal setup of tempfile etc.)
     */
    public function test_manifest_file(): void {
        // The test repo has 2 categories and 1 subcategory. 1 question in each category and 2 in subcategory.
        // We expect 3 category calls to the webservice and 4 question calls.
        $this->importrepo->curlrequest->expects($this->exactly(7))->method('execute')->willReturnOnConsecutiveCalls(
            '{"questionbankentryid": null}',
            '{"questionbankentryid": null}',
            '{"questionbankentryid": null}',
            '{"questionbankentryid": "35001", "version": "2"}',
            '{"questionbankentryid": "35002", "version": "2"}',
            '{"questionbankentryid": "35004", "version": "2"}',
            '{"questionbankentryid": "35003", "version": "2"}',
        );

        $this->importrepo->listcurlrequest->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            '[]',
        );

        $this->importrepo->process();

        // Manifest file is a single array.
        $this->assertEquals(1, count(file($this->importrepo->manifestpath)));
        $manifestcontents = json_decode(file_get_contents($this->importrepo->manifestpath));
        $this->assertCount(4, $manifestcontents->questions);

        $manifestentries = array_column($manifestcontents->questions, null, 'questionbankentryid');
        $this->assertArrayHasKey('35001', $manifestentries);
        $this->assertArrayHasKey('35002', $manifestentries);
        $this->assertArrayHasKey('35003', $manifestentries);
        $this->assertArrayHasKey('35004', $manifestentries);

        $context = $manifestcontents->context;
        $this->assertEquals($context->contextlevel, '10');
        $this->assertEquals($context->coursename, '');
        $this->assertEquals($context->modulename, '');
        $this->assertEquals($context->coursecategory, '');

        $samplerecord = $manifestentries['35004'];
        $this->assertStringContainsString('/top/cat ', $samplerecord->filepath);
        $this->assertEquals($samplerecord->format, 'xml');
        $this->assertEquals($samplerecord->moodlecommit, '35004test');

        $samplerecord = $manifestentries['35001'];
        $this->assertEquals(false, isset($samplerecord->moodlecommit));
    }

    /**
     * Test update of manifest file.
     * @covers \gitsync\cli_helper\create_manifest_file()
     */
    public function test_manifest_file_update(): void {
        $manifestcontents = '{"context":{"contextlevel":70,
                                "coursename":"Course 1",
                                "modulename":"Test 1",
                                "coursecategory":null,
                                "qcategoryname":"/top"
                             },
                             "questions":[{
                                "questionbankentryid":"1",
                                "filepath":"/top/cat 1/First Question.xml",
                                "version": "1",
                                "exportedversion": "1",
                                "format":"xml"
                             }, {
                                "questionbankentryid":"2",
                                "filepath":"/top/cat 2/subcat 2_1/Third Question.xml",
                                "version": "1",
                                "exportedversion": "1",
                                "currentcommit": "test",
                                "format":"xml"
                             }]}';
        $tempcontents = '{"questionbankentryid":"1",' .
                          '"filepath":"/top/cat 1/First Question.xml",' .
                          '"version": "5",' .
                          '"format":"xml"}' . "\n" .
                        '{"questionbankentryid":"3",' .
                          '"filepath":"/top/cat 2/Second Question.xml",' .
                          '"version": "6",' .
                          '"format":"xml"}' . "\n" .
                        '{"questionbankentryid":"2",' .
                          '"filepath":"/top/cat 2/subcat 2_1/Third Question.xml",' .
                          '"version": "7",' .
                          '"format":"xml"}' . "\n" .
                        '{"questionbankentryid":"4",' .
                          '"filepath":"/top/cat 2/subcat 2_1/Fourth Question.xml",' .
                          '"version": "8",' .
                          '"moodlecommit": "test",' .
                          '"format":"xml"}' . "\n";
        $this->importrepo->manifestcontents = json_decode($manifestcontents);
        file_put_contents($this->importrepo->tempfilepath, $tempcontents);

        cli_helper::create_manifest_file($this->importrepo->manifestcontents,
                                        $this->importrepo->tempfilepath, $this->importrepo->manifestpath,
                                        'www.moodle');

        $manifestcontents = json_decode(file_get_contents($this->importrepo->manifestpath));
        $this->assertCount(4, $manifestcontents->questions);

        $manifestentries = array_column($manifestcontents->questions, null, 'questionbankentryid');
        $this->assertArrayHasKey('1', $manifestentries);
        $this->assertArrayHasKey('2', $manifestentries);
        $this->assertArrayHasKey('3', $manifestentries);
        $this->assertArrayHasKey('4', $manifestentries);

        $this->assertEquals('5', $manifestentries['1']->version);
        $this->assertEquals('1', $manifestentries['1']->exportedversion);
        $context = $manifestcontents->context;
        $this->assertEquals($context->contextlevel, '70');
        $this->assertEquals($context->coursename, 'Course 1');
        $this->assertEquals($context->modulename, 'Test 1');
        $this->assertEquals($context->coursecategory, null);

        $samplerecord = $manifestentries['1'];
        $this->assertEquals('/top/cat 1/First Question.xml', $samplerecord->filepath);

        $samplerecord = $manifestentries['4'];
        $this->assertEquals('test', $samplerecord->moodlecommit);
    }

    /**
     * Test delete of questions with no file in repo.
     * @covers \gitsync\import_repo\delete_no_file_questions()
     */
    public function test_delete_no_file_questions(): void {
        $this->importrepo->manifestpath = $this->rootpath . '/' . self::MOODLE . cli_helper::MANIFEST_FILE;
        // 4 files in the manifest.
        $manifestcontents = '{"context":{
                                "contextlevel":70,
                                "coursename":"Course 1",
                                "modulename":"Test 1",
                                "coursecategory":null,
                                "qcategoryname":"/top"
                             },
                             "questions":[{
                                "questionbankentryid":"1",
                                "filepath":"/top/cat 1/First Question.xml",
                                "format":"xml"
                             }, {
                                "questionbankentryid":"2",
                                "filepath":"/top/cat 2/subcat 2_1/Third Question.xml",
                                "format":"xml"
                             }, {
                                "questionbankentryid":"3",
                                "filepath":"/top/cat 2/Second Question.xml",
                                "format":"xml"
                             }, {
                                "questionbankentryid":"4",
                                "filepath":"/top/cat 2/subcat 2_1/Fourth Question.xml",
                                "format":"xml"
                             }]}';
        $this->importrepo->manifestcontents = json_decode($manifestcontents);
        file_put_contents($this->importrepo->manifestpath, $manifestcontents);

        // Delete 2 of the files.
        unlink($this->rootpath . '/top/cat 2/subcat 2_1/Third Question.xml');
        unlink($this->rootpath . '/top/cat 2/Second Question.xml');

        // One question deleted of two that no longer have files.
        $this->importrepo->expects($this->exactly(2))->method('handle_delete')->willReturnOnConsecutiveCalls(
            true, false
        );

        $this->importrepo->delete_no_file_questions();

        // One manifest record removed.
        $manifestcontents = json_decode(file_get_contents($this->importrepo->manifestpath));
        $this->assertEquals(3, count($manifestcontents->questions));
        $questionbankentryids = array_map(function($q) {
            return $q->questionbankentryid;
        }, $manifestcontents->questions);
        $this->assertEquals(3, count($questionbankentryids));
        $this->assertContains('1', $questionbankentryids);
        $this->assertContains('4', $questionbankentryids);

        // Performing expectOutputRegex multiple times causes them all to pass regardless of content.
        // Modifier 's' handles line breaks within match any characters '.*'.
        $this->expectOutputRegex('/These questions are listed in the manifest but there is no longer a matching file' .
                                 '.*top\/cat 2\/subcat 2_1\/Third Question.xml' .
                                 '.*top\/cat 2\/Second Question.xml/s');
    }

    /**
     * Test delete of questions with no file in repo.
     * @covers \gitsync\import_repo\delete_no_file_questions()
     */
    public function test_delete_no_record_questions(): void {
        // 2 records in the manifest.
        $manifestcontents = '{"context":{
                                "contextlevel":70,
                                "coursename":"Course 1",
                                "modulename":"Test 1",
                                "coursecategory":null,
                                "qcategoryname":"/top"
                              },
                              "questions":[{
                                "questionbankentryid":"1",
                                "filepath":"/top/cat 1/First Question.xml",
                                "format":"xml"
                              }, {
                                "questionbankentryid":"2",
                                "filepath":"/top/cat 2/subcat 2_1/Third Question.xml",
                                "format":"xml"
                              }]}';
        $this->importrepo->manifestcontents = json_decode($manifestcontents);
        $this->importrepo->listcurlrequest = $this->listcurl;
        // One question deleted of two that no longer have files.
        $this->importrepo->expects($this->exactly(2))->method('handle_delete')->willReturnOnConsecutiveCalls(
            true, false
        );
        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            '[{"questionbankentryid": "1", "name": "First Question", "questioncategory": "cat 1"},
              {"questionbankentryid": "2", "name": "Third Question", "questioncategory": "subcat 2_1"},
              {"questionbankentryid": "3", "name": "Second Question", "questioncategory": "cat 1"},
              {"questionbankentryid": "4", "name": "Fourth Question", "questioncategory": "cat 1"}]'
        );
        $this->importrepo->delete_no_record_questions();

        $this->expectOutputRegex('/These questions are in Moodle but not linked to your repository:' .
                                 '.*cat 1 - Second Question' .
                                 '.*cat 1 - Fourth Question/s');
    }

    /**
     * Test deleting questions broken JSON.
     * @covers \gitsync\import_repo\delete_no_record_questions()
     */
    public function test_delete_questions_broken_json(): void {
        $this->listcurl->expects($this->any())->method('execute')->willReturn(
            '{broken'
        );
        $this->importrepo->delete_no_record_questions();
        $this->expectOutputRegex('/Broken JSON returned from Moodle:' .
                                 '.*{broken/s');
    }

    /**
     * Test deleting questions exception.
     * @covers \gitsync\import_repo\delete_no_record_questions()
     */
    public function test_delete_questions_exception(): void {
        $this->listcurl->expects($this->any())->method('execute')->willReturn(
            '{"exception":"moodle_exception","message":"No token"}'
        );
        $this->importrepo->delete_no_record_questions();
        $this->expectOutputRegex('/No token/');
    }

    /**
     * Check abort if question version in Moodle doesn't match a version in manifest.
     * @covers \gitsync\export_repo\tidy_manifest()
     */
    public function test_check_question_versions():void {
        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            '[{"questionbankentryid": "35001", "name": "One", "questioncategory": "", "version": "1"},
              {"questionbankentryid": "35002", "name": "Two", "questioncategory": "TestC", "version": "2"},
              {"questionbankentryid": "35003", "name": "Three", "questioncategory": "", "version": "1"},
              {"questionbankentryid": "35004", "name": "Four", "questioncategory": "", "version": "1"}]'
            );
        $this->importrepo->check_question_versions();

        $this->expectOutputRegex('/35002 - TestC - Two' .
                                 '.*Moodle question version: 2' .
                                 '.*Version on last import to Moodle: 6' .
                                 '.*Version on last export from Moodle: 7' .
                                 '.*Export questions from Moodle before proceeding/s');
    }

    /**
     * Test version check passes if exported version matches.
     * @covers \gitsync\export_repo\tidy_manifest()
     */
    public function test_check_question_export_version_success():void {
        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            '[{"questionbankentryid": "35001", "name": "One", "questioncategory": "", "version": "1"},
              {"questionbankentryid": "35002", "name": "Two", "questioncategory": "TestC", "version": "7"},
              {"questionbankentryid": "35003", "name": "Three", "questioncategory": "", "version": "1"},
              {"questionbankentryid": "35004", "name": "Four", "questioncategory": "", "version": "1"}]'
            );
        $this->importrepo->check_question_versions();

        $this->expectOutputString('');
    }

    /**
     * Test version check passes if imported version matches.
     * @covers \gitsync\export_repo\tidy_manifest()
     */
    public function test_check_question_import_version_success():void {
        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            '[{"questionbankentryid": "35001", "name": "One", "questioncategory": "", "version": "1"},
              {"questionbankentryid": "35002", "name": "Two", "questioncategory": "TestC", "version": "6"},
              {"questionbankentryid": "35003", "name": "Three", "questioncategory": "", "version": "1"},
              {"questionbankentryid": "35004", "name": "Four", "questioncategory": "", "version": "1"}]'
            );
        $this->importrepo->check_question_versions();

        $this->expectOutputString('');
    }

    /**
     * Test version check broken JSON.
     * @covers \gitsync\import_repo\check_question_versions()
     */
    public function test_check_versions_broken_json(): void {
        $this->listcurl->expects($this->any())->method('execute')->willReturn(
            '{broken'
        );
        $this->importrepo->check_question_versions();
        $this->expectOutputRegex('/Broken JSON returned from Moodle:' .
                                 '.*{broken/s');
    }

    /**
     * Test version check exception.
     * @covers \gitsync\import_repo\check_question_versions()
     */
    public function test_check_version_exception(): void {
        $this->listcurl->expects($this->any())->method('execute')->willReturn(
            '{"exception":"moodle_exception","message":"No token"}'
        );
        $this->importrepo->check_question_versions();
        $this->expectOutputRegex('/No token/');
    }
}
