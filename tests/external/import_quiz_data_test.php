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
 * Unit tests for import_quiz_data function of gitsync webservice
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
 * Test the import_quiz_data webservice function.
 * @runTestsInSeparateProcesses
 * @group qbank_gitsync
 *
 * @covers \gitsync\external\import_quiz_data::execute
 */
final class import_quiz_data_test extends externallib_advanced_testcase {
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
    /** @var \stdClass generated question object */
    protected \stdClass $q2;
    /** @var int question bank entry id for generated question */
    protected int $qbankentryid2;
    /** @var \stdClass generated user object */
    protected \stdClass $user;
    /** Name of question to be generated and exported. */
    const QNAME = 'Example short answer question';
    /** QUIZNAME - Moodle quiz name value. */
    const QUIZNAME = 'Example quiz';
    /** QUIZINTRO - Moodle quiz intro value. */
    const QUIZINTRO = 'Quiz intro';
    /** FEEDBACK - Quiz feedback value. */
    const FEEDBACK = 'Quiz feedback';
    /** HEADING1 - heading value. */
    const HEADING1 = 'Heading 1';
    /** HEADING2 - heading value. */
    const HEADING2 = 'Heading 2';
    /** @var array input parameters */
    protected array $quizinput = [
        'quiz' => [
            'name' => self::QUIZNAME,
            'intro' => self::QUIZINTRO,
            'introformat' => '0',
            'coursename' => null,
            'courseid' => null,
            'questionsperpage' => '0',
            'grade' => '100.00000',
            'navmethod' => 'free',
        ],
        'sections' => [
            [
                'firstslot' => '1',
                'heading' => self::HEADING1,
                'shufflequestions' => 0,
            ],
            [
                'firstslot' => '2',
                'heading' => self::HEADING2,
                'shufflequestions' => 0,
            ],
        ],
        'questions' => [
            [
                'questionbankentryid' => null,
                'slot' => '1',
                'page' => '1',
                'requireprevious' => 0,
                'maxmark' => '1.0000000',
            ],
            [
                'questionbankentryid' => null,
                'slot' => '2',
                'page' => '2',
                'requireprevious' => 0,
                'maxmark' => '2.0000000',
            ],
        ],
        'feedback' => [
            [
                'feedbacktext' => self::FEEDBACK,
                'feedbacktextformat' => '0',
                'mingrade' => '0.0000000',
                'maxgrade' => '50.000000',
            ],
        ],
    ];

    public function setUp(): void {
        parent::setUp();
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
        $this->q2 = $this->generator->create_question('shortanswer', null,
                        ['name' => self::QNAME . '2', 'category' => $this->qcategory->id]);
        $this->qbankentryid2 = $DB->get_field('question_versions', 'questionbankentryid',
                        ['questionid' => $this->q2->id], $strictness = MUST_EXIST);
        $this->quizinput['quiz']['coursename'] = $this->course->fullname;
        $this->quizinput['quiz']['courseid'] = $this->course->id;
        $this->quizinput['questions'][0]['questionbankentryid'] = $this->qbankentryid;
        $this->quizinput['questions'][1]['questionbankentryid'] = $this->qbankentryid2;
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

        $returnvalue = import_quiz_data::execute($this->quizinput['quiz'], $this->quizinput['sections'],
                                            $this->quizinput['questions'], $this->quizinput['feedback']);

        // We need to execute the return values cleaning process to simulate
        // the web service server.
        $returnvalue = external_api::clean_returnvalue(
            import_quiz_data::execute_returns(),
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
        import_quiz_data::execute($this->quizinput['quiz'], $this->quizinput['sections'],
                                $this->quizinput['questions'], $this->quizinput['feedback']);
    }

    /**
     * Test the execute function fails when no webservice export capability assigned.
     */
    public function test_no_webservice_access(): void {
        global $DB;
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);
        $this->unassignUserCapability('qbank/gitsync:importquestions', \context_system::instance()->id, $managerroleid);
        $this->expectException(required_capability_exception::class);
        $this->expectExceptionMessage('you do not currently have permissions to do that (Import)');
        import_quiz_data::execute($this->quizinput['quiz'], $this->quizinput['sections'],
                                $this->quizinput['questions'], $this->quizinput['feedback']);
    }

    /**
     * Test the execute function fails when user has no access to supplied context.
     */
    public function test_import_capability(): void {
        $this->expectException(require_login_exception::class);
        $this->expectExceptionMessage('Not enrolled');
        import_quiz_data::execute($this->quizinput['quiz'], $this->quizinput['sections'],
                                $this->quizinput['questions'], $this->quizinput['feedback']);
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
        $this->quizinput['quiz']['coursename'] = $course2->fullname;
        $this->quizinput['quiz']['courseid'] = $course2->id;
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Not enrolled');
        // User has import capability on course 1 but not course 2.
        import_quiz_data::execute($this->quizinput['quiz'], $this->quizinput['sections'],
                                    $this->quizinput['questions'], $this->quizinput['feedback']);
    }

