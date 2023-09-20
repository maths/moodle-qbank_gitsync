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

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot. '/question/bank/gitsync/lib.php');

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
            'contextlevel' => new external_value(PARAM_TEXT, 'Context level: 10, 40, 50, 70'),
            'coursename' => new external_value(PARAM_TEXT, 'Unique course name'),
            'modulename' => new external_value(PARAM_TEXT, 'Unique (within course) module name'),
            'coursecategory' => new external_value(PARAM_TEXT, 'Unique course category name'),
        ]);
    }

    /**
     * Returns description of webservice function output.
     * @return external_multiple_structure
     */
    public static function execute_returns():external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'questionbankentryid' => new external_value(PARAM_SEQUENCE, 'questionbankentry id'),
                'name' => new external_value(PARAM_TEXT, 'question name'),
                'questioncategory' => new external_value(PARAM_TEXT, 'question category'),
            ])
        );
    }

    /**
     * Get a list of questions for a given context for a given question category and below.
     *
     * @param string|null $qcategoryname category to search in form top/$category/$subcat1/$subcat2
     * @param int $contextlevel Moodle code for context level e.g. 10 for system
     * @param string|null $coursename Unique course name (optional unless course or module context level)
     * @param string|null $modulename Unique (within course) module name (optional unless module context level)
     * @param string|null $coursecategory course category name (optional unless course catgeory context level)
     * @return array of question data
     */
    public static function execute(?string $qcategoryname,
                                    int $contextlevel, ?string $coursename = null, ?string $modulename = null,
                                    ?string $coursecategory = null):array {
        global $CFG, $DB;
        $params = self::validate_parameters(self::execute_parameters(), [
            'qcategoryname' => $qcategoryname,
            'contextlevel' => $contextlevel,
            'coursename' => $coursename,
            'modulename' => $modulename,
            'coursecategory' => $coursecategory
        ]);
        $thiscontext = get_context($params['contextlevel'], $params['coursecategory'],
                                   $params['coursename'], $params['modulename']);

        // The webservice user needs to have access to the context. They could be given Manager
        // role at site level to access everything or access could be restricted to certain courses.
        self::validate_context($thiscontext);
        require_capability('qbank/gitsync:listquestions', $thiscontext);

        // Category name should be in form top/$category/$subcat1/$subcat2 and
        // have been gleaned directly from the directory structure.
        // Find the 'top' category for the context ($parent==0) and
        // then descend through the hierarchy until we find the category we need.
        $catnames = split_category_path($params['qcategoryname']);
        $parent = 0;
        foreach ($catnames as $catname) {
            $category = $DB->get_record('question_categories', ['name' => $catname,
                                        'contextid' => $thiscontext->id, 'parent' => $parent], 'id, parent, name');
            $parent = $category->id;
        }

        $categoriestosearch = array_merge([$category], self::get_category_descendants($category->id));

        $categoryids = array_map(fn($catinfo) => $catinfo->id, $categoriestosearch);

        $qbentries = $DB->get_records_list('question_bank_entries', 'questioncategoryid', $categoryids);
        $categories = array_column($categoriestosearch, null, 'id');
        $response = [];
        foreach ($qbentries as $qbe) {
            $mindata = get_minimal_question_data($qbe->id);
            $qinfo = new \stdClass();
            $qinfo->questionbankentryid = $qbe->id;
            $qinfo->name = $mindata->name;
            $qinfo->questioncategory = $categories[$qbe->questioncategoryid]->name;
            array_push($response, $qinfo);
        }

        return $response;
    }

    /**
     * Recursive function to return the ids of all the question categories below a given category.
     *
     * @param int $parentid ID of the category to search below
     * @return array of question categories
     */
    public static function get_category_descendants(int $parentid):array {
        global $DB;
        $children = $DB->get_records('question_categories', ['parent' => $parentid], null, 'id, parent, name');
        // Copy array.
        $descendants = array_merge([], $children);
        foreach ($children as $child) {
            $childdescendants = self::get_category_descendants($child->id);
            $descendants = array_merge($descendants, $childdescendants);
        }
        return $descendants;
    }
}