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
 * @subpackage cli
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
use require_login_exception;

class export_question_test extends externallib_advanced_testcase {
    /**
     * Test the execute function when capabilities are present.
     *
     * @covers \gitsync\external\export_question::execute
     */
    public function test_capabilities(): void {
        global $DB, $USER;
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $course = $this->getDataGenerator()->create_course();
        $cat = $generator->create_question_category(['contextid' => \context_course::instance($course->id)->id]);
        $q = $generator->create_question('numerical', null,
        ['name' => 'Example numerical question', 'category' => $cat->id]);

        // Set the required capabilities - webservice access and export rights on course
        $context = context_course::instance($course->id);
        $this->assignUserCapability('qbank/gitsync:exportquestions', $context->id);
        $managerroleid = $DB->get_field('role', 'id', array('shortname' => 'manager'));
        role_assign($managerroleid, $USER->id, $context->id);
        $returnvalue = export_question::execute($q->id, 50, $course->fullname, null);

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
     * Test the execute function fails when capabilities are missing.
     *
     * @covers \mod_fruit\external\get_fruit::execute
     */
    public function test_capabilities_missing(): void {
        global $DB, $USER;
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $course = $this->getDataGenerator()->create_course();
        $cat = $generator->create_question_category(['contextid' => \context_course::instance($course->id)->id]);
        $q = $generator->create_question('numerical', null,
            ['name' => 'Example numerical question', 'category' => $cat->id]);

        // Check when no access to the webservice
        $this->expectException(require_login_exception::class);
        $this->expectExceptionMessage('not logged in');
        export_question::execute($q->id, 50, $course->fullname, null);

        // Check when user has no access to export from course
        $context = context_course::instance($course->id);
        $this->assignUserCapability('qbank/gitsync:exportquestions', $context->id);
        $this->expectException(require_login_exception::class);
        $this->expectExceptionMessage('Not enrolled');
        export_question::execute($q->id, 50, $course->fullname, null);
    }
}