    /**
     * Test output of execute function.
     */
    public function test_import_with_sections_and_feedback(): void {
        global $DB;
        // Set the required capabilities - webservice access and export rights on course.
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);
        $returnvalue = import_quiz_data::execute($this->quizinput['quiz'], $this->quizinput['sections'],
                        $this->quizinput['questions'], $this->quizinput['feedback']);

        $returnvalue = external_api::clean_returnvalue(
            import_quiz_data::execute_returns(),
            $returnvalue
        );

        $quizzes = $DB->get_records('quiz');
        $quiz = array_shift($quizzes);
        $this->assertEquals(self::QUIZNAME, $quiz->name);
        $this->assertEquals(self::QUIZINTRO, $quiz->intro);
        $this->assertEquals(0, $quiz->questionsperpage);
        $this->assertEquals('deferredfeedback', $quiz->preferredbehaviour);
        $this->assertEquals(2, $quiz->decimalpoints);
        $this->assertEquals(4352, $quiz->reviewmarks);
        $this->assertEquals(100, $quiz->grade);

        $sections = $DB->get_records('quiz_sections');
        $this->assertEquals(2, count($sections));
        $section1 = array_shift($sections);
        $section2 = array_shift($sections);
        $this->assertEquals(self::HEADING1, $section1->heading);
        $this->assertEquals(1, $section1->firstslot);
        $this->assertEquals(self::HEADING2, $section2->heading);
        $this->assertEquals(2, $section2->firstslot);

        $slots = $DB->get_records('quiz_slots');
        $this->assertEquals(2, count($slots));
        $slot1 = array_shift($slots);
        $slot2 = array_shift($slots);
        $this->assertEquals(0, $slot1->requireprevious);
        $this->assertEquals(1, $slot1->page);
        $this->assertEquals(1, $slot1->maxmark);
        $this->assertEquals(0, $slot2->requireprevious);
        $this->assertEquals(2, $slot2->page);
        $this->assertEquals(2, $slot2->maxmark);

        $feedback = $DB->get_records('quiz_feedback');
        $this->assertEquals(1, count($feedback));
        $feedback1 = array_shift($feedback);
        $this->assertEquals(self::FEEDBACK, $feedback1->feedbacktext);
        $this->assertEquals(0, $feedback1->feedbacktextformat);
        $this->assertEquals(0, $feedback1->mingrade);
        $this->assertEquals(50, $feedback1->maxgrade);
    }

    /**
     * Test output of execute function.
     */
    public function test_import_without_sections_and_feedback(): void {
        global $DB;
        // Set the required capabilities - webservice access and export rights on course.
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);
        $returnvalue = import_quiz_data::execute($this->quizinput['quiz'], [],
                        $this->quizinput['questions'], []);

        $returnvalue = external_api::clean_returnvalue(
            import_quiz_data::execute_returns(),
            $returnvalue
        );

        $sections = $DB->get_records('quiz_sections');
        $this->assertEquals(1, count($sections));
        $section1 = array_shift($sections);
        $this->assertEquals('', $section1->heading);
        $this->assertEquals(1, $section1->firstslot);

        $slots = $DB->get_records('quiz_slots');
        $this->assertEquals(2, count($slots));
        $slot1 = array_shift($slots);
        $slot2 = array_shift($slots);
        $this->assertEquals(0, $slot1->requireprevious);
        $this->assertEquals(0, $slot2->requireprevious);

        $feedback = $DB->get_records('quiz_feedback');
        $this->assertEquals(1, count($feedback));
        $feedback1 = array_shift($feedback);
        $this->assertEquals('', $feedback1->feedbacktext);
        $this->assertEquals(1, $feedback1->feedbacktextformat);
        $this->assertEquals(0, $feedback1->mingrade);
        $this->assertEquals(101, $feedback1->maxgrade);
    }

    /**
     * Test output of execute function.
     */
    public function test_import_with_require_previous(): void {
        global $DB;
        // Set the required capabilities - webservice access and export rights on course.
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);
        $this->quizinput['questions'][1]['requireprevious'] = '1';
        $returnvalue = import_quiz_data::execute($this->quizinput['quiz'], [],
                        $this->quizinput['questions'], []);

        $returnvalue = external_api::clean_returnvalue(
            import_quiz_data::execute_returns(),
            $returnvalue
        );

        $slots = $DB->get_records('quiz_slots');
        $this->assertEquals(2, count($slots));
        $slot1 = array_shift($slots);
        $slot2 = array_shift($slots);
        $this->assertEquals(0, $slot1->requireprevious);
        $this->assertEquals(1, $slot2->requireprevious);
    }
}
