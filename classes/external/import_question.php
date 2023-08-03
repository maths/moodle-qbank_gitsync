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

use external_function_parameters;
use external_value;
use external_api;
use qformat_xml;
use context_system;
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
            'filepath' => new external_value(PARAM_PATH, 'Local path for file for upload')
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
     */
    public static function execute($questionid, $categoryname, $filepath) {
        global $CFG, $DB;
        $thiscontext = context_system::instance();
        self::validate_context($thiscontext);
        $qformat = new qformat_xml();
        // Context and instance ids?
        $category = $DB->get_record("question_categories", ['name' => $categoryname]);
        $qformat->setCategory($category);
        $qformat->setFilename($filepath);
        $qformat->set_display_progress(false);
        $qformat->setCatfromfile(false);
        $qformat->setContextfromfile(false);
        $qformat->setStoponerror(true);

        $contexts = new question_edit_contexts($thiscontext);
        $qformat->setContexts($contexts->having_one_edit_tab_cap('import'));

        $success = $qformat->importprocess();

        // Annoyingly output from importprocess is just a boolean. We need to find
        // out the id of the created question somehow.
        return $success;
    }
}
