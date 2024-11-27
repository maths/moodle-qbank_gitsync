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
 * Definition of strings for qbank_gitsync plugin.
 *
 * @package    qbank_gitsync
 * @copyright  2023 The University of Edinburgh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['categoryerror'] = 'Problem with question category: {$a}';
$string['categoryerrornew'] = 'Problem with question category: {$a}. If the course is new, please open the question bank in Moodle to initialise it and try Gitsync again.';
$string['categorymismatcherror'] = 'Problem with question category: {$a}. The category is not in the supplied context.';
$string['contexterror'] = 'The context level is invalid: {$a}';
$string['exporterror'] = 'Could not export question id: {$a}';
$string['gitsync:deletequestions'] = 'Delete';
$string['gitsync:exportquestions'] = 'Export';
$string['gitsync:importquestions'] = 'Import';
$string['gitsync:listquestions'] = 'List';
$string['importerror'] = 'Could not import question from file: {$a}';
$string['importversionerror'] = 'Could not import question : {$a->name} Current version in Moodle is {$a->currentversion}. Last imported version is {$a->importedversion}. Last exported version is {$a->exportedversion}. You need to export the question.';
$string['noquestionerror'] = 'Question does not exist. Questionbankentryid: {$a}';
$string['pluginname'] = 'Gitsync';
