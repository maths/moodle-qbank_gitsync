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
 * Get a list of questionbankentryids for questions in a given context.
 *
 * @package   qbank_gitsync
 * @copyright 2023 The University of Edinburgh
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_gitsync\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/externallib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot. '/question/bank/gitsync/lib.php');

use core_question\local\bank\question_version_status;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

/**
 * A webservice function to import a single question with metadata.
 */
class get_question_list extends external_api {
    /**
     * Returns description of webservice function parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'qcategoryname' => new external_value(PARAM_TEXT, 'Category of questions in form top/$category/$subcat1/$subcat2'),
            'contextlevel' => new external_value(PARAM_SEQUENCE, 'Context level: 10, 40, 50, 70'),
            'coursename' => new external_value(PARAM_TEXT, 'Unique course name'),
            'modulename' => new external_value(PARAM_TEXT, 'Unique (within course) module name'),
            'coursecategory' => new external_value(PARAM_TEXT, 'Unique course category name'),
            'qcategoryid' => new external_value(PARAM_SEQUENCE, 'Question category id'),
            'instanceid' => new external_value(PARAM_SEQUENCE, 'Course, module or coursecategory id'),
            'contextonly' => new external_value(PARAM_BOOL, 'Only return context info?'),
            'qbankentryids' => new external_multiple_structure(
                new external_value(PARAM_SEQUENCE, 'Question bank entry id')
            ),
            'ignorecat' => new external_value(PARAM_TEXT, 'Regex of categories to ignore'),
        ]);
    }

    /**
     * Returns description of webservice function output.
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'contextinfo' => new external_single_structure([
                'contextlevel' => new external_value(PARAM_TEXT, 'context level description'),
                'categoryname' => new external_value(PARAM_TEXT, 'course category name (course category context)'),
                'coursename' => new external_value(PARAM_TEXT, 'course name (course or module context)'),
                'courseid' => new external_value(PARAM_SEQUENCE, 'course id (course or module context)'),
                'modulename' => new external_value(PARAM_TEXT, 'module name (module context)'),
                'instanceid' => new external_value(PARAM_SEQUENCE, 'id of course category, course or module'),
                'qcategoryname' => new external_value(PARAM_TEXT, 'name of question category'),
                'qcategoryid' => new external_value(PARAM_SEQUENCE, 'id of question category'),
                'ignorecat' => new external_value(PARAM_TEXT, 'regex of categories ignored'),
            ]),
            'questions' => new external_multiple_structure(
                new external_single_structure([
                    'questionbankentryid' => new external_value(PARAM_SEQUENCE, 'questionbankentry id'),
                    'name' => new external_value(PARAM_TEXT, 'question name'),
                    'questioncategory' => new external_value(PARAM_TEXT, 'question category'),
                    'version' => new external_value(PARAM_SEQUENCE, 'version'),
                ])
            ),
            'quizzes' => new external_multiple_structure(
                new external_single_structure([
                    'instanceid' => new external_value(PARAM_SEQUENCE, 'course module id of quiz'),
                    'name' => new external_value(PARAM_TEXT, 'name of quiz'),
                ])
            ),
        ]);
    }

    /**
     * Get a list of questions for a given context for a given question category and below.
     *
     * @param string|null $qcategoryname category to search in form top/$category/$subcat1/$subcat2
     * @param int $contextlevel Moodle code for context level e.g. 10 for system
     * @param string|null $coursename Unique course name (optional unless course or module context level)
     * @param string|null $modulename Unique (within course) module name (optional unless module context level)
     * @param string|null $coursecategory course category name (optional unless course catgeory context level)
     * @param string|null $qcategoryid ID of the question category to search (supercedes $qcategoryname)
     * @param string|null $instanceid ID of the relevant object for the given context level e.g. course id
     *  for course level) to search for questions (supercedes $coursename, $modulename & $coursecategory)
     * @param bool $contextonly Only return info on context and not questions
     * @param array|null $qbankentryids Array of qbankentryids to check
     * @param string|null $ignorecat Regex of categories to ignore (along with their descendants)
     * @return object containing context info and an array of question data
     */
    public static function execute(?string $qcategoryname,
                                    int $contextlevel, ?string $coursename = null, ?string $modulename = null,
                                    ?string $coursecategory = null, ?string $qcategoryid = null,
                                    ?string $instanceid = null, bool $contextonly = false,
                                    ?array $qbankentryids = [''], ?string $ignorecat = null): object {
        global $CFG, $DB;
        $params = self::validate_parameters(self::execute_parameters(), [
            'qcategoryname' => $qcategoryname,
            'contextlevel' => $contextlevel,
            'coursename' => $coursename,
            'modulename' => $modulename,
            'coursecategory' => $coursecategory,
            'qcategoryid' => $qcategoryid,
            'instanceid' => $instanceid,
            'contextonly' => $contextonly,
            'qbankentryids' => $qbankentryids,
            'ignorecat' => $ignorecat,
        ]);
        $contextinfo = get_context($params['contextlevel'], $params['coursecategory'],
                                   $params['coursename'], $params['modulename'],
                                   $params['instanceid']
                                );

        $thiscontext = $contextinfo->context;

        // The webservice user needs to have access to the context. They could be given Manager
        // role at site level to access everything or access could be restricted to certain courses.
        self::validate_context($thiscontext);
        require_capability('qbank/gitsync:listquestions', $thiscontext);

        $response = new \stdClass();
        $response->contextinfo = $contextinfo;
        unset($response->contextinfo->context);
        $response->questions = [];
        $response->quizzes = [];
        $response->contextinfo->qcategoryname = '';
        $response->contextinfo->qcategoryid = null;
        $response->contextinfo->ignorecat = $ignorecat;

        if (count($qbankentryids) === 1 && $qbankentryids[0] === '') {
            if (is_null($qcategoryid) || $qcategoryid === '') {
                // Category name should be in form top/$category/$subcat1/$subcat2 and
                // have been gleaned directly from the directory structure.
                // Find the 'top' category for the context ($parent==0) and
                // then descend through the hierarchy until we find the category we need.
                $catnames = split_category_path($params['qcategoryname']);
                $parent = 0;
                foreach ($catnames as $catname) {
                    $category = $DB->get_record('question_categories',
                                    ['name' => $catname, 'contextid' => $thiscontext->id, 'parent' => $parent],
                                    'id, parent, name');
                    $parent = $category->id;
                }
            } else {
                $category = $DB->get_record('question_categories', ['id' => $qcategoryid], 'id, parent, name, contextid');
                if (!$category) {
                    throw new \moodle_exception('categoryerror', 'qbank_gitsync', null, $params['qcategoryid']);
                }
                if (isset($category->contextid) && $category->contextid !== strval($thiscontext->id)) {
                    throw new \moodle_exception('categorymismatcherror', 'qbank_gitsync', null, $params['qcategoryid']);
                }
            }

            if (!$category) {
                if ($params['qcategoryname'] === 'top') {
                    throw new \moodle_exception('categoryerrornew', 'qbank_gitsync', null, $params['qcategoryname']);
                } else {
                    throw new \moodle_exception('categoryerror', 'qbank_gitsync', null, $params['qcategoryname']);
                }
            }

            $response->contextinfo->qcategoryname = self::get_category_path($category);
            $response->contextinfo->qcategoryid = $category->id;

            if ((int) $params['contextlevel'] === \CONTEXT_COURSE) {
                $response->quizzes = $DB->get_records_sql(
                    "SELECT cm.id as instanceid, q.name
                        FROM {course_modules} cm
                        INNER JOIN {quiz} q ON q.id = cm.instance
                        INNER JOIN {modules} m ON m.id = cm.module
                    WHERE cm.course = :courseid
                        AND m.name = 'quiz'
                        AND cm.deletioninprogress = 0",
                    ['courseid' => (int) $contextinfo->instanceid]);
            }

            if ($contextonly) {
                return $response;
            }

            $categoriestosearch = array_merge([$category], self::get_category_descendants($category->id, $params['ignorecat']));

            $categoryids = array_map(fn($catinfo) => $catinfo->id, $categoriestosearch);

            $qbentries = $DB->get_records_list('question_bank_entries', 'questioncategoryid', $categoryids);
            $categories = array_column($categoriestosearch, null, 'id');
            foreach ($qbentries as $qbe) {
                $mindata = get_minimal_question_data($qbe->id);
                if ($mindata->status !== question_version_status::QUESTION_STATUS_HIDDEN) {
                    $qinfo = new \stdClass();
                    $qinfo->questionbankentryid = $qbe->id;
                    $qinfo->name = $mindata->name;
                    $qinfo->questioncategory = $categories[$qbe->questioncategoryid]->name;
                    $qinfo->version = $mindata->version;
                    array_push($response->questions, $qinfo);
                }
            }
        } else {
            // Deal with list of qbankids passed in to check.
            $extraqbentries = $DB->get_records_list('question_bank_entries', 'id', $qbankentryids);
            foreach ($extraqbentries as $extraqbe) {
                $mindata = get_minimal_question_data($extraqbe->id);
                $qinfo = new \stdClass();
                $qinfo->questionbankentryid = $extraqbe->id;
                // These questions could be outside the context we've checked for permissions
                // so we only return very basic info. The scenario we're dealing with is checking
                // question existence in Moodle or version number if question has been moved
                // to a new context. Import/export themselves will check context of actual question.
                $qinfo->name = null;
                $qinfo->questioncategory = null;
                $qinfo->version = $mindata->version;
                array_push($response->questions, $qinfo);
            }
        }

        return $response;
    }

