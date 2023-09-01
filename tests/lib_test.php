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
                    '<span lang="sv" class="multilang">Matematiskt (svenska)</span>'
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
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $category = core_course_category::create(array('name' => 'Cat1'));
        $module = $this->getDataGenerator()->create_module('quiz', array('course' => $course->id, 'name' => QUIZ_TEST));

        // System.
        $context = get_context(10, $category->name, $course->fullname, QUIZ_TEST);
        $this->assertEquals($context, context_system::instance());
        // Course category.
        $context = get_context(40, $category->name, $course->fullname, QUIZ_TEST);
        $this->assertEquals($context, context_coursecat::instance($category->id));
        // Course.
        $context = get_context(50, $category->name, $course->fullname, QUIZ_TEST);
        $this->assertEquals($context, context_course::instance($course->id));
        // Module.
        $context = get_context(70, $category->name, $course->fullname, QUIZ_TEST);
        $this->assertEquals($context, context_module::instance($module->cmid));
    }
}
