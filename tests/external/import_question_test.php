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
 * Unit tests for import_question function of gitsync webservice
 *
 * @package    qbank_gitsync
 * @copyright  2023 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_gitsync\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/lib/externallib.php');
require_once($CFG->dirroot . '/files/externallib.php');
require_once($CFG->dirroot. '/question/bank/gitsync/lib.php');

use context_course;
use externallib_advanced_testcase;
use external_api;
use required_capability_exception;
use require_login_exception;
use core_files_external;

/**
 * Test the export_question webservice function.
 * @runTestsInSeparateProcesses
 * @group qbank_gitsync
 *
 * @covers \gitsync\external\import_question::execute
 */
class import_question_test extends externallib_advanced_testcase {
    /** @var core_question_generator plugin generator */
    protected \core_question_generator $generator;
    /** @var generated course object */
    protected \stdClass $course;
    /** @var \stdClass generated question_category object */
    protected \stdClass $qcategory;
    /** @var array information about uploaded file */
    protected array $fileinfo;
    /** @var generated user object */
    protected \stdClass $user;
    /** @var string filepath of directory containing test files */
    protected string $testrepo;
    /** Name of question to be generated and updated. */
    const QNAME = 'Example STACK question';

    public function setUp(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->course = $this->getDataGenerator()->create_course();
        $this->qcategory = $this->generator->create_question_category(
                                ['contextid' => \context_course::instance($this->course->id)->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->user = $user;
        $this->setUser($user);
        $this->testrepo = $CFG->dirroot . '/question/bank/gitsync/testrepo/';
        $this->fileinfo = ['contextid' => '', 'component' => '', 'filearea' => '', 'userid' => '',
                           'itemid' => '', 'filepath' => '', 'filename' => '',
                        ];
    }

    /**
     * Upload a question file ready for import.
     *
     * @param string $contentpath Path to test file containing question
     * @return void
     */
    public function upload_file(string $contentpath):void {
        global $USER;
        $content = file_get_contents($contentpath);
        $context = \context_user::instance($USER->id);
        $contextid = $context->id;
        $component = "user";
        $filearea = "draft";
        $itemid = 0;
        $filepath = "/";
        $filename = "Simple.txt";
        $filecontent = base64_encode($content);
        $contextlevel = null;
        $instanceid = null;

        // Make sure no file exists.
        $fileinfo = core_files_external::upload($contextid, $component, $filearea, $itemid, $filepath,
                $filename, $filecontent, $contextlevel, $instanceid);

        $fileinfo = \external_api::clean_returnvalue(core_files_external::upload_returns(), $fileinfo);

        $this->fileinfo = $fileinfo;
        unset($this->fileinfo['url']);
        $this->fileinfo['userid'] = '';
    }

    /**
     * Give required capabilities to user.
     *
     * @return context_course
     */
    public function give_capabilities():context_course {
        global $DB;
        // Set the required capabilities - webservice access and export rights on course.
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);
        return $context;
    }

    /**
     * Test the execute function when capabilities are present.
     */
    public function test_capabilities(): void {
        $this->give_capabilities();
        $this->upload_file($this->testrepo . 'top/cat-1/gitsync_category.xml');
        $returnvalue = import_question::execute('', '', '',
                                                null,
                                                $this->fileinfo,
                                                50,
                                                $this->course->fullname);

        // We need to execute the return values cleaning process to simulate
        // the web service server.
        $returnvalue = external_api::clean_returnvalue(
            import_question::execute_returns(),
            $returnvalue
        );

        // Assert that there was a response.
        // The actual response is tested in other tests.
        $this->assertNotNull($returnvalue);
    }

    /**
     * Test the execute function fails when not logged in.
     */
    public function test_not_logged_in(): void {
        global $DB;
        $this->setUser();
        $this->expectException(require_login_exception::class);
        // Exception messages don't seem to get translated.
        $this->expectExceptionMessage('not logged in');
        import_question::execute('', '', '', null, $this->fileinfo, 50, $this->course->fullname);
    }

    /**
     * Test the execute function fails when no webservice import capability assigned.
     */
    public function test_no_webservice_access(): void {
        global $DB;
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);
        $this->unassignUserCapability('qbank/gitsync:importquestions', \context_system::instance()->id, $managerroleid);
        $this->expectException(required_capability_exception::class);
        $this->expectExceptionMessage('you do not currently have permissions to do that (Import)');
        import_question::execute('', '', '', null, $this->fileinfo, 50, $this->course->fullname);
    }

