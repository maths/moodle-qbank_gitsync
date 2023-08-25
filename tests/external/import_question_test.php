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

use question_category_created;
use context_course;
use externallib_advanced_testcase;
use external_api;
use require_login_exception;

/**
 * Test the export_question webservice function.
 *
 * @covers \gitsync\external\import_question::execute
 */
class import_question_test extends externallib_advanced_testcase {
    /** @var plugin generator */
    protected $generator;
    /** @var generated course object */
    protected $course;
    /** @var generated question_category object */
    protected $qcategory;
    /** @var generated user object */
    protected $user;
    /** @var filepath of directory containing test files */
    protected $testrepo;

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
        $this->assignUserCapability('qbank/gitsync:importquestions', $context->id);
        $managerroleid = $DB->get_field('role', 'id', array('shortname' => 'manager'));
        role_assign($managerroleid, $this->user->id, $context->id);
        return $context;
    }

    /**
     * Test the execute function when capabilities are present.
     */
    public function test_capabilities(): void {
        $this->give_capabilities();
        $returnvalue = import_question::execute('',
                                                null,
                                                $this->testrepo . 'top/cat 1/gitsync_category.xml',
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
        import_question::execute('', null, $this->testrepo . 'top/cat 1/gitsync_category.xml', 50, $this->course->fullname);
    }

    /**
     * Test the execute function fails when no webservice capability assigned.
     */
    public function test_no_webservice_access(): void {
        global $DB;
        $this->expectException(require_login_exception::class);
        $this->expectExceptionMessage('Not enrolled');
        import_question::execute('', null, $this->testrepo . 'top/cat 1/gitsync_category.xml', 50, $this->course->fullname);
    }

    /**
     * Test the execute function fails when user has no access to supplied context.
     */
    public function test_export_capability(): void {
        $context = context_course::instance($this->course->id);
        $this->assignUserCapability('qbank/gitsync:importquestions', $context->id);
        $this->expectException(require_login_exception::class);
        $this->expectExceptionMessage('Not enrolled');
        import_question::execute('', null, $this->testrepo . 'top/cat 1/gitsync_category.xml', 50, $this->course->fullname);
    }

    /**
     * Test import of category.
     * The category is created; no question id is returned; the correct logging occurs.
     */
    public function test_category_import(): void {
        global $DB;
        $context = $this->give_capabilities();
        $createdcategory = $DB->get_record('question_categories', ['name' => 'cat 1'], '*');
        $this->assertEquals($createdcategory, false);
        $sink = $this->redirectEvents();

        // Create a category.
        $returnvalue = import_question::execute('',
                                                null,
                                                $this->testrepo . 'top/cat 1/gitsync_category.xml',
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

        $this->assertEquals($returnvalue['questionid'], null);
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
        $createdcategory = $DB->get_record('question_categories', ['name' => 'cat 2'], '*');
        $this->assertEquals($createdcategory, false);
        $createdsubcategory = $DB->get_record('question_categories', ['name' => 'subcat 2_1'], '*');
        $this->assertEquals($createdsubcategory, false);
        $sink = $this->redirectEvents();

        // Create a category and subcategory.
        $returnvalue = import_question::execute('',
                                                null,
                                                $this->testrepo . 'top/cat 2/subcat 2_1/gitsync_category.xml',
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

        $this->assertEquals($returnvalue['questionid'], null);
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
        $context = $this->give_capabilities();
        $createdquestion = $DB->get_record('question', ['name' => 'Third Question'], '*');
        $this->assertEquals($createdquestion, false);

        import_question::execute('',
                                 null,
                                 $this->testrepo . 'top/cat 2/subcat 2_1/gitsync_category.xml',
                                 50,
                                 $this->course->fullname);
        $createdsubcategory = $DB->get_record('question_categories', ['name' => 'subcat 2_1'], '*', $strictness = MUST_EXIST);

        $sink = $this->redirectEvents();

        $returnvalue = import_question::execute('',
                                                'top/cat 2/subcat 2_1',
                                                $this->testrepo . 'top/cat 2/subcat 2_1/Third Question.xml',
                                                50,
                                                $this->course->fullname);
        $createdquestion = $DB->get_record('question', ['name' => 'Third Question'], '*', $strictness = MUST_EXIST);
        $qversion = $DB->get_record('question_versions', ['questionid' => $createdquestion->id], '*', $strictness = MUST_EXIST);
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

        $this->assertEquals($returnvalue['questionid'], $createdquestion->id);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 2);
        $this->assertInstanceOf('\core\event\question_created', $events['0']);
        $this->assertInstanceOf('\core\event\questions_imported', $events['1']);
    }
}
