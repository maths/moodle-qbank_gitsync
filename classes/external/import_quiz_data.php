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
 * Import details of a quiz content and structure.
 *
 * @package   qbank_gitsync
 * @copyright 2024 The University of Edinburgh
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_gitsync\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/course/lib.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/lib/externallib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot. '/question/bank/gitsync/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/course/modlib.php');

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use mod_quiz\grade_calculator;
use mod_quiz\quiz_settings;

/**
 * A webservice function to export details of a quiz content and structure.
 */
class import_quiz_data extends external_api {
    /**
     * Returns description of webservice function parameters
     * @return external_single_structure
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'quiz' => new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'context level description'),
                'intro' => new external_value(PARAM_RAW, 'course category name (course category context)'),
                'introformat' => new external_value(PARAM_SEQUENCE, 'id of course category, course or module'),
                'coursename' => new external_value(PARAM_TEXT, 'course to import quiz into'),
                'courseid' => new external_value(PARAM_SEQUENCE, 'course to import quiz into'),
                'questionsperpage' => new external_value(PARAM_SEQUENCE, 'default questions per page'),
                'grade' => new external_value(PARAM_TEXT, 'maximum grade'),
                'navmethod' => new external_value(PARAM_TEXT, 'navigation method'),
                'cmid' => new external_value(PARAM_TEXT, 'id of quiz if it already exists'),
            ]),
            'sections' => new external_multiple_structure(
                new external_single_structure([
                    'firstslot' => new external_value(PARAM_SEQUENCE, 'first slot of section'),
                    'heading' => new external_value(PARAM_TEXT, 'heading'),
                    'shufflequestions' => new external_value(PARAM_INT, 'shuffle questions?'),
                ])
            ),
            'questions' => new external_multiple_structure(
                new external_single_structure([
                    'questionbankentryid' => new external_value(PARAM_SEQUENCE, 'questionbankentry id'),
                    'slot' => new external_value(PARAM_SEQUENCE, 'slot number'),
                    'page' => new external_value(PARAM_SEQUENCE, 'page number'),
                    'requireprevious' => new external_value(PARAM_INT, 'Require completion of previous question?'),
                    'maxmark' => new external_value(PARAM_TEXT, 'maximum mark'),
                ]), '', VALUE_DEFAULT, []
            ),
            'feedback' => new external_multiple_structure(
                new external_single_structure([
                    'feedbacktext' => new external_value(PARAM_TEXT, 'Feedback text', VALUE_OPTIONAL),
                    'feedbacktextformat' => new external_value(PARAM_SEQUENCE, 'Format of feedback', VALUE_OPTIONAL),
                    'mingrade' => new external_value(PARAM_TEXT, 'minimum mark', VALUE_OPTIONAL),
                    'maxgrade' => new external_value(PARAM_TEXT, 'maximum mark', VALUE_OPTIONAL),
                ]), '', VALUE_DEFAULT, []
            ),
        ]);
    }

    /**
     * Returns description of webservice function output.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Import success?'),
            'cmid' => new external_value(PARAM_SEQUENCE, 'CMID of quiz'),
        ]);
    }

    /**
     * Import details of a quiz content and structure.
     *
     * @param array $quiz
     * @param array $sections
     * @param array $questions
     * @param array|null $feedback
     * @return object containing outcome
     */
    public static function execute(array $quiz, array $sections, array $questions, ?array $feedback = []): object {
        global $CFG, $DB;
        $params = self::validate_parameters(self::execute_parameters(), [
            'quiz' => $quiz,
            'sections' => $sections,
            'questions' => $questions,
            'feedback' => $feedback,
        ]);
        $contextinfo = get_context(\CONTEXT_COURSE, null, $params['quiz']['coursename'], null, $params['quiz']['courseid']);

        $thiscontext = $contextinfo->context;

        // The webservice user needs to have access to the context. They could be given Manager
        // role at site level to access everything or access could be restricted to certain courses.
        self::validate_context($thiscontext);
        require_capability('qbank/gitsync:importquestions', $thiscontext);

        $moduleinfo = new \stdClass();
        $moduleinfo->name = $params['quiz']['name'];
        $moduleinfo->modulename = 'quiz';
        $moduleinfo->module = $DB->get_field('modules', 'id', ['name' => 'quiz']);
        $moduleinfo->course = $contextinfo->instanceid;
        $moduleinfo->section = 1;
        $moduleinfo->quizpassword = '';
        $moduleinfo->visible = true;
        $moduleinfo->introeditor = [
                                    'text' => $params['quiz']['intro'],
                                    'format' => (int) $params['quiz']['introformat'],
                                    'itemid' => 0,
                                    ];
        $moduleinfo->preferredbehaviour = 'deferredfeedback';
        $moduleinfo->grade = $params['quiz']['grade'];
        $moduleinfo->questionsperpage = (int) $params['quiz']['questionsperpage'];
        $moduleinfo->shuffleanswers = true;
        $moduleinfo->navmethod = $params['quiz']['navmethod'];
        $moduleinfo->timeopen = 0;
        $moduleinfo->timeclose = 0;
        $moduleinfo->decimalpoints = 2;
        $moduleinfo->questiondecimalpoints = -1;
        $moduleinfo->grademethod = 1;
        $moduleinfo->graceperiod = 0;
        $moduleinfo->timelimit = 0;
        if ($params['quiz']['cmid']) {
            $moduleinfo->coursemodule = (int) $params['quiz']['cmid'];
            $moduleinfo->cmidnumber = $moduleinfo->coursemodule;
            $module = get_coursemodule_from_id('', $moduleinfo->coursemodule, 0, false, \MUST_EXIST);
            list($module, $moduleinfo) = \update_moduleinfo($module, $moduleinfo, \get_course($contextinfo->instanceid));
            $module = get_module_from_cmid($moduleinfo->coursemodule)[0];
        } else {
            $moduleinfo->cmidnumber = '';
            $moduleinfo = \add_moduleinfo($moduleinfo, \get_course($contextinfo->instanceid));

            $module = get_module_from_cmid($moduleinfo->coursemodule)[0];
        }

        // Post-creation updates.
        $reviewchoice = [];
        $reviewchoice['reviewattempt'] = 69888;
        $reviewchoice['reviewcorrectness'] = 4352;
        $reviewchoice['reviewmarks'] = 4352;
        $reviewchoice['reviewspecificfeedback'] = 4352;
        $reviewchoice['reviewgeneralfeedback'] = 4352;
        $reviewchoice['reviewrightanswer'] = 4352;
        $reviewchoice['reviewoverallfeedback'] = 4352;
        $reviewchoice['id'] = $moduleinfo->instance;
        $DB->update_record('quiz', $reviewchoice);

        // Sort questions by slot.
        usort($params['questions'], function($a, $b) {
            if ((int) $a['slot'] > (int) $b['slot']) {
                return 1;
            } else if ((int) $a['slot'] < (int) $b['slot']) {
                return -1;
            } else {
                return 0;
            }
        });
        if ($params['quiz']['cmid']) {
            // We can only add questions if the quiz already exists.
            foreach ($params['questions'] as $question) {
                $qdata = get_minimal_question_data($question['questionbankentryid']);
                // Double-check user has question access.
                quiz_require_question_use($qdata->questionid);
                quiz_add_quiz_question($qdata->questionid, $module, (int) $question['page'], (float) $question['maxmark']);
                if ($question['requireprevious']) {
                    $quizcontext = get_context(\CONTEXT_MODULE, null, null, null, $moduleinfo->coursemodule);
                    $itemid = $DB->get_field('question_references', 'itemid',
                        ['usingcontextid' => $quizcontext->context->id, 'questionbankentryid' => $question['questionbankentryid']]);
                    $DB->set_field('quiz_slots', 'requireprevious', 1, ['id' => $itemid]);
                }
            }
            if (class_exists('mod_quiz\grade_calculator')) {
                quiz_settings::create($moduleinfo->instance)->get_grade_calculator()->recompute_quiz_sumgrades();
            } else {
                quiz_update_sumgrades($module);
            }
            // NB Must add questions before updating sections.
            foreach ($params['sections'] as $section) {
                $section['quizid'] = $moduleinfo->instance;
                $section['firstslot'] = (int) $section['firstslot'];
                // First slot will have been automatically created so we need to overwrite.
                if ($section['firstslot'] == 1) {
                    $sectionid = $DB->get_field('quiz_sections', 'id',
                        ['quizid' => $moduleinfo->instance, 'firstslot' => 1]);
                    $section['id'] = $sectionid;
                    $DB->update_record('quiz_sections', $section);
                } else {
                    $sectionid = $DB->insert_record('quiz_sections', $section);
                }
                $slotid = $DB->get_field('quiz_slots', 'id',
                    ['quizid' => $moduleinfo->instance, 'slot' => (int) $section['firstslot']]);

                // Log section break created event.
                $event = \mod_quiz\event\section_break_created::create([
                    'context' => $thiscontext,
                    'objectid' => $sectionid,
                    'other' => [
                        'quizid' => $section['quizid'],
                        'firstslotnumber' => $section['firstslot'],
                        'firstslotid' => $slotid,
                        'title' => $section['heading'],
                    ],
                ]);
                $event->trigger();
            }
        } else {
            $quizcontext = get_context(\CONTEXT_MODULE, null, null, null, $moduleinfo->coursemodule);
            \question_make_default_categories([$quizcontext->context]);
        }

        foreach ($params['feedback'] as $feedback) {
            $feedback['quizid'] = $moduleinfo->instance;
            $DB->insert_record('quiz_feedback', $feedback);
        }

        $response = new \stdClass();
        $response->success = true;
        $response->cmid = (int) $module->cmid;
        return $response;
    }
}
