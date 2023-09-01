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
            'questionbankentryid' => new external_value(PARAM_SEQUENCE, 'Moodle question id if it exists'),
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
     * @param string $questionbankentryid questionbankentry id
     * @return array question details
     */
    public static function execute(string $questionbankentryid):array {
        global $DB, $SITE;

        $questiondata = $DB->get_record_sql("
               SELECT qc.contextid as contextid, c.contextlevel as contextlevel,
                      MAX(q.id) as questionid, c.instanceid as instanceid,
                      qc.id as categoryid
                 FROM {question_categories} qc
                 JOIN {question_bank_entries} qbe ON qc.id = qbe.questioncategoryid
                 JOIN {question_versions} qv ON qbe.id = qv.questionbankentryid
                 JOIN {question} q ON qv.questionid = q.id
                 JOIN {context} c on qc.contextid = c.id
                WHERE qbe.id = :questionbankentryid",
            ['questionbankentryid' => $questionbankentryid],
            MUST_EXIST);

        switch ($questiondata->contextlevel) {
            case \CONTEXT_SYSTEM:
                $course = $DB->get_record('course', ['shortname' => $SITE->shortname], '*', $strictness = MUST_EXIST);
                break;
            case \CONTEXT_COURSECAT:
                $course = $DB->get_record('course', ['shortname' => $SITE->shortname], '*', $strictness = MUST_EXIST);
                break;
            case \CONTEXT_COURSE:
                $course = $DB->get_record('course', ['id' => $questiondata->instanceid], '*', $strictness = MUST_EXIST);
                break;
            case \CONTEXT_MODULE:
                $course = $DB->get_record_sql("
                    SELECT c.*
                      FROM mdl_course_modules cm
                      JOIN mdl_course c ON c.id = cm.course
                     WHERE cm.id = :moduleid",
                ['moduleid' => $questiondata->instanceid],
                MUST_EXIST);
                break;
            default:
                throw new moodle_exception(get_string('contexterror', 'qbank_gitsync', $questiondata->questionid));
        }
        $thiscontext = get_context($questiondata->contextlevel, null, null, null, $questiondata->instanceid);
        self::validate_context($thiscontext);
        $question = question_bank::load_question_data($questiondata->questionid);
        $qformat = new qformat_xml();
        $qformat->setQuestions([$question]);
        $qformat->setCourse($course);
        $contexts = new question_edit_contexts($thiscontext);
        // Checks user has export permission for the supplied context.
        $qformat->setContexts($contexts->having_one_edit_tab_cap('export'));
        $qformat->setCattofile(false);
        $qformat->setContexttofile(false);
        if (!$qformat->exportpreprocess()) {
            throw new moodle_exception(get_string('exporterror', 'qbank_gitsync', $questiondata->questionid));
        }
        if (!$question = $qformat->exportprocess(true)) {
            throw new moodle_exception(get_string('exporterror', 'qbank_gitsync', $questiondata->questionid));
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
