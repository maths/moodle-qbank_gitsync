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
 * Export a single question with accompanying metadata via webservice
 *
 * @package   qbank_gitsync
 * @copyright 2023 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_gitsync\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot. '/question/bank/gitsync/lib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use moodle_exception;
use qformat_xml;
use question_bank;
use core_question\local\bank\question_edit_contexts;

/**
 * A webservice function to export a single question with metadata.
 */
class export_question extends external_api {

    /**
     * Returns description of webservice function parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionid' => new external_value(PARAM_SEQUENCE, 'Moodle question id if it exists'),
            'contextlevel' => new external_value(PARAM_SEQUENCE, 'Context level: 10, 40, 50, 70'),
            'coursename' => new external_value(PARAM_TEXT, 'Unique course or category name'),
            'modulename' => new external_value(PARAM_TEXT, 'Unique (within course) module name'),
            'coursecategory' => new external_value(PARAM_TEXT, 'Unique course category name'),
        ]);
    }

    /**
     * Returns description of webservice function output.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'question' => new external_value(PARAM_RAW, 'question'),
        ]);
    }

    /**
     * Exports a single question as XML.
     * Will need to add metadata and be packaged properly.
     *
     * @param string $questionid question id
     * @param int $contextlevel Moodle code for context level e.g. 10 for system
     * @param string $coursename Unique course name (required whatever the context)
     * @param string|null $modulename Unique (within course) module name (required for module context)
     * @param string|null $coursecategory course category name (optional unless course catgeory context level)
     * @return array question details
     */
    public static function execute(string $questionid, int $contextlevel,
                                   string $coursename, ?string $modulename = null,
                                   ?string $coursecategory = null):array {
        global $DB;
        $course = $DB->get_record('course', ['fullname' => $coursename], '*', $strictness = MUST_EXIST);
        $thiscontext = get_context($contextlevel, $coursecategory, $coursename, $modulename);
        self::validate_context($thiscontext);
        $questiondata = question_bank::load_question_data($questionid);
        $qformat = new qformat_xml();
        $qformat->setQuestions([$questiondata]);
        $qformat->setCourse($course);
        $contexts = new question_edit_contexts($thiscontext);
        // Checks user has export permission for the supplied context.
        $qformat->setContexts($contexts->having_one_edit_tab_cap('export'));
        // Check question is available in the supplied context.
        // This may be overly complicated. In most cases always using the course context level
        // and supplying course name would be sufficient. Course categories would be an issue, though -
        // they use the system 'course' and so a user can have permission on the category and not
        // the course.
        $questiondata = $DB->get_record_sql("
               SELECT c.path as contextpath, qc.id as categoryid
                 FROM {question} q
            LEFT JOIN {question_versions} qv ON qv.questionid = q.id
            LEFT JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
            LEFT JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
            LEFT JOIN {context} c ON c.id = qc.contextid
                WHERE q.id = :questionid",
            ['questionid' => $questionid],
            MUST_EXIST);
        $contextids = explode('/', $questiondata->contextpath);
        $contextmatched = false;
        foreach ($contextids as $currentcontextid) {
            if ($currentcontextid === strval($thiscontext->id)) {
                $contextmatched = true;
                break;
            }
        }
        if (!$contextmatched) {
            throw new moodle_exception(get_string('contexterror', 'qbank_gitsync', $questionid));
        }
        $qformat->setCattofile(false);
        $qformat->setContexttofile(false);
        if (!$qformat->exportpreprocess()) {
            throw new moodle_exception(get_string('exporterror', 'qbank_gitsync', $questionid));
        }
        if (!$question = $qformat->exportprocess(true)) {
            throw new moodle_exception(get_string('exporterror', 'qbank_gitsync', $questionid));
        }

        // Log the export of this question.
        $eventparams = [
            'contextid' => $thiscontext->id,
            'other' => ['format' => 'xml', 'categoryid' => $questiondata->categoryid],
        ];
        $event = \core\event\questions_exported::create($eventparams);
        $event->trigger();

        $response = [
            'question' => $question,
        ];

        return $response;
    }
}
