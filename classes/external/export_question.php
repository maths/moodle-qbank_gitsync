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
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
use external_function_parameters;
use external_value;
use external_api;
use question_bank;
use qformat_xml;
use context_system;
use question_edit_contexts;

/**
 * A webservice function to export a single question with metadata.
 */
class export_question extends external_api {

    /**
     * Returns description of webservice function parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'questionid' => new external_value(PARAM_SEQUENCE, 'id of question'),
                ]
            );
    }

    /**
     * Returns description of webservice function output.
     * @return external_value
     */
    public static function execute_returns() {
        return new external_value(PARAM_RAW, 'question');
    }

    /**
     * Exports a single question as XML.
     * Will need to add metadata and be packaged properly.
     * @param string $questionid question id
     */
    public static function execute($questionid) {
        $thiscontext = context_system::instance();
        self::validate_context($thiscontext);
        $questiondata = question_bank::load_question_data($questionid);
        $qformat = new qformat_xml();
        $qformat->setQuestions([$questiondata]);
        $contexts = new question_edit_contexts($thiscontext);
        // Very basic security. The webservice user needs moodle/question:viewall capability
        // for system context. This is really a bit of a pointless hoop on top of
        // qbank/gitsync:exportquestions but we might want to restrict access scope in future.
        $qformat->setContexts($contexts->having_one_edit_tab_cap('export'));
        $qformat->setCattofile(false);
        $qformat->setContexttofile(false);
        $qformat->exportpreprocess();
        $question = $qformat->exportprocess();

        return var_dump($question);
    }
}
