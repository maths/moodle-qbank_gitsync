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
 * Import structure (not questions) of a quiz to Moodle.
 *
 * @package    qbank_gitsync
 * @copyright  2024 University of Edinburgh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_gitsync;
define('CLI_SCRIPT', true);
require_once('./config.php');
require_once('../classes/curl_request.php');
require_once('../classes/cli_helper.php');
require_once('../classes/import_quiz.php');

$options = [
    [
        'longopt' => 'moodleinstance',
        'shortopt' => 'i',
        'description' => 'Key of Moodle instance in $moodleinstances to use. ' .
                         'Should match end of instance URL.',
        'default' => $instance,
        'variable' => 'moodleinstance',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'rootdirectory',
        'shortopt' => 'r',
        'description' => "Directory on user's computer containing repos.",
        'default' => $rootdirectory,
        'variable' => 'rootdirectory',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'nonquizmanifestpath',
        'shortopt' => 'p',
        'description' => 'Filepath of non-quiz manifest file relative to root directory.',
        'default' => null,
        'variable' => 'nonquizmanifestpath',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'quizmanifestpath',
        'shortopt' => 'f',
        'description' => 'Filepath of quiz manifest file relative to root directory.',
        'default' => null,
        'variable' => 'quizmanifestpath',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'quizdatapath',
        'shortopt' => 'a',
        'description' => 'Filepath of quiz data file relative to root directory.',
        'default' => null,
        'variable' => 'quizdatapath',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'coursename',
        'shortopt' => 'c',
        'description' => 'Unique course name of course.',
        'default' => null,
        'variable' => 'coursename',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'instanceid',
        'shortopt' => 'n',
        'description' => 'Numerical id of the course.',
        'default' => null,
        'variable' => 'instanceid',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'token',
        'shortopt' => 't',
        'description' => 'Security token for webservice.',
        'default' => $token,
        'variable' => 'token',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'help',
        'shortopt' => 'h',
        'description' => '',
        'default' => false,
        'variable' => 'help',
        'valuerequired' => false,
    ],
    [
        'longopt' => 'usegit',
        'shortopt' => 'u',
        'description' => 'Is the repo controlled using Git?',
        'default' => $usegit,
        'variable' => 'usegit',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'subcall',
        'shortopt' => 'w',
        'description' => 'Is this a subcall of the script?',
        'default' => false,
        'variable' => 'subcall',
        'valuerequired' => false,
        'hidden' => true,
    ],
];

if (!function_exists('simplexml_load_file')) {
    echo 'Please install the PHP library SimpleXML.' . "\n";
    exit;
}

$clihelper = new cli_helper($options);
$importquiz = new import_quiz($clihelper, $moodleinstances);
if ($importquiz->nonquizmanifestpath) {
    echo "Checking repo...\n";
    $clihelper->check_for_changes($importquiz->nonquizmanifestpath);
}
if ($importquiz->quizmanifestpath) {
    echo "Checking quiz repo...\n";
    $clihelper->check_for_changes($importquiz->quizmanifestpath);
}
$importquiz->process();
