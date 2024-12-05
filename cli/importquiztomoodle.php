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
 * Import a git repo containing questions into Moodle.
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
        'longopt' => 'directory',
        'shortopt' => 'd',
        'description' => 'Directory of repo on users computer containing "top" folder, ' .
                         'relative to root directory.',
        'default' => '',
        'variable' => 'directory',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'subdirectory',
        'shortopt' => 's',
        'description' => 'Relative subdirectory of repo to actually import.',
        'default' => null,
        'variable' => 'subdirectory',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'contextlevel',
        'shortopt' => 'l',
        'description' => 'Context in which to place quiz. Always course.',
        'default' => 'course',
        'variable' => 'contextlevel',
        'valuerequired' => true,
        'hidden' => true,
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
        'description' => 'Unique course name for course or module context.',
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
        'longopt' => 'createquiz',
        'shortopt' => 'k',
        'description' => 'Are we creating a quiz?',
        'default' => true,
        'variable' => 'createquiz',
        'valuerequired' => false,
        'hidden' => true,
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
        'longopt' => 'ignorecat',
        'shortopt' => 'x',
        'description' => 'Regex of categories to ignore - add an extra leading / for Windows.',
        'default' => $ignorecat,
        'variable' => 'ignorecat',
        'valuerequired' => true,
    ],
];

if (!function_exists('simplexml_load_file')) {
    echo 'Please install the PHP library SimpleXML.' . "\n";
    exit;
}
$scriptdirectory = dirname(__FILE__);
$clihelper = new cli_helper($options);
$importquiz = new import_quiz($clihelper, $moodleinstances);
$importquiz->import_all($clihelper, $scriptdirectory);
