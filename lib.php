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
 * If the id of the category, course or module is known this can be supplied instead.
 *
 * @param int $contextlevel
 * @param string|null $categoryname
 * @param string|null $coursename
 * @param string|null $modulename
 * @param int|null $instanceid
 * @return context
 */
function get_context(int $contextlevel, ?string $categoryname = null,
                    ?string $coursename = null, ?string $modulename = null, ?int $instanceid = null):context {
    global $DB;
    switch ($contextlevel) {
        case \CONTEXT_SYSTEM:
            return context_system::instance();
        case \CONTEXT_COURSECAT:
            if (is_null($instanceid)) {
                $instanceid = $DB->get_field('course_categories', 'id', ['name' => $categoryname], $strictness = MUST_EXIST);
            }
            return context_coursecat::instance($instanceid);
        case \CONTEXT_COURSE:
            if (is_null($instanceid)) {
                $instanceid = $DB->get_field('course', 'id', ['fullname' => $coursename], $strictness = MUST_EXIST);
            }
            return context_course::instance($instanceid);
        case \CONTEXT_MODULE:
            if (is_null($instanceid)) {
                // Assuming here that the module is a quiz.
                $instanceid = $DB->get_field_sql("
                    SELECT cm.id
                        FROM {course_modules} cm
                        JOIN {quiz} q ON q.course = cm.course AND q.id = cm.instance
                        JOIN {course} c ON c.id = cm.course
                        JOIN {modules} m ON m.id = cm.module
                        WHERE c.fullname = :coursename
                                AND q.name = :quizname
                                AND m.name = 'quiz'",
                    ['coursename' => $coursename, 'quizname' => $modulename], $strictness = MUST_EXIST);
            }
                return context_module::instance($instanceid);
            break;
        default:
            throw new Exception('Invalid context level supplied.');
    }
}

/**
 * Return information on the latest version of a question given its questionbankentryid.
 *
 * @param string $questionbankentryid
 * @return stdClass Contains properties of question such as version and context
 */
function get_question_data(string $questionbankentryid):stdClass {
    global $DB;
    $questiondata = $DB->get_record_sql("
    SELECT qc.contextid as contextid, c.contextlevel as contextlevel,
            q.id as questionid, c.instanceid as instanceid,
            qc.id as categoryid, qv.version as version
        FROM {question_categories} qc
        JOIN {question_bank_entries} qbe ON qc.id = qbe.questioncategoryid
        JOIN {question_versions} qv ON qbe.id = qv.questionbankentryid
        JOIN {question} q ON qv.questionid = q.id
        JOIN {context} c on qc.contextid = c.id
        WHERE qbe.id = :questionbankentryid1
        AND qv.version = (SELECT MAX(version) FROM {question_versions} WHERE questionbankentryid = :questionbankentryid2)",
    ['questionbankentryid1' => $questionbankentryid, 'questionbankentryid2' => $questionbankentryid],
    MUST_EXIST);

    return $questiondata;
}

/**
 * Return information on the latest version of a question given its questionbankentryid.
 *
 * @param string $questionbankentryid
 * @return stdClass Contains properties of question such as version and context
 */
function get_minimal_question_data(string $questionbankentryid):stdClass {
    global $DB;
    $questiondata = $DB->get_record_sql("
    SELECT q.id as questionid, q.name as name, qv.version as version
        FROM {question} q
        JOIN {question_versions} qv ON qv.questionid = q.id
        JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
        WHERE qbe.id = :questionbankentryid1
        AND qv.version = (SELECT MAX(version) FROM {question_versions} WHERE questionbankentryid = :questionbankentryid2)",
    ['questionbankentryid1' => $questionbankentryid, 'questionbankentryid2' => $questionbankentryid],
    MUST_EXIST);

    return $questiondata;
}

