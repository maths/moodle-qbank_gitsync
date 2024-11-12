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
 * Export structure (not questions) of a quiz.
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
require_once('../classes/export_quiz.php');

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
        'longopt' => 'modulename',
        'shortopt' => 'm',
        'description' => 'Unique (within course) quiz name.',
        'default' => null,
        'variable' => 'modulename',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'instanceid',
        'shortopt' => 'n',
        'description' => 'Numerical course module id of quiz.',
        'default' => null,
        'variable' => 'instanceid',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'quiet',
        'shortopt' => 'z',
        'description' => 'Do not display context info or option to abort.',
        'default' => false,
        'variable' => 'quiet',
        'valuerequired' => false,
    ],
    [
        'longopt' => 'subcall',
        'shortopt' => 'w',
        'description' => 'Is this a subcall of the script?',
        'default' => false,
        'variable' => 'subcall',
        'valuerequired' => false,
        'hidden' => true,
    ]
];

if (!function_exists('simplexml_load_file')) {
    echo 'Please install the PHP library SimpleXML.' . "\n";
    exit;
}
$clihelper = new cli_helper($options);
$exportquiz = new export_quiz($clihelper, $moodleinstances);
if ($exportquiz->nonquizmanifestpath) {
    if (!isset($clihelper->get_arguments()['quiet'])) {
        echo "Checking repo...\n";
    }
    $clihelper->check_for_changes($exportquiz->nonquizmanifestpath);
}
if ($exportquiz->quizmanifestpath) {
    if (!isset($clihelper->get_arguments()['quiet'])) {
        echo "Checking quiz repo...\n";
    }
    $clihelper->check_for_changes($exportquiz->quizmanifestpath);
}
$exportquiz->process();
