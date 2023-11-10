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
 * Unit tests for function library
 *
 * @package    qbank_gitsync
 * @copyright  2023 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_gitsync;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot. '/question/bank/gitsync/lib.php');
use core_course_category;
use context_system;
use context_coursecat;
use context_course;
use context_module;

/**
 * Tests for library function in lib.php
 * @group qbank_gitsync
 */
class lib_test extends \advanced_testcase {
    /**
     * Test the category path is split correctly.
     * @covers \gitsync\lib.php\split_category_path()
     */
    public function test_split_category_path() {
        $path = '$course$/Tim\'s questions/Tricky things like // //// ' .
                'and so on/Category name ending in // / // and one that ' .
                'starts with one/<span lang="en" class="multilang">Matematically<//span> ' .
                '<span lang="sv" class="multilang">Matematiskt (svenska)<//span>';
        $this->assertEquals([
                    '$course$',
                    "Tim's questions",
                    "Tricky things like / // and so on",
                    'Category name ending in /',
                    '/ and one that starts with one',
                    '<span lang="en" class="multilang">Matematically</span> ' .
                    '<span lang="sv" class="multilang">Matematiskt (svenska)</span>',
                ], split_category_path($path));
    }

    /**
     * Test the category path is cleaned correctly.
     * @covers \gitsync\lib.php\split_category_path()
     */
    public function test_split_category_path_cleans() {
        $path = '<evil>Nasty <virus //> thing<//evil>';
        $this->assertEquals(['Nasty  thing'], split_category_path($path));
    }

    /**
     * Test the correct context is returned at each level
     * @covers \gitsync\lib.php\get_context()
     */
    public function test_get_context() {
        define('QUIZ_TEST', 'Quiz test');
        define('CAT_NAME', 'Cat1');
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $category = core_course_category::create(['name' => CAT_NAME]);
        $module = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id, 'name' => QUIZ_TEST]);

        // System.
        $context = get_context(10, $category->name, $course->fullname, QUIZ_TEST, null);
        $this->assertEquals($context->context, context_system::instance());
        $this->assertEquals($context->contextlevel, 'system');
        $this->assertEquals($context->categoryname, null);
        $this->assertEquals($context->coursename, null);
        $this->assertEquals($context->modulename, null);
        $this->assertEquals($context->instanceid, null);
        // Course category.
        $context = get_context(40, $category->name, $course->fullname, QUIZ_TEST, null);
        $this->assertEquals($context->context, context_coursecat::instance($category->id));
        $this->assertEquals($context->contextlevel, 'course category');
        $this->assertEquals($context->categoryname, CAT_NAME);
        $this->assertEquals($context->coursename, null);
        $this->assertEquals($context->modulename, null);
        $this->assertEquals($context->instanceid, $category->id);
        // Course.
        $context = get_context(50, $category->name, $course->fullname, QUIZ_TEST, null);
        $this->assertEquals($context->context, context_course::instance($course->id));
        $this->assertEquals($context->contextlevel, 'course');
        $this->assertEquals($context->categoryname, null);
        $this->assertEquals($context->coursename, $course->fullname);
        $this->assertEquals($context->modulename, null);
        $this->assertEquals($context->instanceid, $course->id);
        // Module.
        $context = get_context(70, $category->name, $course->fullname, QUIZ_TEST, null);
        $this->assertEquals($context->context, context_module::instance($module->cmid));
        $this->assertEquals($context->contextlevel, 'module');
        $this->assertEquals($context->categoryname, null);
        $this->assertEquals($context->coursename, $course->fullname);
        $this->assertEquals($context->modulename, QUIZ_TEST);
        $this->assertEquals($context->instanceid, $module->cmid);

        // Using stance id
        // Course category.
        $context = get_context(40, null, null, null, $category->id);
        $this->assertEquals($context->context, context_coursecat::instance($category->id));
        $this->assertEquals($context->contextlevel, 'course category');
        $this->assertEquals($context->categoryname, CAT_NAME);
        $this->assertEquals($context->coursename, null);
        $this->assertEquals($context->modulename, null);
        $this->assertEquals($context->instanceid, $category->id);
        // Course.
        $context = get_context(50, null, null, null, $course->id);
        $this->assertEquals($context->context, context_course::instance($course->id));
        $this->assertEquals($context->contextlevel, 'course');
        $this->assertEquals($context->categoryname, null);
        $this->assertEquals($context->coursename, $course->fullname);
        $this->assertEquals($context->modulename, null);
        $this->assertEquals($context->instanceid, $course->id);
        // Module.
        $context = get_context(70, null, null, null, $module->cmid);
        $this->assertEquals($context->context, context_module::instance($module->cmid));
        $this->assertEquals($context->contextlevel, 'module');
        $this->assertEquals($context->categoryname, null);
        $this->assertEquals($context->coursename, $course->fullname);
        $this->assertEquals($context->modulename, QUIZ_TEST);
        $this->assertEquals($context->instanceid, $module->cmid);
    }

    /**
     * Test question info retrieval
     * @covers \gitsync\lib.php\get_question_data()
     * @covers \gitsync\lib.php\get_minimal_question_data()
     */
    public function test_get_question_data() {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $course = $this->getDataGenerator()->create_course();
        $qcategory = $generator->create_question_category(
                            ['contextid' => \context_course::instance($course->id)->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->user = $user;
        $this->setUser($user);
        $q = $generator->create_question('shortanswer', null,
                            ['name' => 'This is the first version', 'category' => $qcategory->id]);
        $qbankentryid = $DB->get_field('question_versions', 'questionbankentryid',
                                                ['questionid' => $q->id], $strictness = MUST_EXIST);
        $generator->update_question($q, null, ['name' => 'This is the second version']);
        $v3 = $generator->update_question($q, null, ['name' => 'This is the third version']);

        $qdata = get_question_data($qbankentryid);
        $this->assertEquals(3, $qdata->version);
        $this->assertEquals($v3->id, $qdata->questionid);

        $qmindata = get_minimal_question_data($qbankentryid);
        $this->assertEquals(3, $qmindata->version);
        $this->assertEquals($v3->name, $qmindata->name);
        $this->assertEquals($v3->id, $qmindata->questionid);
    }
}