    /**
     * Test the execute function fails when user has no access to supplied context.
     */
    public function test_import_capability(): void {
        $this->expectException(require_login_exception::class);
        $this->expectExceptionMessage('Not enrolled');
        import_question::execute('', '', '', null, $this->fileinfo, 50, $this->course->fullname);
    }

    /**
     * Test import of category.
     * The category is created; no question id is returned; the correct logging occurs.
     */
    public function test_category_import(): void {
        global $DB;
        $context = $this->give_capabilities();
        $this->upload_file($this->testrepo . 'top/cat-1/gitsync_category.xml');
        $createdcategory = $DB->get_record('question_categories', ['name' => 'cat 1'], '*');
        $this->assertEquals($createdcategory, false);
        $sink = $this->redirectEvents();

        // Create a category.
        $returnvalue = import_question::execute('', '', '',
                                                null,
                                                $this->fileinfo,
                                                50,
                                                $this->course->fullname);
        $createdcategory = $DB->get_record('question_categories', ['name' => 'cat 1'], '*', $strictness = MUST_EXIST);
        $this->assertEquals($createdcategory->contextid, $context->id);
        $parentcategory = $DB->get_record('question_categories',
                                          ['name' => 'top', 'contextid' => $context->id],
                                          '*',
                                          $strictness = MUST_EXIST);
        $this->assertEquals($createdcategory->parent, $parentcategory->id);
        $this->assertEquals($createdcategory->info, 'First imported folder');

        $returnvalue = external_api::clean_returnvalue(
            import_question::execute_returns(),
            $returnvalue
        );

        $this->assertEquals($returnvalue['questionbankentryid'], null);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 1);
        $event = reset($events);
        $this->assertInstanceOf('\core\event\question_category_created', $event);
    }

    /**
     * Test import of subcategory.
     */
    public function test_subcategory_import(): void {
        global $DB;
        $context = $this->give_capabilities();
        $this->upload_file($this->testrepo . 'top/cat-2/subcat-2_1/gitsync_category.xml');
        $createdcategory = $DB->get_record('question_categories', ['name' => 'cat 2'], '*');
        $this->assertEquals($createdcategory, false);
        $createdsubcategory = $DB->get_record('question_categories', ['name' => 'subcat 2_1'], '*');
        $this->assertEquals($createdsubcategory, false);
        $sink = $this->redirectEvents();

        // Create a category and subcategory.
        $returnvalue = import_question::execute('', '', '',
                                                null,
                                                $this->fileinfo,
                                                50,
                                                $this->course->fullname);
        $createdcategory = $DB->get_record('question_categories', ['name' => 'cat 2'], '*', $strictness = MUST_EXIST);
        $this->assertEquals($createdcategory->contextid, $context->id);
        $parentcategory = $DB->get_record('question_categories',
                                          ['name' => 'top', 'contextid' => $context->id],
                                          '*',
                                          $strictness = MUST_EXIST);
        $this->assertEquals($createdcategory->parent, $parentcategory->id);
        // Category will have been created without info. When doing this for real, categories should be created in descending order.
        $this->assertEquals($createdcategory->info, '');

        $createdsubcategory = $DB->get_record('question_categories', ['name' => 'subcat 2_1'], '*', $strictness = MUST_EXIST);
        $this->assertEquals($createdsubcategory->contextid, $context->id);
        $this->assertEquals($createdsubcategory->parent, $createdcategory->id);
        $this->assertEquals($createdsubcategory->info, 'Imported subfolder');

        $returnvalue = external_api::clean_returnvalue(
            import_question::execute_returns(),
            $returnvalue
        );

        $this->assertEquals($returnvalue['questionbankentryid'], null);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 2);
        $this->assertInstanceOf('\core\event\question_category_created', $events['0']);
        $this->assertInstanceOf('\core\event\question_category_created', $events['1']);
    }

    /**
     * Test question import.
     */
    public function test_question_import(): void {
        global $DB;
        $this->give_capabilities();
        $this->upload_file($this->testrepo . 'top/cat-2/subcat-2_1/gitsync_category.xml');
        $createdquestion = $DB->get_record('question', ['name' => 'Third Question'], '*');
        $this->assertEquals($createdquestion, false);

        import_question::execute('', '', '',
                                 null,
                                 $this->fileinfo,
                                 50,
                                 $this->course->fullname);
        $createdsubcategory = $DB->get_record('question_categories', ['name' => 'subcat 2_1'], '*', $strictness = MUST_EXIST);

        $sink = $this->redirectEvents();
        $this->upload_file($this->testrepo . 'top/cat-2/subcat-2_1/Third-Question.xml');
        $returnvalue = import_question::execute('', '', '',
                                                'top/cat 2/subcat 2_1',
                                                $this->fileinfo,
                                                50,
                                                $this->course->fullname);
        $createdquestion = $DB->get_record('question', ['name' => 'Third Question'], '*', $strictness = MUST_EXIST);
        $qversion = $DB->get_record('question_versions',
                                    ['questionid' => $createdquestion->id], '*', $strictness = MUST_EXIST);
        $qbankentry = $DB->get_record('question_bank_entries',
                                      ['id' => $qversion->questionbankentryid],
                                      '*',
                                      $strictness = MUST_EXIST);
        $qcategory = $DB->get_record('question_categories',
                                     ['id' => $qbankentry->questioncategoryid],
                                     '*',
                                     $strictness = MUST_EXIST);
        $this->assertEquals($qcategory->id, $createdsubcategory->id);

        $returnvalue = external_api::clean_returnvalue(
            import_question::execute_returns(),
            $returnvalue
        );

        $this->assertEquals($returnvalue['questionbankentryid'], $qbankentry->id);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 2);
        $this->assertInstanceOf('\core\event\question_created', $events['0']);
        $this->assertInstanceOf('\core\event\questions_imported', $events['1']);
    }

    /**
     * Test question update.
     */
    public function test_question_update(): void {
        global $DB;
        $this->give_capabilities();
        // Generate question and obtain its QBE id.
        $question = $this->generator->create_question('shortanswer', null,
                            ['name' => self::QNAME, 'category' => $this->qcategory->id]);
        $qbankentryid = $DB->get_field('question_versions', 'questionbankentryid',
                            ['questionid' => $question->id], $strictness = MUST_EXIST);
        $this->assertEquals(false, $DB->record_exists('question_versions',
                            ['questionbankentryid' => $qbankentryid, 'version' => 2]));
        $sink = $this->redirectEvents();
        // Update question.
        $this->upload_file($this->testrepo . 'top/cat-2/subcat-2_1/Third-Question.xml');
        $returnvalue = import_question::execute($qbankentryid, '1', '1',
                                 null,
                                 $this->fileinfo,
                                 50,
                                 $this->course->fullname);

        // Check version number has increased.
        $this->assertEquals(true, $DB->record_exists('question_versions',
                            ['questionbankentryid' => $qbankentryid, 'version' => 2]));

        $returnvalue = external_api::clean_returnvalue(
            import_question::execute_returns(),
            $returnvalue
        );

        $this->assertEquals($returnvalue['questionbankentryid'], $qbankentryid);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 1);
        $this->assertInstanceOf('\qbank_importasversion\event\question_version_imported', $events['0']);
    }

    /**
     * Test version check.
     */
    public function test_version_check(): void {
        global $DB;
        $this->give_capabilities();
        // Generate question and obtain its QBE id.
        $question = $this->generator->create_question('shortanswer', null,
                            ['name' => self::QNAME, 'category' => $this->qcategory->id]);
        $qbankentryid = $DB->get_field('question_versions', 'questionbankentryid',
                            ['questionid' => $question->id], $strictness = MUST_EXIST);
        $this->assertEquals(false, $DB->record_exists('question_versions',
                            ['questionbankentryid' => $qbankentryid, 'version' => 2]));
        $sink = $this->redirectEvents();
        // Update question.
        $this->upload_file($this->testrepo . 'top/cat-2/subcat-2_1/Third-Question.xml');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('Could not import question : Example STACK question ' .
                                      'Current version in Moodle is 1. Last imported version is 3. ' .
                                      'Last exported version is 4. You need to export the question.');
        import_question::execute($qbankentryid, '3', '4',
                                 null,
                                 $this->fileinfo,
                                 50,
                                 $this->course->fullname);

        // Check version number has not increased.
        $this->assertEquals(true, $DB->record_exists('question_versions',
                            ['questionbankentryid' => $qbankentryid, 'version' => 1]));
    }
}
