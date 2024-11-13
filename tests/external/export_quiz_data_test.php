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
 * Unit tests for export_quiz_data function of gitsync webservice
 *
 * @package    qbank_gitsync
 * @copyright  2024 The Open University
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
 * Test the export_quiz_data webservice function.
 * @runTestsInSeparateProcesses
 * @group qbank_gitsync
 *
 * @covers \gitsync\external\export_quiz_data::execute
 */
class export_quiz_data_test extends externallib_advanced_testcase {
    /** @var \core_question_generator plugin generator */
    protected \core_question_generator  $generator;
    /** @var \mod_quiz_generator plugin generator */
    protected \mod_quiz_generator $quizgenerator;
    /** @var \stdClass generated course object */
    protected \stdClass $course;
    /** @var \stdClass generated quiz object */
    protected \stdClass $quiz;
    /** @var \stdClass generated question_category object */
    protected \stdClass $qcategory;
    /** @var \stdClass generated question object */
    protected \stdClass $q;
    /** @var int quix module id for generated quiz */
    protected int $quizmoduleid;

    /** @var int question bank entry id for generated question */
    protected int $qbankentryid;
    /** @var \stdClass generated user object */
    protected \stdClass $user;
    /** Name of question to be generated and exported. */
    const QNAME = 'Example short answer question';
    const QUIZNAME = 'Example quiz';

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
        $this->q = $this->generator->create_question('shortanswer', null,
                        ['name' => self::QNAME, 'category' => $this->qcategory->id]);
        $this->qbankentryid = $DB->get_field('question_versions', 'questionbankentryid',
                        ['questionid' => $this->q->id], $strictness = MUST_EXIST);
        $q2 = $this->generator->create_question('shortanswer', null,
                        ['name' => self::QNAME . '2', 'category' => $this->qcategory->id]);

        $quizgenerator =  new \testing_data_generator();
        $this->quizgenerator =  $quizgenerator->get_plugin_generator('mod_quiz');

        $this->quiz = $this->quizgenerator->create_instance(array('course' => $this->course->id, 'name' => self::QUIZNAME, 'questionsperpage' => 0,
            'grade' => 100.0, 'sumgrades' => 2, 'preferredbehaviour' => 'immediatefeedback'));

        $this->quizmoduleid = $this->quiz->cmid;
        \quiz_add_quiz_question($this->q->id, $this->quiz);
        \quiz_add_quiz_question($q2->id, $this->quiz);
        $quizobj = \quiz::create($this->quiz->id);
        \mod_quiz\structure::create_for_quiz($quizobj);

    }

    /**
     * Test the execute function when capabilities are present.
     */
    public function test_capabilities(): void {
        global $DB;
        // Set the required capabilities - webservice access and export rights on course.
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);

        $returnvalue = export_quiz_data::execute($this->quizmoduleid, null, null);

        // We need to execute the return values cleaning process to simulate
        // the web service server.
        $returnvalue = external_api::clean_returnvalue(
            export_quiz_data::execute_returns(),
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
        export_quiz_data::execute($this->quizmoduleid, null, null);
    }

    /**
     * Test the execute function fails when no webservice export capability assigned.
     */
    public function test_no_webservice_access(): void {
        global $DB;
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);
        $this->unassignUserCapability('qbank/gitsync:listquestions', \context_system::instance()->id, $managerroleid);
        $this->expectException(required_capability_exception::class);
        $this->expectExceptionMessage('you do not currently have permissions to do that (List)');
        export_quiz_data::execute($this->quizmoduleid, null, null);
    }

    /**
     * Test the execute function fails when user has no access to supplied context.
     */
    public function test_export_capability(): void {
        $this->expectException(require_login_exception::class);
        $this->expectExceptionMessage('Not enrolled');
        export_quiz_data::execute($this->quizmoduleid, null, null);
    }

    /**
     * Test the execute function fails when the question is not accessible in the supplied context.
     */
    public function test_question_is_in_supplied_context(): void {
        global $DB;
        $context = context_course::instance($this->course->id);
        $course2 = $this->getDataGenerator()->create_course();
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);
        $quiz2 = $this->quizgenerator->create_instance(array('course' => $course2, 'questionsperpage' => 0,
        'grade' => 100.0, 'sumgrades' => 2, 'preferredbehaviour' => 'immediatefeedback'));
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Not enrolled');
        // User has list capability on course 1 but not course 2.
        export_quiz_data::execute($quiz2->cmid, null, null);
    }

    /**
     * Test output of execute function.
     */
    public function test_export_with_moduleid(): void {
        global $DB;
        // Set the required capabilities - webservice access and export rights on course.
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);
        $returnvalue = export_quiz_data::execute($this->quizmoduleid, null, null);;

        $returnvalue = external_api::clean_returnvalue(
            export_quiz_data::execute_returns(),
            $returnvalue
        );
        $this->assertEquals(self::QUIZNAME, $returnvalue['quiz']['name']);
        $this->assertEquals(2, count($returnvalue['questions']));
        $this->assertEquals($this->qbankentryid, $returnvalue['questions'][0]['questionbankentryid']);
        $this->assertEquals(1, count($returnvalue['sections']));
    }

    /**
     * Test output of execute function.
     */
    public function test_export_with_name(): void {
        global $DB;
        // Set the required capabilities - webservice access and export rights on course.
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);
        $returnvalue = export_quiz_data::execute(null, $this->course->fullname, self::QUIZNAME);;

        $returnvalue = external_api::clean_returnvalue(
            export_quiz_data::execute_returns(),
            $returnvalue
        );
        $this->assertEquals(self::QUIZNAME, $returnvalue['quiz']['name']);
        $this->assertEquals(2, count($returnvalue['questions']));
        $this->assertEquals($this->qbankentryid, $returnvalue['questions'][0]['questionbankentryid']);
        $this->assertEquals(1, count($returnvalue['sections']));
    }
}
