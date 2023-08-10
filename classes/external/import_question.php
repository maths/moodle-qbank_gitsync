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
 * Import a single question with accompanying metadata via webservice.
 *
 * @package   qbank_gitsync
 * @copyright 2023 The University of Edinburgh
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_gitsync\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot. '/question/bank/gitsync/lib.php');

use context_course;
use context_coursecat;
use context_module;
use context_system;
use Exception;
use external_api;
use external_function_parameters;
use external_value;
use qformat_xml;
use question_edit_contexts;

/**
 * A webservice function to import a single question with metadata.
 */
class import_question extends external_api {
    /**
     * Returns description of webservice function parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'questionid' => new external_value(PARAM_SEQUENCE, 'Moodle question id if it exists'),
            'categoryname' => new external_value(PARAM_TEXT, 'Category of question'),
            'filepath' => new external_value(PARAM_PATH, 'Local path for file for upload'),
            'contextlevel' => new external_value(PARAM_TEXT, 'Context level: 10, 40, 50, 70'),
            'contextidentifier1' => new external_value(PARAM_TEXT, 'Unique course or category name'),
            'contentidentifier2' => new external_value(PARAM_TEXT, 'Unique (within course) module name'),
                ]
            );
    }

    /**
     * Returns description of webservice function output.
     * @return external_value
     */
    public static function execute_returns() {
        return new external_value(PARAM_SEQUENCE, 'question id');
    }

    /**
     * Import a question from XML file
     * Initially just create a new one in Moodle DB. Will need to expand to
     * use importasversion if question already exists.
     * @param string $questionid question id
     * @param string $categoryname category of the question
     * @param string $filepath local file path (including filename) for file to be imported
     * @param int $contextlevel Moodle code for context level e.g. 10 for system
     * @param string $contextidentifier1 Unique course or category name (optional depending on context)
     * @param string $contextidentifier2 Unique (within course) module name (optional depending on context)
     */
    public static function execute($questionid, $categoryname, $filepath,
                                    $contextlevel, $contextidentifier1 = null, $contextidentifier2 = null) {
        global $CFG, $DB, $USER;
        switch ($contextlevel) {
            case \CONTEXT_SYSTEM:
                $thiscontext = context_system::instance();
                break;
            case \CONTEXT_COURSECAT:
                $coursecatid = $DB->get_field('course_categories', 'id', ['name' => $contextidentifier1], $strictness = MUST_EXIST);
                $thiscontext = context_coursecat::instance($coursecatid);
                break;
            case \CONTEXT_COURSE:
                $courseid = $DB->get_field('course', 'id', ['fullname' => $contextidentifier1], $strictness = MUST_EXIST);
                $thiscontext = context_course::instance($courseid);
                break;
            case \CONTEXT_MODULE:
                // Assuming here that the module is a quiz.
                $cmid = $DB->get_field_sql("
                       SELECT cm.id
                         FROM {course_modules} cm
                    LEFT JOIN {quiz} q ON q.course = cm.course AND q.id = cm.instance
                    LEFT JOIN {course} c ON c.id = cm.course
                        WHERE c.fullname = :coursename
                              AND q.name = :quizname
                              AND cm.module = 18",
                    ['coursename' => $contextidentifier1, 'quizname' => $contextidentifier2]);
                    $thiscontext = context_module::instance($cmid);
                break;
            default:
                throw new Exception('Invalid context level supplied.');
                return;
        }

        self::validate_context($thiscontext);
        $qformat = new qformat_xml();

        if ($categoryname) {
            // Category should be in form top/$category/$subcat1/$subcat2 and
            // have been gleaned directly from the directory structure.
            // Find the 'top' category for the context ($parent==0) and
            // then descend through the hierarchy until we find the category we need.
            $catnames = split_category_path($categoryname);
            $parent = 0;
            foreach ($catnames as $key => $catname) {
                $category = $DB->get_record('question_categories', ['name' => $catname,
                                            'contextid' => $thiscontext->id, 'parent' => $parent]);
                $parent = $category->id;
            }
            $qformat->setCategory($category);
            $qformat->setCatfromfile(false);
        } else {
            // No categoryname was supplied so we're dealing with a category question file.
            // We supply the base category for the context and let the import process
            // figure out if it needs to create a new category based on the info in the file.
            $category = $DB->get_record("question_categories", ['name' => 'top', 'contextid' => $thiscontext->id]);
            $qformat->setCategory($category);
            $qformat->setCatfromfile(true);
        }
        $qformat->setFilename($filepath);
        $qformat->set_display_progress(false);
        $qformat->setContextfromfile(false);
        $qformat->setStoponerror(true);
        $contexts = new question_edit_contexts($thiscontext);
        $qformat->setContexts($contexts->having_one_edit_tab_cap('import'));

        if (!$qformat->importpreprocess()) {
            throw new moodle_exception('importerror', 'gitsync', '', $filepath);
        }

        // Process the uploaded file.
        if (!$qformat->importprocess()) {
            throw new moodle_exception('importerror', 'gitsync', '', $filepath);
        }

        // In case anything needs to be done after.
        if (!$success = $qformat->importpostprocess()) {
            throw new moodle_exception('importerror', 'gitsync', '', $filepath);
        }

        $eventparams = [
            'contextid' => $qformat->category->contextid,
            'other' => ['format' => 'xml', 'categoryid' => $qformat->category->id],
        ];
        $event = \core\event\questions_imported::create($eventparams);
        $event->trigger();

        // Return id of new question ready to make manifest file.
        $questions = $DB->get_records('question', ['modifiedby' => $USER->id], 'id DESC', 'id', 0, 1);
        $question = reset($questions);
        return $question->id;
    }
}
