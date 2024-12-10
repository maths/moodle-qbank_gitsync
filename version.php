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
 * Version information for GitSync question bank plugin.
 *
 * @package   qbank_gitsync
 * @copyright 2023 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2024121000;
// Question versions functionality of Moodle 4 required.
// Question delete fix for Moodle 4.1.5 required.
// NB 4.2.0 and 4.2.1 do not have the fix.
$plugin->requires  = 2022112805;
$plugin->component = 'qbank_gitsync';
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '0.10.0 for Moodle 4.1+';

$plugin->dependencies = [
    'qbank_importasversion'     => 2024041600,
];
