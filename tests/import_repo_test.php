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
 * Allows testing of errors that lead to an exit.
 */
class fake_helper extends cli_helper {
    /**
     * Override so ignored during testing
     *
     * @return void
     */
    public static function call_exit():void {
        return;
    }

    /**
     * Override so ignored during testing
     *
     * @return void
     */
    public static function handle_abort():void {
        return;
    }
}

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
            'subdirectory' => null,
            'contextlevel' => 'system',
            'coursename' => 'Course 1',
            'modulename' => 'Test 1',
            'coursecategory' => 'Cat 1',
            'qcategoryid' => null,
            'instanceid' => null,
            'manifestpath' => null,
            'token' => 'XXXXXX',
            'help' => false,
            'usegit' => false,
        ];
        $this->clihelper = $this->getMockBuilder(\qbank_gitsync\cli_helper::class)->onlyMethods([
            'get_arguments', 'check_context',
        ])->setConstructorArgs([$this->options])->getMock();
        $this->clihelper->expects($this->any())->method('get_arguments')->will($this->returnValue($this->options));
        $this->clihelper->expects($this->any())->method('check_context')->willReturn(
            json_decode('{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                             "modulename":"Module 1", "instanceid":"", "qcategoryname":"top", "qcategoryid":123},
              "questions": []}')
        );
        // Mock call to webservice.
        $this->curl = $this->getMockBuilder(\qbank_gitsync\curl_request::class)->onlyMethods([
            'execute',
        ])->setConstructorArgs(['xxxx'])->getMock();
        $this->uploadcurl = $this->getMockBuilder(\qbank_gitsync\curl_request::class)->onlyMethods([
            'execute',
        ])->setConstructorArgs(['xxxx'])->getMock();
        $this->deletecurl = $this->getMockBuilder(\qbank_gitsync\curl_request::class)->onlyMethods([
            'execute',
        ])->setConstructorArgs(['xxxx'])->getMock();
        $this->listcurl = $this->getMockBuilder(\qbank_gitsync\curl_request::class)->onlyMethods([
            'execute',
        ])->setConstructorArgs(['xxxx'])->getMock();
        $this->importrepo = $this->getMockBuilder(\qbank_gitsync\import_repo::class)->onlyMethods([
            'upload_file', 'handle_delete', 'call_exit', 'handle_abort',
        ])->setConstructorArgs([$this->clihelper, $this->moodleinstances])->getMock();
        $this->importrepo->curlrequest = $this->curl;
        $this->importrepo->deletecurlrequest = $this->deletecurl;
        $this->importrepo->listcurlrequest = $this->listcurl;
        $this->importrepo->uploadcurlrequest = $this->uploadcurl;
        $this->importrepo->expects($this->any())->method('upload_file')->will($this->returnValue(true));
    }

    /**
     * Redo mock set up
     *
     * Required if we want to change options so that they affect contructor output.
     *
     * @return void
     */
    public function replace_mock_default() {
        $this->clihelper = $this->getMockBuilder(\qbank_gitsync\cli_helper::class)->onlyMethods([
            'get_arguments', 'check_context',
        ])->setConstructorArgs([$this->options])->getMock();
        $this->clihelper->expects($this->any())->method('get_arguments')->will($this->returnValue($this->options));
        $this->clihelper->expects($this->any())->method('check_context')->willReturn(
            json_decode('{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                             "modulename":"Module 1", "instanceid":"", "qcategoryname":"top", "qcategoryid":123},
              "questions": []}')
        );
        $this->importrepo = $this->getMockBuilder(\qbank_gitsync\import_repo::class)->onlyMethods([
            'upload_file', 'handle_delete', 'call_exit', 'handle_abort',
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

        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturn(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                             "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
              "questions": []}',
        );

        $this->importrepo->process();

        // Check manifest file created.
        $this->assertEquals(file_exists($this->rootpath . '/' . self::MOODLE . '_system' . cli_helper::MANIFEST_FILE), true);
        $this->expectOutputRegex('/^\nAdded 0 questions.*Updated 4 questions.*\n$/s');
        // There's a manifest file but it's not being used so don't use manifest default.
        $this->assertEquals("top", $this->importrepo->subdirectory);
    }

    /**
     * Test the full process with manifest path.
     */
    public function test_process_manifest_path(): void {
        $this->options["manifestpath"] = 'fakeexport_system_question_manifest.json';
        $this->replace_mock_default();
        // The test repo has 2 categories and 1 subcategory. 1 question in each category and 2 in subcategory.
        // We expect 3 category calls to the webservice and 3 question calls as using cat 2 subdirectory
        // from manifest file default.
        $this->curl->expects($this->exactly(6))->method('execute')->willReturnOnConsecutiveCalls(
            '{"questionbankentryid": null}',
            '{"questionbankentryid": null}',
            '{"questionbankentryid": null}',
            '{"questionbankentryid": "35002", "version": "2"}',
            '{"questionbankentryid": "35004", "version": "2"}',
            '{"questionbankentryid": "35003", "version": "2"}',
        );

        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturn(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                             "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
              "questions": []}',
        );

        $this->importrepo->process();

        // Check manifest file created.
        $this->assertEquals(file_exists($this->rootpath . '/' . self::MOODLE . '_system' . cli_helper::MANIFEST_FILE), true);
        $this->expectOutputRegex('/^\nAdded 0 questions.*Updated 3 questions.*\n$/s');
        // Use manifest default.
        $this->assertEquals("top/cat-2", $this->importrepo->subdirectory);
    }

    /**
     * Test the full process with manifest path and subdirectory.
     */
    public function test_process_manifest_path_and_subdirectory(): void {
        $this->options["manifestpath"] = 'fakeexport_system_question_manifest.json';
        $this->options["subdirectory"] = 'top/cat-2/subcat-2_1';
        $this->replace_mock_default();
        // The test repo has 2 categories and 1 subcategory. 1 question in each category and 2 in subcategory.
        // We expect 3 category calls to the webservice and 2 question calls as using subcat 2_1
        // from subdirectory parameter.
        $this->curl->expects($this->exactly(5))->method('execute')->willReturnOnConsecutiveCalls(
            '{"questionbankentryid": null}',
            '{"questionbankentryid": null}',
            '{"questionbankentryid": null}',
            '{"questionbankentryid": "35004", "version": "2"}',
            '{"questionbankentryid": "35003", "version": "2"}',
        );

        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturn(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                             "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
              "questions": []}',
        );

        $this->importrepo->process();

        // Check manifest file created.
        $this->assertEquals(file_exists($this->rootpath . '/' . self::MOODLE . '_system' . cli_helper::MANIFEST_FILE), true);
        $this->expectOutputRegex('/^\nAdded 0 questions.*Updated 2 questions.*\n$/s');
        // Use subdirectory parameter.
        $this->assertEquals("top/cat-2/subcat-2_1", $this->importrepo->subdirectory);
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
        $this->assertContains($this->rootpath . '/top/cat-1/gitsync_category.xml', $this->results);
        $this->assertContains($this->rootpath . '/top/cat-2/gitsync_category.xml', $this->results);
        $this->assertContains($this->rootpath . '/top/cat-2/subcat-2_1/gitsync_category.xml', $this->results);
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
                                    $this->importrepo->postsettings['qcategoryname'],
                                   ];
            })
        );
        $this->importrepo->postsettings = [
            'contextlevel' => '10',
            'coursename' => 'Course 1',
            'modulename' => 'Test 1',
            'coursecategory' => 'Cat 1',
            'instanceid' => null,
        ];
        $this->importrepo->import_questions();
        $this->assertContains([$this->rootpath . '/top/cat-1/First-Question.xml', 'top/cat 1'], $this->results);
        $this->assertContains([$this->rootpath . '/top/cat-2/subcat-2_1/Third-Question.xml', 'top/cat 2/subcat 2_1'],
                              $this->results);
        $this->assertContains([$this->rootpath . '/top/cat-2/subcat-2_1/Fourth-Question.xml', 'top/cat 2/subcat 2_1'],
                              $this->results);
        $this->assertContains([$this->rootpath . '/top/cat-2/Second-Question.xml', 'top/cat 2'], $this->results);

        // Check temp manifest file created.
        $this->assertEquals(file_exists($this->importrepo->tempfilepath), true);
        $this->assertEquals(4, count(file($this->importrepo->tempfilepath)));
        $tempfile = fopen($this->importrepo->tempfilepath, 'r');
        $firstline = json_decode(fgets($tempfile));
        $this->assertStringContainsString('3500', $firstline->questionbankentryid);
        $this->assertEquals($firstline->contextlevel, '10');
        $this->assertStringContainsString($this->rootpath . '/top/cat-', $firstline->filepath);
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
        $this->options["subdirectory"] = 'top/cat-2/subcat-2_1';
        $this->replace_mock_default();
        $this->results = [];
        $this->curl->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            '{"questionbankentryid": "35001", "version": "2"}',
            '{"questionbankentryid": "35002", "version": "2"}',
        );
        $this->curl->expects($this->exactly(2))->method('execute')->will($this->returnCallback(
            function() {
                $this->results[] = [
                                    $this->importrepo->subdirectoryiterator->getPathname(),
                                    $this->importrepo->postsettings['qcategoryname'],
                                   ];
            })
        );
        $this->importrepo->subdirectory = 'top/cat-2/subcat-2_1';
        $this->importrepo->postsettings = [
            'contextlevel' => '10',
            'coursename' => 'Course 1',
            'modulename' => 'Test 1',
            'coursecategory' => 'Cat 1',
            'instanceid' => null,
        ];
        $this->importrepo->import_questions();
        $this->assertContains([$this->rootpath . '/top/cat-2/subcat-2_1/Third-Question.xml', 'top/cat 2/subcat 2_1'],
                               $this->results);
        $this->assertContains([$this->rootpath . '/top/cat-2/subcat-2_1/Fourth-Question.xml', 'top/cat 2/subcat 2_1'],
                               $this->results);

        // Check temp manifest file created.
        $this->assertEquals(file_exists($this->importrepo->tempfilepath), true);
        $this->assertEquals(2, count(file($this->importrepo->tempfilepath)));
        $tempfile = fopen($this->importrepo->tempfilepath, 'r');
        $firstline = json_decode(fgets($tempfile));
        $this->assertStringContainsString('3500', $firstline->questionbankentryid);
        $this->assertEquals($firstline->contextlevel, '10');
        $this->assertStringContainsString($this->rootpath . '/top/cat-', $firstline->filepath);
        $this->assertEquals($firstline->coursename, 'Course 1');
        $this->assertEquals($firstline->modulename, 'Test 1');
        $this->assertEquals($firstline->coursecategory, 'Cat 1');
        $this->assertEquals($firstline->format, 'xml');
        $this->assertEquals($this->importrepo->listpostsettings["qcategoryname"], 'top/cat 2/subcat 2_1');
    }

    /**
     * Test importing existing questions.
     * @covers \gitsync\import_repo\import_questions()
     */
    public function test_import_existing_questions(): void {
        $manifestcontents = '{"context":{"contextlevel":70,"coursename":"Course 1","modulename":"Test 1","coursecategory":null},
                             "questions":[{
                                "questionbankentryid":"1",
                                "importedversion":"1",
                                "exportedversion":"1",
                                "filepath":"/top/cat-1/First-Question.xml",
                                "format":"xml"
                            }, {
                                "questionbankentryid":"2",
                                "importedversion":"1",
                                "exportedversion":"1",
                                "filepath":"/top/cat-2/subcat-2_1/Third-Question.xml",
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
                                    $this->importrepo->postsettings['questionbankentryid'],
                                   ];
            })
        );
        $this->importrepo->postsettings = [
            'contextlevel' => '10',
            'coursename' => 'Course 1',
            'modulename' => 'Test 1',
            'coursecategory' => 'Cat 1',
            'instanceid' => null,
        ];
        $this->importrepo->import_questions();
        // Check questions in manifest pass questionbankentryid to webservice but the others don't.
        $this->assertContains([$this->rootpath . '/top/cat-1/First-Question.xml', 'top/cat 1', '1'], $this->results);
        $this->assertContains([$this->rootpath . '/top/cat-2/subcat-2_1/Third-Question.xml', 'top/cat 2/subcat 2_1', '2'],
                              $this->results);
        $this->assertContains([$this->rootpath . '/top/cat-2/subcat-2_1/Fourth-Question.xml', 'top/cat 2/subcat 2_1', null],
                              $this->results);
        $this->assertContains([$this->rootpath . '/top/cat-2/Second-Question.xml', 'top/cat 2', null], $this->results);
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
                                 "filepath":"/top/cat-1/First-Question.xml",
                                 "importedversion":"1",
                                 "exportedversion":"1",
                                 "format":"xml"
                             }, {
                                "questionbankentryid":"2",
                                "filepath":"/top/cat-2/subcat-2_1/Third-Question.xml",
                                "currentcommit":"notmatched",
                                "importedversion":"1",
                                "exportedversion":"1",
                                "format":"xml"
                            }, {
                                "questionbankentryid":"3",
                                "filepath":"/top/cat-2/subcat-2_1/Fourth-Question.xml",
                                "currentcommit":"notmatched",
                                "moodlecommit":"notmatched!",
                                "importedversion":"1",
                                "exportedversion":"1",
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
                                    $this->importrepo->postsettings['questionbankentryid'],
                                ];
            })
        );
        $this->importrepo->postsettings = [
            'contextlevel' => '10',
            'coursename' => 'Course 1',
            'modulename' => 'Test 1',
            'coursecategory' => 'Cat 1',
            'instanceid' => null,
        ];
        $this->importrepo->import_questions();
        // Check question with matching hashes wasn't imported.
        $this->assertNotContains([$this->rootpath . '/top/cat-1/First-Question.xml', 'top/cat 1', '1'], $this->results);
        $this->assertContains([$this->rootpath . '/top/cat-2/subcat-2_1/Third-Question.xml', 'top/cat 2/subcat 2_1', '2'],
                              $this->results);
        $this->assertContains([$this->rootpath . '/top/cat-2/subcat-2_1/Fourth-Question.xml', 'top/cat 2/subcat 2_1', '3'],
                              $this->results);
        $this->assertContains([$this->rootpath . '/top/cat-2/Second-Question.xml', 'top/cat 2', null], $this->results);
    }


    /**
     * Test message displayed when an invalid directory is used.
     * @covers \gitsync\import_repo\import_questions()
     */
    public function test_import_questions_wrong_directory(): void {
        $this->importrepo->directory = $this->rootpath;
        $this->importrepo->subdirectory = 'top/cat-1';
        $this->curl->expects($this->any())->method('execute')->will(
            $this->returnValue('{"questionbankentryid": "35001", "version": "2"}'));
        unlink($this->rootpath . '/top/cat-1' . '/' . cli_helper::CATEGORY_FILE . '.xml');

        $this->importrepo->import_questions();
        $this->expectOutputRegex('/Problem with the category file or file location./');
    }

    /**
     * Test creation of manifest file.
     * @covers \gitsync\cli_helper\create_manifest_file()
     *
     * (Run the entire process and check the output to avoid lots of additonal setup of tempfile etc.)
     */
    public function test_manifest_file(): void {
        unlink($this->importrepo->manifestpath);
        $this->importrepo = $this->getMockBuilder(\qbank_gitsync\import_repo::class)->onlyMethods([
            'upload_file', 'handle_delete', 'call_exit', 'handle_abort',
        ])->setConstructorArgs([$this->clihelper, $this->moodleinstances])->getMock();
        $this->importrepo->curlrequest = $this->curl;
        $this->importrepo->deletecurlrequest = $this->deletecurl;
        $this->importrepo->listcurlrequest = $this->listcurl;
        $this->importrepo->uploadcurlrequest = $this->uploadcurl;
        $this->importrepo->expects($this->any())->method('upload_file')->will($this->returnValue(true));
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

        $this->importrepo->listcurlrequest->expects($this->exactly(1))->method('execute')->willReturn(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                "modulename":"Module 1", "instanceid":"", "qcategoryname":"top", "qcategoryid":1},
              "questions": []}'
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
        $this->assertEquals($context->coursename, 'Course 1');
        $this->assertEquals($context->modulename, 'Module 1');
        $this->assertEquals($context->coursecategory, '');
        $this->assertEquals($context->defaultsubcategoryid, 123);
        $this->assertEquals($context->defaultsubdirectory, 'top');

        $this->expectOutputRegex('/^\nManifest file is empty.*\nAdded 4 questions.*Updated 0 questions.*\n$/s');
    }

    /**
     * Test creation of manifest file with subdirectory.
     * @covers \gitsync\cli_helper\create_manifest_file()
     *
     * (Run the entire process and check the output to avoid lots of additonal setup of tempfile etc.)
     */
    public function test_manifest_file_with_subdirectory(): void {
        unlink($this->importrepo->manifestpath);
        $this->options["subdirectory"] = 'top/cat-2/subcat-2_1';
        $this->replace_mock_default();
        // The test repo has 2 categories and 1 subcategory. 1 question in each category and 2 in subcategory.
        // We expect 3 category calls to the webservice and 2 question calls (as subdirectory parameter used).
        $this->importrepo->curlrequest->expects($this->exactly(5))->method('execute')->willReturnOnConsecutiveCalls(
            '{"questionbankentryid": null}',
            '{"questionbankentryid": null}',
            '{"questionbankentryid": null}',
            '{"questionbankentryid": "35004", "version": "2"}',
            '{"questionbankentryid": "35003", "version": "2"}',
        );

        $this->importrepo->listcurlrequest->expects($this->exactly(1))->method('execute')->willReturn(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                "modulename":"Module 1", "instanceid":"", "qcategoryname":"top/ds", "qcategoryid":1},
              "questions": []}'
        );
        $this->importrepo->process();

        // Manifest file is a single array.
        $this->assertEquals(1, count(file($this->importrepo->manifestpath)));
        $manifestcontents = json_decode(file_get_contents($this->importrepo->manifestpath));
        $this->assertCount(2, $manifestcontents->questions);

        $manifestentries = array_column($manifestcontents->questions, null, 'questionbankentryid');
        $this->assertArrayHasKey('35004', $manifestentries);
        $this->assertArrayHasKey('35003', $manifestentries);

        $context = $manifestcontents->context;
        $this->assertEquals($context->contextlevel, '10');
        $this->assertEquals($context->coursename, 'Course 1');
        $this->assertEquals($context->modulename, 'Module 1');
        $this->assertEquals($context->coursecategory, '');
        $this->assertEquals($context->defaultsubcategoryid, 123);
        $this->assertEquals($context->defaultsubdirectory, 'top/cat-2/subcat-2_1');

        $this->expectOutputRegex('/^\nManifest file is empty.*\nAdded 2 questions.*Updated 0 questions.*\n$/s');
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
                                "filepath":"/top/cat-1/First-Question.xml",
                                "version": "1",
                                "exportedversion": "1",
                                "format":"xml"
                             }, {
                                "questionbankentryid":"2",
                                "filepath":"/top/cat-2/subcat-2_1/Third-Question.xml",
                                "version": "1",
                                "exportedversion": "1",
                                "currentcommit": "test",
                                "format":"xml"
                             }]}';
        $tempcontents = '{"questionbankentryid":"1",' .
                          '"filepath":"/top/cat-1/First-Question.xml",' .
                          '"version": "5",' .
                          '"format":"xml"}' . "\n" .
                        '{"questionbankentryid":"3",' .
                          '"filepath":"/top/cat-2/Second-Question.xml",' .
                          '"version": "6",' .
                          '"format":"xml"}' . "\n" .
                        '{"questionbankentryid":"2",' .
                          '"filepath":"/top/cat-2/subcat-2_1/Third-Question.xml",' .
                          '"version": "7",' .
                          '"format":"xml"}' . "\n" .
                        '{"questionbankentryid":"4",' .
                          '"filepath":"/top/cat-2/subcat-2_1/Fourth-Question.xml",' .
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

        $this->assertEquals('5', $manifestentries['1']->importedversion);
        $this->assertEquals('1', $manifestentries['1']->exportedversion);
        $context = $manifestcontents->context;
        $this->assertEquals($context->contextlevel, '70');
        $this->assertEquals($context->coursename, 'Course 1');
        $this->assertEquals($context->modulename, 'Test 1');
        $this->assertEquals($context->coursecategory, null);

        $samplerecord = $manifestentries['1'];
        $this->assertEquals('/top/cat-1/First-Question.xml', $samplerecord->filepath);

        $samplerecord = $manifestentries['4'];
        $this->assertEquals('test', $samplerecord->moodlecommit);

        $this->expectOutputRegex('/^\nAdded 2 questions.*Updated 2 questions.*\n$/s');
    }

    /**
     * Test update of manifest file temp file error.
     * @covers \gitsync\cli_helper\create_manifest_file()
     */
    public function test_manifest_temp_file_error(): void {
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
                             }]}';
        $tempcontents = '{"questionbankentryid":"1",' .
                          '"filepath":"/top/cat 1/First Question.xml",' .
                          '"version": "5",' .
                          '"format":"xml"}' . "\n";
        $this->importrepo->manifestcontents = json_decode($manifestcontents);
        file_put_contents($this->importrepo->tempfilepath, $tempcontents);
        chmod($this->importrepo->tempfilepath, 0000);
        @fake_helper::create_manifest_file($this->importrepo->manifestcontents,
                                        $this->importrepo->tempfilepath, $this->importrepo->manifestpath,
                                        'www.moodle');
        $this->expectOutputRegex('/^\nUnable to access temp file.*Aborting.\n$/s');
    }

    /**
     * Test update of manifest file manifest file error.
     * @covers \gitsync\cli_helper\create_manifest_file()
     */
    public function test_manifest_manifest_file_error(): void {
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
                             }]}';
        $tempcontents = '{"questionbankentryid":"1",' .
                          '"filepath":"/top/cat 1/First Question.xml",' .
                          '"version": "5",' .
                          '"format":"xml"}' . "\n";
        $this->importrepo->manifestcontents = json_decode($manifestcontents);
        file_put_contents($this->importrepo->tempfilepath, $tempcontents);
        chmod($this->importrepo->manifestpath, 0000);
        @fake_helper::create_manifest_file($this->importrepo->manifestcontents,
                                        $this->importrepo->tempfilepath, $this->importrepo->manifestpath,
                                        'www.moodle');
        $this->expectOutputRegex('/\nUnable to update manifest file.*Aborting.\n$/s');
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
                                "filepath":"/top/cat-1/First-Question.xml",
                                "format":"xml"
                             }, {
                                "questionbankentryid":"2",
                                "filepath":"/top/cat-2/subcat-2_1/Third-Question.xml",
                                "format":"xml"
                             }, {
                                "questionbankentryid":"3",
                                "filepath":"/top/cat-2/Second-Question.xml",
                                "format":"xml"
                             }, {
                                "questionbankentryid":"4",
                                "filepath":"/top/cat-2/subcat-2_1/Fourth-Question.xml",
                                "format":"xml"
                             }]}';
        $this->importrepo->manifestcontents = json_decode($manifestcontents);
        file_put_contents($this->importrepo->manifestpath, $manifestcontents);

        // Delete 2 of the files.
        unlink($this->rootpath . '/top/cat-2/subcat-2_1/Third-Question.xml');
        unlink($this->rootpath . '/top/cat-2/Second-Question.xml');

        // One question deleted of two that no longer have files.
        $this->importrepo->expects($this->exactly(2))->method('handle_delete')->willReturnOnConsecutiveCalls(
            true, false
        );

        $this->importrepo->delete_no_file_questions(true);

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
                                 '.*top\/cat-2\/subcat-2_1\/Third-Question.xml' .
                                 '.*top\/cat-2\/Second-Question.xml/s');
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
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
               "questions": [{"questionbankentryid": "1", "name": "First Question", "questioncategory": "cat 1"},
              {"questionbankentryid": "2", "name": "Third Question", "questioncategory": "subcat 2_1"},
              {"questionbankentryid": "3", "name": "Second Question", "questioncategory": "cat 1"},
              {"questionbankentryid": "4", "name": "Fourth Question", "questioncategory": "cat 1"}]}'
        );
        $this->importrepo->delete_no_record_questions(true);

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
        $this->importrepo->delete_no_record_questions(true);
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
        $this->importrepo->delete_no_record_questions(true);
        $this->expectOutputRegex('/No token/');
    }

    /**
     * Check abort if question version in Moodle doesn't match a version in manifest.
     * @covers \gitsync\import_repo\check_question_versions()
     */
    public function test_check_question_versions():void {
        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
               "questions": [{"questionbankentryid": "35001", "name": "One", "questioncategory": "", "version": "1"},
              {"questionbankentryid": "35002", "name": "Two", "questioncategory": "TestC", "version": "2"},
              {"questionbankentryid": "35003", "name": "Three", "questioncategory": "", "version": "1"},
              {"questionbankentryid": "35004", "name": "Four", "questioncategory": "", "version": "1"}]}'
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
     * @covers \gitsync\import_repo\check_question_versions()
     */
    public function test_check_question_export_version_success():void {
        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
              "questions": [{"questionbankentryid": "35001", "name": "One", "questioncategory": "", "version": "1"},
              {"questionbankentryid": "35002", "name": "Two", "questioncategory": "TestC", "version": "7"},
              {"questionbankentryid": "35003", "name": "Three", "questioncategory": "", "version": "1"},
              {"questionbankentryid": "35004", "name": "Four", "questioncategory": "", "version": "1"}]}'
            );
        $this->importrepo->check_question_versions();
    }

    /**
     * Test version check passes if imported version matches.
     * @covers \gitsync\import_repo\check_question_versions()
     */
    public function test_check_question_import_version_success():void {
        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturnOnConsecutiveCalls(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
              "questions": [{"questionbankentryid": "35001", "name": "One", "questioncategory": "", "version": "1"},
              {"questionbankentryid": "35002", "name": "Two", "questioncategory": "TestC", "version": "6"},
              {"questionbankentryid": "35003", "name": "Three", "questioncategory": "", "version": "1"},
              {"questionbankentryid": "35004", "name": "Four", "questioncategory": "", "version": "1"}]}'
            );
        $this->importrepo->check_question_versions();
    }

    /**
     * Check abort if question version in Moodle doesn't match a version in manifest.
     * @covers \gitsync\import_repo\check_question_versions()
     */
    public function test_check_question_versions_moved_question():void {
        $this->listcurl->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
               "questions": [{"questionbankentryid": "35001", "name": "One", "questioncategory": "", "version": "1"},
              {"questionbankentryid": "35003", "name": "Three", "questioncategory": "", "version": "1"},
              {"questionbankentryid": "35004", "name": "Four", "questioncategory": "", "version": "1"}]}',
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
            "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
            "questions": [{"questionbankentryid": "35002", "name": "Two", "questioncategory": "TestC", "version": "2"}]}'
            );
        $this->importrepo->check_question_versions();

        $this->expectOutputRegex('/35002 - TestC - Two' .
                                 '.*Moodle question version: 2' .
                                 '.*Version on last import to Moodle: 6' .
                                 '.*Version on last export from Moodle: 7' .
                                 '.*Export questions from Moodle before proceeding/s');
    }

    /**
     * Test version check passes if imported version matches.
     * @covers \gitsync\import_repo\check_question_versions()
     */
    public function test_check_question_import_version_success_moved_question():void {
        $this->listcurl->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
              "questions": [{"questionbankentryid": "35001", "name": "One", "questioncategory": "", "version": "1"},
              {"questionbankentryid": "35003", "name": "Three", "questioncategory": "", "version": "1"},
              {"questionbankentryid": "35004", "name": "Four", "questioncategory": "", "version": "1"}]}',
              '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
              "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
              "questions": [{"questionbankentryid": "35002", "name": "Two", "questioncategory": "TestC", "version": "6"}]}'
            );
        $this->importrepo->check_question_versions();
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

    /**
     * Test version check broken JSON.
     * @covers \gitsync\import_repo\check_question_versions()
     */
    public function test_check_versions_broken_json_request_2(): void {
        $this->listcurl->expects($this->any())->method('execute')->willReturnOnConsecutiveCalls(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
              "questions": [{"questionbankentryid": "35001", "name": "One", "questioncategory": "", "version": "1"}]}',
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
    public function test_check_version_exception_request_2(): void {
        $this->listcurl->expects($this->any())->method('execute')->willReturn(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                "modulename":"Module 1", "instanceid":"", "qcategoryname":"top"},
              "questions": [{"questionbankentryid": "35001", "name": "One", "questioncategory": "", "version": "1"}]}',
            '{"exception":"moodle_exception","message":"No token"}'
        );
        $this->importrepo->check_question_versions();
        $this->expectOutputRegex('/No token/');
    }

    /**
     * Test checking context
     * @covers \gitsync\cli_helper\check_context()
     */
    public function test_check_content(): void {
        $clihelper = new fake_helper([]);
        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturn(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                             "modulename":"Module 1", "instanceid":"", "qcategoryname":"top", "qcategoryid":1},
              "questions": []}',
        );
        $clihelper->check_context($this->importrepo);
        $this->expectOutputRegex('/^\nPreparing to.*import_repo.*Question subdirectory: top\n$/s');
    }

    /**
     * Test checking context exception
     * @covers \gitsync\cli_helper\check_context()
     */
    public function test_check_content_exception(): void {
        $clihelper = new fake_helper([]);
        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturn(
            '{"exception":"moodle_exception","message":"No token"}'
        );
        $clihelper->check_context($this->importrepo);
        $this->expectOutputRegex('/No token/');
    }

    /**
     * Test checking context broken JSON
     * @covers \gitsync\cli_helper\check_context()
     */
    public function test_check_content_broekn_json(): void {
        $clihelper = new fake_helper([]);
        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturn(
            '{broken'
        );
        $clihelper->check_context($this->importrepo);
        $this->expectOutputRegex('/Broken JSON returned from Moodle:' .
                                 '.*{broken/s');
    }

    /**
     * Test checking context silent
     * @covers \gitsync\cli_helper\check_context()
     */
    public function test_check_content_silent(): void {
        $clihelper = new fake_helper([]);
        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturn(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                             "modulename":"Module 1", "instanceid":"", "qcategoryname":"top", "qcategoryid":1},
              "questions": []}',
        );
        $clihelper->check_context($this->importrepo, true, true);
        $this->expectOutputString('');
    }

    /**
     * Test checking context default warning
     * @covers \gitsync\cli_helper\check_context()
     */
    public function test_check_content_default_warning(): void {
        $clihelper = new fake_helper([]);
        $this->listcurl->expects($this->exactly(1))->method('execute')->willReturn(
            '{"contextinfo":{"contextlevel": "module", "categoryname":"", "coursename":"Course 1",
                             "modulename":"Module 1", "instanceid":"", "qcategoryname":"top", "qcategoryid":1},
              "questions": []}',
        );
        $clihelper->check_context($this->importrepo, true, false);
        $this->expectOutputRegex('/Using default subdirectory from manifest file./');
    }

}
