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
 * Delete a single question via webservice
 *
 * @package   qbank_gitsync
 * @copyright 2023 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_gitsync\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot. '/question/bank/gitsync/lib.php');

use context;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use moodle_exception;

/**
 * A webservice function to delete a single question.
 */
class delete_question extends external_api {

    /**
     * Returns description of webservice function parameters.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'questionbankentryid' => new external_value(PARAM_SEQUENCE, 'Moodle question questionbankentryid'),
        ]);
    }

    /**
     * Returns description of webservice function output.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'question'),
        ]);
    }

    /**
     * Deletes all versions of a single question.
     *
     * @param string $questionbankentryid questionbankentry id
     * @return array success: true or exception
     */
    public static function execute(string $questionbankentryid):array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'questionbankentryid' => $questionbankentryid,
        ]);
        $questiondata = get_question_data($params['questionbankentryid']);
        if (!$questiondata->questionid) {
            throw new moodle_exception(get_string('noquestionerror', 'qbank_gitsync', $params['questionbankentryid']));
        }
        $thiscontext = context::instance_by_id($questiondata->contextid);
        self::validate_context($thiscontext);
        require_capability('qbank/gitsync:deletequestions', $thiscontext);
        \qbank_deletequestion\helper::delete_questions([$questiondata->questionid], true);

        $response = [
            'success' => true,
        ];

        return $response;
    }
}