    /**
     * Recursive function to return the ids of all the question categories below a given category.
     *
     * @param int $parentid ID of the category to search below
     * @param string|null $ignorecat Regex of categories to ignore (along with their descendants)
     * @return array of question categories
     */
    public static function get_category_descendants(int $parentid, ?string $ignorecat): array {
        global $DB;
        $children = $DB->get_records('question_categories', ['parent' => $parentid], null, 'id, parent, name');
        if ($ignorecat) {
            $children = array_filter($children, fn($ch) => !preg_match($ignorecat, $ch->name));
        }
        // Copy array.
        $descendants = array_merge([], $children);
        foreach ($children as $child) {
            $childdescendants = self::get_category_descendants($child->id, $ignorecat);
            $descendants = array_merge($descendants, $childdescendants);
        }
        return $descendants;
    }

    /**
     * get the category as a path (e.g., tom/dick/harry)
     * @param object $category question category
     * @return string the path
     */
    public static function get_category_path($category) {
        global $DB;

        $pathsections = [];
        do {
            $pathsections[] = $category->name;
            $id = $category->parent;
        } while ($category = $DB->get_record('question_categories', ['id' => $id]));

        $names = array_reverse($pathsections);

        foreach ($names as $name) {
            $escapedname = str_replace('/', '//', $name);
            if (substr($escapedname, 0, 1) == '/') {
                $escapedname = ' ' . $escapedname;
            }
            if (substr($escapedname, -1) == '/') {
                $escapedname = $escapedname . ' ';
            }
            $escapednames[] = $escapedname;
        }
        return implode('/', $escapednames);
    }
}
