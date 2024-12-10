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
 * Set up for qbank_gitsync plugin webservice.
 *
 * @package   qbank_gitsync
 * @copyright 2023 The University of Edinburgh
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Web service allows import and export of question from the command line.
$functions = [
    'qbank_gitsync_export_question' => [
        'classname'   => 'qbank_gitsync\external\export_question',
        'description' => 'Exports a question and separate metadata.',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'qbank_gitsync_import_question' => [
        'classname'   => 'qbank_gitsync\external\import_question',
        'description' => 'Imports a question and separate metadata.',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'qbank_gitsync_delete_question' => [
        'classname'   => 'qbank_gitsync\external\delete_question',
        'description' => 'Deletes all versions of a question',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'qbank_gitsync_get_question_list' => [
        'classname'   => 'qbank_gitsync\external\get_question_list',
        'description' => 'Get list of questions for a context',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'qbank_gitsync_export_quiz_data' => [
        'classname'   => 'qbank_gitsync\external\export_quiz_data',
        'description' => 'Export quiz content and layout data',
        'type'        => 'read',
        'ajax'        => true,
    ],
    'qbank_gitsync_import_quiz_data' => [
        'classname'   => 'qbank_gitsync\external\import_quiz_data',
        'description' => 'Import quiz content and layout data',
        'type'        => 'write',
        'ajax'        => true,
    ],
];

$services = [
    'qbank_gitsync' => [
            'functions' => ['qbank_gitsync_export_question',
                            'qbank_gitsync_import_question',
                            'qbank_gitsync_delete_question',
                            'qbank_gitsync_get_question_list',
                            'qbank_gitsync_export_quiz_data',
                            'qbank_gitsync_import_quiz_data',
                        ],
            'restrictedusers' => 1,
            'enabled' => 1,
            'shortname' => 'qbank_gitsync_ws',
    ],
];
