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
 * Sample config file. Copy, update and rename config.php
 *
 * @package    qbank_gitsync
 * @copyright  2023 University of Edinburgh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Remove this line in actual config file for running on your local computer.
defined('MOODLE_INTERNAL') || die();

// Array of moodleinstnce nicknames and URLs.
// The nicknames are used how....
// No trailing slash on the URLs.
$moodleinstances = [
    'instance1' => 'http://stack.org/instance1',
    'instance2' => 'http:localhost:8888',
];

// Array of moodleinstance nicknames and tokens.
$token = [
    'instance1' => '4ec...cfcd',
    'instance2' => '6ae...abcd',
];

// Use this variable to hard-wire the default instance.
$instance = 'instance1';

// Root directory on the local file system.
// This directory stores all git repos associated with the gitsync.
// No trailing slash on the directory name.
$rootdirectory = '/home/user/questions';

$usegit = true;
