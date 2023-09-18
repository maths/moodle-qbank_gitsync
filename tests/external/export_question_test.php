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
 * Unit tests for export_question function of gitsync webservice
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

use context_course;
use externallib_advanced_testcase;
use external_api;
use required_capability_exception;
use require_login_exception;
use moodle_exception;

/**
 * Test the export_question webservice function.
 *
 * @covers \gitsync\external\export_question::execute
 */
class export_question_test extends externallib_advanced_testcase {
    /** @var \core_question_generator plugin generator */
    protected \core_question_generator  $generator;
    /** @var \stdClass generated course object */
    protected \stdClass $course;
    /** @var \stdClass generated question_category object */
    protected \stdClass $qcategory;
    /** @var \stdClass generated question object */
    protected \stdClass $q;
    /** @var int question bank entry id for generated question */
    protected int $qbankentryid;
    /** @var \stdClass generated user object */
    protected \stdClass $user;
    /** Name of question to be generated and exported. */
    const QNAME = 'Example STACK question';

    public function setUp(): void {
        global $DB;
        $this->resetAfterTest();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->course = $this->getDataGenerator()->create_course();
        $this->qcategory = $this->generator->create_question_category(
                        ['contextid' => \context_course::instance($this->course->id)->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->user = $user;
        $this->setUser($user);
        $this->q = $this->generator->create_question('stack', 'test3',
                        ['name' => self::QNAME, 'category' => $this->qcategory->id]);
        $this->qbankentryid = $DB->get_field('question_versions', 'questionbankentryid',
                                             ['questionid' => $this->q->id], $strictness = MUST_EXIST);

    }

    /**
     * Test the execute function when capabilities are present.
     */
    public function test_capabilities(): void {
        global $DB;
        // Set the required capabilities - webservice access and export rights on course.
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', array('shortname' => 'manager'));
        role_assign($managerroleid, $this->user->id, $context->id);

        $returnvalue = export_question::execute($this->qbankentryid);

        // We need to execute the return values cleaning process to simulate
        // the web service server.
        $returnvalue = external_api::clean_returnvalue(
            export_question::execute_returns(),
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
        export_question::execute($this->qbankentryid);
    }

    /**
     * Test the execute function fails when no webservice export capability assigned.
     */
    public function test_no_webservice_access(): void {
        global $DB;
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', array('shortname' => 'manager'));
        role_assign($managerroleid, $this->user->id, $context->id);
        $this->unassignUserCapability('qbank/gitsync:exportquestions', \context_system::instance()->id, $managerroleid);
        $this->expectException(required_capability_exception::class);
        $this->expectExceptionMessage('you do not currently have permissions to do that (Export)');
        export_question::execute($this->qbankentryid);
    }

    /**
     * Test the execute function fails when user has no access to supplied context.
     */
    public function test_export_capability(): void {
        $this->expectException(require_login_exception::class);
        $this->expectExceptionMessage('Not enrolled');
        export_question::execute($this->qbankentryid);
    }

    /**
     * Test the execute function fails when the question is not accessible in the supplied context.
     */
    public function test_question_is_in_supplied_context(): void {
        global $DB;
        $context = context_course::instance($this->course->id);
        $course2 = $this->getDataGenerator()->create_course();
        $catincourse2 = $this->generator->create_question_category(['contextid' => \context_course::instance($course2->id)->id]);
        $qincourse2 = $this->generator->create_question('numerical', null,
            ['name' => 'Example numerical question', 'category' => $catincourse2->id]);
        $qbankentryid2 = $DB->get_field('question_versions', 'questionbankentryid',
                                        ['questionid' => $qincourse2->id], $strictness = MUST_EXIST);

        $managerroleid = $DB->get_field('role', 'id', array('shortname' => 'manager'));
        role_assign($managerroleid, $this->user->id, $context->id);
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Not enrolled');
        // Trying to export question from course 2 using context of course 1.
        // User has export capability on course 1 but not course 2.
        export_question::execute($qbankentryid2);
    }

    /**
     * Test output of execute function.
     */
    public function test_export(): void {
        global $DB;
        // Set the required capabilities - webservice access and export rights on course.
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', array('shortname' => 'manager'));
        role_assign($managerroleid, $this->user->id, $context->id);
        $sink = $this->redirectEvents();
        $returnvalue = export_question::execute($this->qbankentryid);

        $returnvalue = external_api::clean_returnvalue(
            export_question::execute_returns(),
            $returnvalue
        );

        $this->assertStringContainsString("question: {$this->q->id}", $returnvalue['question']);
        $this->assertStringContainsString(self::QNAME, $returnvalue['question']);

        $events = $sink->get_events();
        $this->assertEquals(count($events), 1);
        $this->assertInstanceOf('\core\event\questions_exported', $events['0']);
    }
}
