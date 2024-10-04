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
 * Export details of a quiz content and structure.
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
 * A webservice function to export details of a quiz content and structure.
 */
class export_quiz_data extends external_api {
    /**
     * Returns description of webservice function parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'moduleid' => new external_value(PARAM_SEQUENCE, 'Course module id'),
            'quizname' => new external_value(PARAM_TEXT, 'Quiz name'),
        ]);
    }

    /**
     * Returns description of webservice function output.
     * @return external_multiple_structure
     */
    public static function execute_returns():external_single_structure {
        return new external_single_structure([
            'quiz' => new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'context level description'),
                'intro' => new external_value(PARAM_RAW, 'course category name (course category context)'),
                'introformat' => new external_value(PARAM_SEQUENCE, 'id of course category, course or module'),
            ]),
            'sections' => new external_multiple_structure(
                new external_single_structure([
                    'firstslot' => new external_value(PARAM_SEQUENCE, 'first slot of section'),
                    'heading' => new external_value(PARAM_TEXT, 'heading'),
                    'shufflequestions' => new external_value(PARAM_BOOL, 'shuffle questions?'),
                ])
            ),
            'questions' => new external_multiple_structure(
                new external_single_structure([
                    'questionbankentryid' => new external_value(PARAM_SEQUENCE, 'questionbankentry id'),
                    'slot' => new external_value(PARAM_SEQUENCE, 'slot number'),
                    'page' => new external_value(PARAM_SEQUENCE, 'page number'),
                    'requireprevious' => new external_value(PARAM_BOOL, 'Require completion of previous question'),
                    'maxmark' => new external_value(PARAM_TEXT, 'maximum mark'),
                ])
            ),
        ]);
    }

    /**
     * Export details of a quiz content and structure.
     *
     * @param string|null $moduleid course module id of the quiz to export
     * @param string|null $quizname Name of the quiz
     * @return object containing quiz data
     */
    public static function execute(?string $moduleid = null, ?string $quizname = null):object {
        global $CFG, $DB;
        $params = self::validate_parameters(self::execute_parameters(), [
            'moduleid' => $moduleid,
            'quizname' => $quizname,
        ]);
        $contextinfo = get_context(\CONTEXT_MODULE, null, null, $params['quizname'], $params['moduleid']);

        $thiscontext = $contextinfo->context;

        // The webservice user needs to have access to the context. They could be given Manager
        // role at site level to access everything or access could be restricted to certain courses.
        self::validate_context($thiscontext);
        require_capability('qbank/gitsync:listquestions', $thiscontext);

        $response = new \stdClass();
        $response->quiz = new \stdClass();
        $response->sections = [];
        $response->questions = [];
        $instanceid = (int) $contextinfo->moduleid;

        $quiz = $DB->get_record('quiz', ['id' => $instanceid], 'intro, introformat', $strictness = MUST_EXIST);
        $response->quiz->name = $contextinfo->modulename;
        $response->quiz->intro = $quiz->intro;
        $response->quiz->introformat = $quiz->introformat;

        $response->sections = $DB->get_records('quiz_sections', ['moduleid' => $instanceid], null, 'firstslot, heading, shufflequestions');
        $response->questions = $DB->get_records_sql("
        SELECT qr.questionbankentryid, qs.slot, qs.page, qs.requireprevious, qs.maxmark
            FROM {quiz_slots} qs
            JOIN {question_references} qr ON qr.itemid = qs.id
            WHERE qr.usingcontextid = :contextid
            AND qr.questionarea = 'slot'",
        ['contextid' => $contextinfo->context->id]);

        return $response;
    }
}
