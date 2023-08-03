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
        'ajax'        => true
    ],
    'qbank_gitsync_import_question' => [
        'classname'   => 'qbank_gitsync\external\import_question',
        'description' => 'Imports a question and separate metadata.',
        'type'        => 'write',
        'ajax'        => true
    ]
];

$services = array(
    'qbank_gitsync' => array(
            'functions' => ['qbank_gitsync_export_question', 'qbank_gitsync_import_question'],
            'restrictedusers' => 0,
            'enabled' => 1,
            'shortname' => 'qbank_gitsync_ws'
    )
);
