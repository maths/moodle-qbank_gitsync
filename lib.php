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
 * Function library for gitsync plugin
 *
 * @package   qbank_gitsync
 * @copyright 2023 The University of Edinburgh
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Convert a string into an array of category names.
 *
 * Each category name is cleaned by a call to clean_param(, PARAM_TEXT),
 * which matches the cleaning in question/bank/managecategories/category_form.php.
 *
 * @param string|null $path
 * @return array of category names.
 */
function split_category_path(?string $path): array {
    $rawnames = preg_split('~(?<!/)/(?!/)~', $path);
    $names = array();
    foreach ($rawnames as $rawname) {
        $names[] = clean_param(trim(str_replace('//', '/', $rawname)), PARAM_TEXT);
    }
    return $names;
}
/**
 * Return the correct context given a valid selection of identifying information
 *
 * Required parameters are dependent on the supplied context level.
 * System: nothing.
 * Course category: category name.
 * Course: course name.
 * Module: course name and module name.
 *
 * @param int $contextlevel
 * @param string|null $categoryname
 * @param string|null $coursename
 * @param string|null $modulename
 * @return context
 */
function get_context(int $contextlevel, ?string $categoryname = null,
                    ?string $coursename = null, ?string $modulename = null):context {
    global $DB;
    switch ($contextlevel) {
        case \CONTEXT_SYSTEM:
            return context_system::instance();
        case \CONTEXT_COURSECAT:
            $coursecatid = $DB->get_field('course_categories', 'id', ['name' => $categoryname], $strictness = MUST_EXIST);
            return context_coursecat::instance($coursecatid);
        case \CONTEXT_COURSE:
            $courseid = $DB->get_field('course', 'id', ['fullname' => $coursename], $strictness = MUST_EXIST);
            return context_course::instance($courseid);
        case \CONTEXT_MODULE:
            // Assuming here that the module is a quiz.
            $cmid = $DB->get_field_sql("
                   SELECT cm.id
                     FROM {course_modules} cm
                LEFT JOIN {quiz} q ON q.course = cm.course AND q.id = cm.instance
                LEFT JOIN {course} c ON c.id = cm.course
                LEFT JOIN {modules} m ON m.id = cm.module
                    WHERE c.fullname = :coursename
                            AND q.name = :quizname
                            AND m.name = 'quiz'",
                ['coursename' => $coursename, 'quizname' => $modulename], $strictness = MUST_EXIST);
                return context_module::instance($cmid);
            break;
        default:
            throw new Exception('Invalid context level supplied.');
    }
}
