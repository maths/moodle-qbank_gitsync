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
 * @copyright  2023 University of Edinburgh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_gitsync;
define('CLI_SCRIPT', true);
require_once('./config.php');
require_once('../classes/curl_request.php');
require_once('../classes/cli_helper.php');
require_once('../classes/tidy_trait.php');
require_once('../classes/import_repo.php');
require_once('../classes/yaml_converter.php');

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
        'longopt' => 'directory',
        'shortopt' => 'd',
        'description' => 'Directory of repo on users computer containing "top" folder, ' .
                         'relative to root directory.',
        'default' => '',
        'variable' => 'directory',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'defaultfile',
        'shortopt' => 'o',
        'description' => 'Name of file containing custom question defaults.',
        'default' => '',
        'variable' => 'defaultfile',
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
        'longopt' => 'targetcategory',
        'shortopt' => 'k',
        'description' => 'Category to import a subdirectory into.',
        'default' => null,
        'variable' => 'targetcategory',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'targetcategoryname',
        'shortopt' => 'a',
        'description' => 'Category to import a subdirectory into.',
        'default' => null,
        'variable' => 'targetcategoryname',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'contextlevel',
        'shortopt' => 'l',
        'description' => 'Context to import questions into. Should always be module for Moodle 5+. ' .
                         'Should always be course for Moodle < 5.',
        'default' => null,
        'variable' => 'contextlevel',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'coursename',
        'shortopt' => 'c',
        'description' => 'Unique course name.',
        'default' => null,
        'variable' => 'coursename',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'modulename',
        'shortopt' => 'm',
        'description' => 'Name of the question bank module',
        'default' => null,
        'variable' => 'modulename',
        'valuerequired' => true,
        'hidden' => true,
    ],
    [
        'longopt' => 'coursecategory',
        'shortopt' => 'g',
        'description' => 'Not used.',
        'default' => null,
        'variable' => 'coursecategory',
        'valuerequired' => true,
        'hidden' => true,
    ],
    [
        'longopt' => 'instanceid',
        'shortopt' => 'n',
        'description' => 'Numerical id of the course or module.',
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
        'longopt' => 'useyaml',
        'shortopt' => 'y',
        'description' => 'Export questions as YAML difference file?',
        'default' => $useyaml,
        'variable' => 'useyaml',
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
$arguments = $clihelper->get_arguments();
echo "Importing a course into Moodle. Associated quiz contexts will also be imported.\n";
echo "Structures of existing quizzes in Moodle will not be updated.\n";
$importrepo = new import_repo($clihelper, $moodleinstances);
$clihelper->check_for_changes($importrepo->manifestpath);
$clihelper->backup_manifest($importrepo->manifestpath);
$importrepo->recovery();
$importrepo->check_question_versions();
$clihelper->commit_hash_update($importrepo);
$importrepo->process();
$importrepo->update_quizzes($clihelper, $scriptdirectory);

