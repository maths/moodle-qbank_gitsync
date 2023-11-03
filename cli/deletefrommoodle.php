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
 * Delete questions from Moodle that are not linked to a file in the repo.
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
                         'relative to root directory. ',
        'default' => '',
        'variable' => 'directory',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'subdirectory',
        'shortopt' => 's',
        'description' => 'Relative subdirectory of repo to actually delete from.',
        'default' => 'top',
        'variable' => 'subdirectory',
        'valuerequired' => true,
    ],
    [
        'longopt' => 'contextlevel',
        'shortopt' => 'l',
        'description' => 'Context in which to place questions. Set to system, coursecategory, course or module',
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
        'description' => 'Numerical id of subdirectory to actually export.',
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
    [
        'longopt' => 'usegit',
        'shortopt' => 'u',
        'description' => 'Is the repo controlled using Git?',
        'default' => $usegit,
        'variable' => 'usegit',
        'valuerequired' => false,
    ]
];

$clihelper = new cli_helper($options);
$importrepo = new import_repo($clihelper, $moodleinstances);
$clihelper->check_for_changes($importrepo->manifestpath);
$clihelper->backup_manifest($importrepo->manifestpath);
// Add any updates from temp file into manifest in case this is being
// run after an unresolved import failure because someone is bound to try.
$importrepo->recovery();
// Tidy manifest in case this job has previously failed halfway through leaving
// questions deleted from Moodle but still in manifest.
$importrepo->tidy_manifest();
$importrepo->delete_no_file_questions(true);
$importrepo->delete_no_record_questions(true);
