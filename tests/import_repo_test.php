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
        $this->deletecurl = $this->getMockBuilder(\qbank_gitsync\curl_request::class)->onlyMethods([
            'execute'
        ])->setConstructorArgs(['xxxx'])->getMock();;
        $this->listcurl = $this->getMockBuilder(\qbank_gitsync\curl_request::class)->onlyMethods([
            'execute'
        ])->setConstructorArgs(['xxxx'])->getMock();;
        $this->importrepo = $this->getMockBuilder(\qbank_gitsync\import_repo::class)->onlyMethods([
            'get_curl_request', 'upload_file', 'handle_delete'
        ])->getMock();
        $this->importrepo->expects($this->any())->method('get_curl_request')->willReturnOnConsecutiveCalls(
            $this->curl, $this->deletecurl, $this->listcurl, $this->uploadcurl
        );
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

        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            '{}',
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
        $this->importrepo->tempfilepath = $this->rootpath . '/' . self::MOODLE . cli_helper::TEMP_MANIFEST_FILE;
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
     * Test importing questions from only a subdirectory of questions
     * @covers \gitsync\import_repo\import_questions()
     */
    public function test_import_subdirectory_questions(): void {
        $this->importrepo->tempfilepath = $this->rootpath . '/' . self::MOODLE . cli_helper::TEMP_MANIFEST_FILE;
        $this->results = [];
        $this->curl->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            '{"questionbankentryid": 35001}',
            '{"questionbankentryid": 35002}',
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
        $this->importrepo->curlrequest = $this->curl;

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
        $this->importrepo->tempfilepath = $this->rootpath . '/' . self::MOODLE . cli_helper::TEMP_MANIFEST_FILE;
        $manifestcontents = '{"context":{"contextlevel":70,"coursename":"Course 1","modulename":"Test 1","coursecategory":null},
                             "questions":[{
                                "questionbankentryid":"1",
                                "filepath":"' . $this->rootpath . '/top/cat 1/First Question.xml",
                                "format":"xml"
                            }, {
                                "questionbankentryid":"2",
                                "filepath":"' . $this->rootpath . '/top/cat 2/subcat 2_1/Third Question.xml",
                                "format":"xml"
                            }]}';
        $this->importrepo->manifestcontents = json_decode($manifestcontents);
        $this->results = [];
        $this->curl->expects($this->exactly(4))->method('execute')->willReturnOnConsecutiveCalls(
            '{"questionbankentryid": 35001}',
            '{"questionbankentryid": 35002}',
            '{"questionbankentryid": 1}',
            '{"questionbankentryid": 2}',
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
        $this->importrepo->curlrequest = $this->curl;
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
     * Test message displayed when an invalid directory is used.
     * @covers \gitsync\import_repo\import_questions()
     */
    public function test_import_questions_wrong_directory(): void {
        $this->importrepo->tempfilepath = $this->rootpath . '/' . self::MOODLE . cli_helper::TEMP_MANIFEST_FILE;
        $this->curl->expects($this->any())->method('execute')->will($this->returnValue('{"questionbankentryid": 35001}'));
        $this->importrepo->curlrequest = $this->curl;
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
        $this->curl->expects($this->exactly(7))->method('execute')->willReturnOnConsecutiveCalls(
            '{"questionbankentryid": null}',
            '{"questionbankentryid": null}',
            '{"questionbankentryid": null}',
            '{"questionbankentryid": 35001}',
            '{"questionbankentryid": 35002}',
            '{"questionbankentryid": 35004}',
            '{"questionbankentryid": 35003}',
        );

        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            '{}',
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

    /**
     * Test update of manifest file.
     * @covers \gitsync\cli_helper\create_manifest_file()
     */
    public function test_manifest_file_update(): void {
        // The test repo has 2 categories and 1 subcategory. 1 question in each category and 2 in subcategory.
        // We expect 3 category calls to the webservice and 4 question calls.
        $this->importrepo->manifestpath = $this->rootpath . '/' . self::MOODLE . cli_helper::MANIFEST_FILE;
        $this->importrepo->tempfilepath = $this->rootpath . '/' . self::MOODLE . cli_helper::TEMP_MANIFEST_FILE;
        $manifestcontents = '{"context":{"contextlevel":70,"coursename":"Course 1","modulename":"Test 1","coursecategory":null},
                             "questions":[{
                                "questionbankentryid":"1",
                                "filepath":"' . $this->rootpath . '/top/cat 1/First Question.xml",
                                "format":"xml"
                            }, {
                                "questionbankentryid":"2",
                                "filepath":"' . $this->rootpath . '/top/cat 2/subcat 2_1/Third Question.xml",
                                "format":"xml"
                            }]}';
        $tempcontents = '{"questionbankentryid":"1",' .
                          '"filepath":"' . $this->rootpath . '/top/cat 1/First Question.xml",' .
                          '"format":"xml"}' . "\n" .
                        '{"questionbankentryid":"3",' .
                          '"filepath":"' . $this->rootpath . '/top/cat 2/Second Question.xml",' .
                          '"format":"xml"}' . "\n" .
                        '{"questionbankentryid":"2",' .
                          '"filepath":"' . $this->rootpath . '/top/cat 2/subcat 2_1/Third Question.xml",' .
                          '"format":"xml"}' . "\n" .
                        '{"questionbankentryid":"4",' .
                          '"filepath":"' . $this->rootpath . '/top/cat 2/subcat 2_1/Fourth Question.xml",' .
                          '"format":"xml"}' . "\n";
        $this->importrepo->manifestcontents = json_decode($manifestcontents);
        file_put_contents($this->importrepo->tempfilepath, $tempcontents);

        cli_helper::create_manifest_file($this->importrepo->manifestcontents,
                                        $this->importrepo->tempfilepath, $this->importrepo->manifestpath);

        $manifestcontents = json_decode(file_get_contents($this->importrepo->manifestpath));
        $this->assertEquals(4, count($manifestcontents->questions));
        $questionbankentryids = array_map(function($q) {
            return $q->questionbankentryid;
        }, $manifestcontents->questions);
        $this->assertEquals(4, count($questionbankentryids));
        $this->assertContains('1', $questionbankentryids);
        $this->assertContains('2', $questionbankentryids);
        $this->assertContains('3', $questionbankentryids);
        $this->assertContains('4', $questionbankentryids);

        $context = $manifestcontents->context;
        $this->assertEquals($context->contextlevel, '70');
        $this->assertEquals($context->coursename, 'Course 1');
        $this->assertEquals($context->modulename, 'Test 1');
        $this->assertEquals($context->coursecategory, null);

        $samplerecords = array_filter($manifestcontents->questions, function($q) {
            return $q->questionbankentryid === '1';
        });
        $samplerecord = reset($samplerecords);
        $this->assertEquals($this->rootpath . '/top/cat 1/First Question.xml', $samplerecord->filepath);
    }

    /**
     * Test delete of questions with no file in repo.
     * @covers \gitsync\import_repo\delete_no_file_questions()
     */
    public function test_delete_no_file_questions(): void {
        $this->importrepo->manifestpath = $this->rootpath . '/' . self::MOODLE . cli_helper::MANIFEST_FILE;
        // 4 files in the manifest.
        $manifestcontents = '{"context":{"contextlevel":70,"coursename":"Course 1","modulename":"Test 1","coursecategory":null},
                             "questions":[{
                                "questionbankentryid":"1",
                                "filepath":"' . $this->rootpath . '/top/cat 1/First Question.xml",
                                "format":"xml"
                            }, {
                                "questionbankentryid":"2",
                                "filepath":"' . $this->rootpath . '/top/cat 2/subcat 2_1/Third Question.xml",
                                "format":"xml"
                            }, {
                                "questionbankentryid":"3",
                                "filepath":"' . $this->rootpath . '/top/cat 2/Second Question.xml",
                                "format":"xml"
                            }, {
                                "questionbankentryid":"4",
                                "filepath":"' . $this->rootpath . '/top/cat 2/subcat 2_1/Fourth Question.xml",
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

        $this->expectOutputRegex('/^\nThese questions are listed in the manifest but there is no longer a matching file/');
        $this->expectOutputRegex('/top\/cat 2\/subcat 2_1\/Third Question.xml+/');
        $this->expectOutputRegex('/top\/cat 2\/Second Question.xml+/');
    }

    /**
     * Test delete of questions with no file in repo.
     * @covers \gitsync\import_repo\delete_no_file_questions()
     */
    public function test_delete_no_record_questions(): void {
        // 2 records in the manifest.
        $manifestcontents = '{"context":{"contextlevel":70,"coursename":"Course 1","modulename":"Test 1","coursecategory":null},
                             "questions":[{
                                "questionbankentryid":"1",
                                "filepath":"' . $this->rootpath . '/top/cat 1/First Question.xml",
                                "format":"xml"
                            }, {
                                "questionbankentryid":"2",
                                "filepath":"' . $this->rootpath . '/top/cat 2/subcat 2_1/Third Question.xml",
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

        $this->expectOutputRegex('/^\nThese questions are in Moodle but not linked to your repository:/');
        $this->expectOutputRegex('/cat 1 - Second Question+/');
        $this->expectOutputRegex('/cat 1 - Fourth Question+/');
    }
}
