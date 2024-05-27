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
 * Unit tests for get_question_list function of gitsync webservice
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
 * Test the get_question_list webservice function.
 * @runTestsInSeparateProcesses
 * @group qbank_gitsync
 *
 * @covers \gitsync\external\get_question_list::execute
 */
class get_question_list_test extends externallib_advanced_testcase {
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
    /** Name of question to be generated and listed. */
    const QNAME = 'Example short answer question';

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

    }

    /**
     * Test the execute function when capabilities are present.
     */
    public function test_capabilities(): void {
        global $DB;
        // Set the required capabilities - webservice access and list rights on course.
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);

        $returnvalue = get_question_list::execute('top', 50, $this->course->fullname, null, null,
                                                  null, null, false, ['']);

        // We need to execute the return values cleaning process to simulate
        // the web service server.
        $returnvalue = external_api::clean_returnvalue(
            get_question_list::execute_returns(),
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
        get_question_list::execute('top', 50, $this->course->fullname, null, null,
                                   null, null, false, ['']);
    }

    /**
     * Test the execute function fails when no webservice list capability assigned.
     */
    public function test_no_webservice_access(): void {
        global $DB;
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);
        $this->unassignUserCapability('qbank/gitsync:listquestions', \context_system::instance()->id, $managerroleid);
        $this->expectException(required_capability_exception::class);
        $this->expectExceptionMessage('you do not currently have permissions to do that (List)');
        get_question_list::execute('top', 50, $this->course->fullname, null, null,
                                   null, null, false, ['']);
    }

    /**
     * Test the execute function fails when user has no access to supplied context.
     */
    public function test_list_capability(): void {
        $this->expectException(require_login_exception::class);
        $this->expectExceptionMessage('Not enrolled');
        get_question_list::execute('top', 50, $this->course->fullname, null, null,
                                   null, null, false, ['']);
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

        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('Not enrolled');
        // Trying to list question from course 2 using context of course 1.
        // User has list capability on course 1 but not course 2.
        get_question_list::execute('top', 50, $course2->fullname, null, null,
                                   null, null, false, ['']);
    }

    /**
     * Test output of execute function.
     */
    public function test_list(): void {
        global $DB;
        // Set the required capabilities - webservice access and list rights on course.
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);
        $qcategory2 = $this->generator->create_question_category(
            ['contextid' => \context_course::instance($this->course->id)->id]);
        $q2 = $this->generator->create_question('shortanswer', null,
                                                ['name' => self::QNAME . '2', 'category' => $qcategory2->id]);
        $qbankentryid2 = $DB->get_field('question_versions', 'questionbankentryid',
                             ['questionid' => $q2->id], $strictness = MUST_EXIST);
        $sink = $this->redirectEvents();
        $returnvalue = get_question_list::execute('top', 50, $this->course->fullname, null, null,
                                                  null, null, false, ['']);

        $returnvalue = external_api::clean_returnvalue(
            get_question_list::execute_returns(),
            $returnvalue
        );

        $returnedq1 = [];
        $returnedq2 = [];
        $this->assertEquals(count($returnvalue['questions']), 2);
        foreach ($returnvalue['questions'] as $returnedq) {
            if ($returnedq['questionbankentryid'] === $this->q->questionbankentryid) {
                $returnedq1 = $returnedq;
            } else if ($returnedq['questionbankentryid'] === $qbankentryid2) {
                $returnedq2 = $returnedq;
            }
        }

        $this->assertEquals($this->q->name, $returnedq1['name']);
        $this->assertEquals($q2->name, $returnedq2['name']);
        $this->assertEquals($this->qcategory->name, $returnedq1['questioncategory']);
        $this->assertEquals($qcategory2->name, $returnedq2['questioncategory']);

        $this->assertEquals($this->course->fullname, $returnvalue['contextinfo']['coursename']);
        $this->assertEquals($this->course->id, $returnvalue['contextinfo']['instanceid']);
        $this->assertEquals(null, $returnvalue['contextinfo']['categoryname']);
        $this->assertEquals(null, $returnvalue['contextinfo']['modulename']);

        $events = $sink->get_events();
        $this->assertEquals(count($events), 0);
    }

    /**
     * Test output of execute function using instanceid to retrieve list.
     */
    public function test_list_instanceid(): void {
        global $DB;
        // Set the required capabilities - webservice access and list rights on course.
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);
        $qcategory2 = $this->generator->create_question_category(
            ['contextid' => \context_course::instance($this->course->id)->id]);
        $q2 = $this->generator->create_question('shortanswer', null,
                                                ['name' => self::QNAME . '2', 'category' => $qcategory2->id]);
        $qbankentryid2 = $DB->get_field('question_versions', 'questionbankentryid',
                             ['questionid' => $q2->id], $strictness = MUST_EXIST);
        $sink = $this->redirectEvents();
        $returnvalue = get_question_list::execute('top', 50, null, null, null,
                                                  null, $this->course->id, false, ['']);

        $returnvalue = external_api::clean_returnvalue(
            get_question_list::execute_returns(),
            $returnvalue
        );

        $returnedq1 = [];
        $returnedq2 = [];
        $this->assertEquals(count($returnvalue['questions']), 2);
        foreach ($returnvalue['questions'] as $returnedq) {
            if ($returnedq['questionbankentryid'] === $this->q->questionbankentryid) {
                $returnedq1 = $returnedq;
            } else if ($returnedq['questionbankentryid'] === $qbankentryid2) {
                $returnedq2 = $returnedq;
            }
        }

        $this->assertEquals($this->q->name, $returnedq1['name']);
        $this->assertEquals($q2->name, $returnedq2['name']);
        $this->assertEquals($this->qcategory->name, $returnedq1['questioncategory']);
        $this->assertEquals($qcategory2->name, $returnedq2['questioncategory']);

        $this->assertEquals($this->course->fullname, $returnvalue['contextinfo']['coursename']);
        $this->assertEquals($this->course->id, $returnvalue['contextinfo']['instanceid']);
        $this->assertEquals(null, $returnvalue['contextinfo']['categoryname']);
        $this->assertEquals(null, $returnvalue['contextinfo']['modulename']);

        $events = $sink->get_events();
        $this->assertEquals(count($events), 0);
    }

    /**
     * Test output of execute function getting questions via an array.
     */
    public function test_list_with_array(): void {
        global $DB;
        // Set the required capabilities - webservice access and list rights on course.
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);
        $qcategory2 = $this->generator->create_question_category(
            ['contextid' => \context_course::instance($this->course->id)->id]);
        $q2 = $this->generator->create_question('shortanswer', null,
                                                ['name' => self::QNAME . '2', 'category' => $qcategory2->id]);
        $qbankentryid2 = $DB->get_field('question_versions', 'questionbankentryid',
                             ['questionid' => $q2->id], $strictness = MUST_EXIST);
        $course2 = $this->getDataGenerator()->create_course();
        $catincourse2 = $this->generator->create_question_category(['contextid' => \context_course::instance($course2->id)->id]);
        $qincourse2 = $this->generator->create_question('numerical', null,
            ['name' => 'Example numerical question', 'category' => $catincourse2->id]);
        $qbankentryid3 = $DB->get_field('question_versions', 'questionbankentryid',
                                                             ['questionid' => $qincourse2->id], $strictness = MUST_EXIST);

        $sink = $this->redirectEvents();
        $returnvalue = get_question_list::execute('top', 50, null, null, null,
                                                  null, $this->course->id, false, [$qbankentryid2, $qbankentryid3]);

        $returnvalue = external_api::clean_returnvalue(
            get_question_list::execute_returns(),
            $returnvalue
        );

        $returnedq1 = [];
        $returnedq2 = [];
        $returnedq3 = [];
        $this->assertEquals(count($returnvalue['questions']), 2);
        foreach ($returnvalue['questions'] as $returnedq) {
            if ($returnedq['questionbankentryid'] === $this->q->questionbankentryid) {
                $returnedq1 = $returnedq;
            } else if ($returnedq['questionbankentryid'] === $qbankentryid2) {
                $returnedq2 = $returnedq;
            } else if ($returnedq['questionbankentryid'] === $qbankentryid3) {
                $returnedq3 = $returnedq;
            }
        }

        $this->assertEquals([], $returnedq1);

        $this->assertEquals(null, $returnedq2['name']);
        $this->assertEquals(null, $returnedq3['name']);
        $this->assertEquals(null, $returnedq2['questioncategory']);
        $this->assertEquals(null, $returnedq3['questioncategory']);

        $this->assertEquals($this->course->fullname, $returnvalue['contextinfo']['coursename']);
        $this->assertEquals($this->course->id, $returnvalue['contextinfo']['instanceid']);
        $this->assertEquals(null, $returnvalue['contextinfo']['categoryname']);
        $this->assertEquals(null, $returnvalue['contextinfo']['modulename']);

        $events = $sink->get_events();
        $this->assertEquals(count($events), 0);
    }

    /**
     * Test question category is in the supplied context.
     */
    public function test_question_category_is_in_supplied_context(): void {
        global $DB;
        $course2 = $this->getDataGenerator()->create_course();
        $catincourse2 = $this->generator->create_question_category(['contextid' => \context_course::instance($course2->id)->id]);
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('The category is not in the supplied context.');
        // Trying to list question from course 2 using context of course 1.
        // User has list capability on course 1 but not course 2.
        get_question_list::execute('top', 50, $this->course->fullname, null, null,
                                    $catincourse2->id, null, false, ['']);
    }

    /**
     * Test get full question catgeory path
     * @covers \gitsync\external\get_question_list\get_category_path()
     *
     * @return void
     */
    public function test_get_category_path() {
        $contextid = \context_course::instance($this->course->id)->id;
        $qcategory2 = $this->generator->create_question_category(
            ['contextid' => $contextid, 'parent' => $this->qcategory->id, 'name' => "Tim's questions"]);
        $qcategory3 = $this->generator->create_question_category(
            ['contextid' => $contextid, 'parent' => $qcategory2->id, 'name' => "Tricky things like / // and so on"]);
        $qcategory4 = $this->generator->create_question_category(
            ['contextid' => $contextid, 'parent' => $qcategory3->id, 'name' => 'Category name ending in /']);
        $qcategory5 = $this->generator->create_question_category(
            ['contextid' => $contextid, 'parent' => $qcategory4->id, 'name' => '/ and one that starts with one']);
        $qcategory6 = $this->generator->create_question_category(
            ['contextid' => $contextid, 'parent' => $qcategory5->id,
             'name' => '<span lang="en" class="multilang">Matematically</span> ' .
             '<span lang="sv" class="multilang">Matematiskt (svenska)</span>"',
            ]);

        $this->assertEquals('top/' . $this->qcategory->name .
            '/Tim\'s questions/Tricky things like // //// and so on/Category name ending in ' .
            '// / // and one that starts with one/<span lang="en" class="multilang">Matematically<//span> ' .
            '<span lang="sv" class="multilang">Matematiskt (svenska)<//span>"',
            get_question_list::get_category_path($qcategory6));
    }

    /**
     * Test output of execute function when subcategory name supplied.
     */
    public function test_list_with_subcategory_name(): void {
        global $DB;
        // Set the required capabilities - webservice access and list rights on course.
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);
        $qcategory2 = $this->generator->create_question_category(
            ['contextid' => \context_course::instance($this->course->id)->id]);
        $q2 = $this->generator->create_question('shortanswer', null,
                                                ['name' => self::QNAME . '2', 'category' => $qcategory2->id]);
        $qbankentryid2 = $DB->get_field('question_versions', 'questionbankentryid',
                             ['questionid' => $q2->id], $strictness = MUST_EXIST);
        $sink = $this->redirectEvents();
        $returnvalue = get_question_list::execute('top/' . $qcategory2->name, 50, $this->course->fullname, null, null,
                                                  null, null, false, ['']);

        $returnvalue = external_api::clean_returnvalue(
            get_question_list::execute_returns(),
            $returnvalue
        );

        $returnedq2 = [];
        $this->assertEquals(count($returnvalue['questions']), 1);
        foreach ($returnvalue['questions'] as $returnedq) {
            if ($returnedq['questionbankentryid'] === $qbankentryid2) {
                $returnedq2 = $returnedq;
            }
        }

        $this->assertEquals($q2->name, $returnedq2['name']);
        $this->assertEquals($qcategory2->name, $returnedq2['questioncategory']);

        $this->assertEquals($this->course->fullname, $returnvalue['contextinfo']['coursename']);
        $this->assertEquals($this->course->id, $returnvalue['contextinfo']['instanceid']);
        $this->assertEquals(null, $returnvalue['contextinfo']['categoryname']);
        $this->assertEquals(null, $returnvalue['contextinfo']['modulename']);

        $events = $sink->get_events();
        $this->assertEquals(count($events), 0);
    }

    /**
     * Test output of execute function when subcategory id supplied.
     */
    public function test_list_with_subcategory_id(): void {
        global $DB;
        // Set the required capabilities - webservice access and list rights on course.
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);
        $qcategory2 = $this->generator->create_question_category(
            ['contextid' => \context_course::instance($this->course->id)->id]);
        $q2 = $this->generator->create_question('shortanswer', null,
                                                ['name' => self::QNAME . '2', 'category' => $qcategory2->id]);
        $qbankentryid2 = $DB->get_field('question_versions', 'questionbankentryid',
                             ['questionid' => $q2->id], $strictness = MUST_EXIST);
        $sink = $this->redirectEvents();
        $returnvalue = get_question_list::execute('top', 50, $this->course->fullname, null, null,
                                $qcategory2->id, null, false, ['']);

        $returnvalue = external_api::clean_returnvalue(
            get_question_list::execute_returns(),
            $returnvalue
        );

        $returnedq2 = [];
        $this->assertEquals(count($returnvalue['questions']), 1);
        foreach ($returnvalue['questions'] as $returnedq) {
            if ($returnedq['questionbankentryid'] === $qbankentryid2) {
                $returnedq2 = $returnedq;
            }
        }

        $this->assertEquals($q2->name, $returnedq2['name']);
        $this->assertEquals($qcategory2->name, $returnedq2['questioncategory']);

        $this->assertEquals($this->course->fullname, $returnvalue['contextinfo']['coursename']);
        $this->assertEquals($this->course->id, $returnvalue['contextinfo']['instanceid']);
        $this->assertEquals(null, $returnvalue['contextinfo']['categoryname']);
        $this->assertEquals(null, $returnvalue['contextinfo']['modulename']);

        $events = $sink->get_events();
        $this->assertEquals(count($events), 0);
    }

    /**
     * Test output of execute function when subcategory name supplied and ignore category.
     */
    public function test_list_with_subcategory_name_and_ignore(): void {
        global $DB;
        // Set the required capabilities - webservice access and list rights on course.
        // Q1 in original category. Q2 in Cat2. Q3 in SubCat1. Q4 in SubCat2.
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);
        $qcategory2 = $this->generator->create_question_category(
            ['contextid' => \context_course::instance($this->course->id)->id, 'name' => 'Cat2_DO_NOT_SHARE']);
        $qcategory3 = $this->generator->create_question_category(
            ['contextid' => \context_course::instance($this->course->id)->id, 'name' => 'SubCat1',
            'parent' => $qcategory2->id]);
        $qcategory4 = $this->generator->create_question_category(
            ['contextid' => \context_course::instance($this->course->id)->id, 'name' => 'SubCat2',
            'parent' => $qcategory2->id]);

        $q2 = $this->generator->create_question('shortanswer', null,
                                            ['name' => self::QNAME . '2', 'category' => $qcategory2->id]);
        $q3 = $this->generator->create_question('shortanswer', null,
                                            ['name' => self::QNAME . '3', 'category' => $qcategory3->id]);
        $q4 = $this->generator->create_question('shortanswer', null,
                                            ['name' => self::QNAME . '4', 'category' => $qcategory4->id]);

        $sink = $this->redirectEvents();
        $returnvalue = get_question_list::execute('top', 50, $this->course->fullname, null, null,
                                                  null, null, false, [''], '/.*DO_NOT_SHARE/');

        $returnvalue = external_api::clean_returnvalue(
            get_question_list::execute_returns(),
            $returnvalue
        );

        $wrongq = false;
        $this->assertEquals(1, count($returnvalue['questions']));
        foreach ($returnvalue['questions'] as $returnedq) {
            if (array_search($returnedq['questionbankentryid'], [$this->qbankentryid]) === false) {
                $wrongq = true;
            }
        }

        $this->assertEquals($wrongq, false);

        $this->assertEquals($this->course->fullname, $returnvalue['contextinfo']['coursename']);
        $this->assertEquals($this->course->id, $returnvalue['contextinfo']['instanceid']);
        $this->assertEquals(null, $returnvalue['contextinfo']['categoryname']);
        $this->assertEquals(null, $returnvalue['contextinfo']['modulename']);
        $this->assertEquals('/.*DO_NOT_SHARE/', $returnvalue['contextinfo']['ignorecat']);

        $events = $sink->get_events();
        $this->assertEquals(count($events), 0);
    }

    /**
     * Test output of execute function when ignore category.
     */
    public function test_list_with_ignore(): void {
        global $DB;
        // Set the required capabilities - webservice access and list rights on course.
        // Q1 in original category. Q2 in Cat2. Q3 in SubCat1. Q4 in SubCat2.
        $context = context_course::instance($this->course->id);
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $context->id);
        $qcategory2 = $this->generator->create_question_category(
            ['contextid' => \context_course::instance($this->course->id)->id, 'name' => 'Cat2']);
        $qcategory3 = $this->generator->create_question_category(
            ['contextid' => \context_course::instance($this->course->id)->id, 'name' => 'SubCat1',
            'parent' => $qcategory2->id]);
        $qcategory4 = $this->generator->create_question_category(
            ['contextid' => \context_course::instance($this->course->id)->id, 'name' => 'SubCat2_DO_NOT_SHARE',
            'parent' => $qcategory2->id]);

        $q2 = $this->generator->create_question('shortanswer', null,
                                            ['name' => self::QNAME . '2', 'category' => $qcategory2->id]);
        $q3 = $this->generator->create_question('shortanswer', null,
                                            ['name' => self::QNAME . '3', 'category' => $qcategory3->id]);
        $q4 = $this->generator->create_question('shortanswer', null,
                                            ['name' => self::QNAME . '4', 'category' => $qcategory4->id]);


        $qbankentryid2 = $DB->get_field('question_versions', 'questionbankentryid',
                             ['questionid' => $q2->id], $strictness = MUST_EXIST);
        $qbankentryid3 = $DB->get_field('question_versions', 'questionbankentryid',
                             ['questionid' => $q3->id], $strictness = MUST_EXIST);

        $sink = $this->redirectEvents();
        $returnvalue = get_question_list::execute('top/' . $qcategory2->name, 50, $this->course->fullname, null, null,
                                                  null, null, false, [''], '/.*DO_NOT_SHARE/');

        $returnvalue = external_api::clean_returnvalue(
            get_question_list::execute_returns(),
            $returnvalue
        );

        $wrongq = false;
        $this->assertEquals(2, count($returnvalue['questions']));
        foreach ($returnvalue['questions'] as $returnedq) {
            // Q1 excluded by subcategory. Q4 excluded by ignore.
            if (array_search($returnedq['questionbankentryid'], [$qbankentryid2, $qbankentryid3]) === false) {
                $wrongq = true;
            }
        }

        $this->assertEquals($wrongq, false);

        $this->assertEquals($this->course->fullname, $returnvalue['contextinfo']['coursename']);
        $this->assertEquals($this->course->id, $returnvalue['contextinfo']['instanceid']);
        $this->assertEquals(null, $returnvalue['contextinfo']['categoryname']);
        $this->assertEquals(null, $returnvalue['contextinfo']['modulename']);
        $this->assertEquals('/.*DO_NOT_SHARE/', $returnvalue['contextinfo']['ignorecat']);

        $events = $sink->get_events();
        $this->assertEquals(count($events), 0);
    }
}
