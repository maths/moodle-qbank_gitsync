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
 * Create a git repo containing questions from Moodle.
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
require_once('../classes/create_repo.php');

$options = [
    [
        'longopt' => 'moodleinstance',
        'shortopt' => 'i',
        'description' => 'Key of Moodle instance in $moodleinstances to use (see config.php). ' .
                         'Should match end of instance URL. ' .
                         'Defaults to $instance in config.php.',
        'default' => $instance,
        'variable' => 'moodleinstance',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'rootdirectory',
        'shortopt' => 'r',
        'description' => "Directory on user's computer containing repos. " .
                         'Defaults to $rootdirectory in config.php.',
        'default' => $rootdirectory,
        'variable' => 'rootdirectory',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'directory',
        'shortopt' => 'd',
        'description' => 'Directory of repo on users computer, containing "top" folder, ' .
                         'relative to root directory.',
        'default' => '',
        'variable' => 'directory',
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
        'longopt' => 'contextlevel',
        'shortopt' => 'l',
        'description' => 'Context from which to extract questions. Set to system, coursecategory, course or module',
        'default' => null,
        'variable' => 'contextlevel',
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
        'longopt' => 'modulename',
        'shortopt' => 'm',
        'description' => 'Unique (within course) module name for module context.',
        'default' => null,
        'variable' => 'modulename',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'coursecategory',
        'shortopt' => 'g',
        'description' => 'Unique course category name for coursecategory context.',
        'default' => null,
        'variable' => 'coursecategory',
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
        'longopt' => 'instanceid',
        'shortopt' => 'n',
        'description' => 'Numerical id of the course, module of course category.',
        'default' => null,
        'variable' => 'instanceid',
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
        'longopt' => 'ignorecat',
        'shortopt' => 'x',
        'description' => 'Regex of categories to ignore - add an extra leading / for Windows.',
        'default' => $ignorecat,
        'variable' => 'ignorecat',
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
$createrepo = new create_repo($clihelper, $moodleinstances);
$clihelper->check_repo_initialised($createrepo->manifestpath);
$createrepo->process();
$clihelper->commit_hash_setup($createrepo);
// If we're exporting a quiz then we try getting the structure as well.
// Skip if we're creating a whole course repo or we'll do it twice!
if ($createrepo->manifestcontents->context->contextlevel === 70 && !$clihelper->get_arguments()['subcall']) {
    $scriptdirectory = dirname(__FILE__);
    $createrepo->export_quiz_structure($clihelper, $scriptdirectory);
    if ($clihelper->get_arguments()['usegit']) {
        chdir(dirname($createrepo->manifestpath));
        exec("git add --all");
        exec('git commit -m "Initial Commit - Quiz structure"');
    }
}

