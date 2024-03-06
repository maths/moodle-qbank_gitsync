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
 * Export from Moodle into a git repo containing questions.
 *
 * @package    qbank_gitsync
 * @copyright  2023 University of Edinburgh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_gitsync;
define('CLI_SCRIPT', true);
require_once('./config.php');
require_once('../classes/curl_request.php');
require_once('../classes/cli_helper.php');
require_once('../classes/export_trait.php');
require_once('../classes/tidy_trait.php');
require_once('../classes/export_repo.php');

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
        'longopt' => 'manifestpath',
        'shortopt' => 'f',
        'description' => 'Filepath of manifest file relative to root directory.',
        'default' => $manifestpath,
        'variable' => 'manifestpath',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'subcategory',
        'shortopt' => 's',
        'description' => 'Relative subcategory of question to actually export.',
        'default' => null,
        'variable' => 'subcategory',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'questioncategoryid',
        'shortopt' => 'q',
        'description' => 'Numerical id of subcategory to actually export.',
        'default' => null,
        'variable' => 'qcategoryid',
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
        'valuerequired' => false,
    ],
];

if (!function_exists('simplexml_load_file')) {
    echo 'Please install the PHP library SimpleXML.' . "\n";
    exit;
}
$clihelper = new cli_helper($options);
$exportrepo = new export_repo($clihelper, $moodleinstances);
$clihelper->check_for_changes($exportrepo->manifestpath);
$clihelper->backup_manifest($exportrepo->manifestpath);
$exportrepo->process();
