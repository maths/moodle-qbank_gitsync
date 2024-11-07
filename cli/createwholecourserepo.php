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
 * Create git repos containing questions from Moodle.
 * Exports a course context into one repo and associated
 * quizzes into sibling repos.
 *
 * @package    qbank_gitsync
 * @copyright  2024 University of Edinburgh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_gitsync;

use stdClass;
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
        'longopt' => 'contextlevel',
        'shortopt' => 'l',
        'description' => 'Context from which to extract questions. Should always be course.',
        'default' => 'course',
        'variable' => 'contextlevel',
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
        'longopt' => 'coursename',
        'shortopt' => 'c',
        'description' => 'Unique course name for course or module context.',
        'default' => null,
        'variable' => 'coursename',
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

// Create course directory and populate.
$scriptdirectory = dirname(__FILE__);
$clihelper = new cli_helper($options);
$arguments = $clihelper->get_arguments();
$arguments['contextlevel'] = 'course';
$clihelper->processedoptions = $arguments;
echo "Exporting a course. Associated quiz contexts will also be exported.\n";
$createrepo = new create_repo($clihelper, $moodleinstances);
$clihelper->create_directory(dirname($createrepo->manifestpath));
$clihelper->check_repo_initialised($createrepo->manifestpath);
$createrepo->process();
$clihelper->commit_hash_setup($createrepo);

// Create quiz directories and populate.
$contextinfo = $clihelper->check_context($createrepo, false, true);
if ($arguments['directory']) {
    $basedirectory = $arguments['rootdirectory'] . '/' . $arguments['directory'];
} else {
    $basedirectory = $arguments['rootdirectory'];
}
$moodleinstance = $arguments['moodleinstance'];
$coursemanifestname = cli_helper::get_manifest_path($moodleinstance, 'course', null,
                            $contextinfo->contextinfo->coursename, null, $basedirectory);
$instanceid = $arguments['instanceid'];
$token = $arguments['token'][$moodleinstance];
$ignorecat = $arguments['ignorecat'];
$ignorecat = ($ignorecat) ? ' -x "' . $ignorecat . '"' : '';
$quizfilepath = $basedirectory . '/' . $clihelper::QUIZPATH_FILE;
$quiz_locations = [];
foreach ($contextinfo->quizzes as $quiz) {
    $instanceid = "{$quiz->instanceid}";
    $quizdirectory = cli_helper::get_quiz_directory($basedirectory, $quiz->name);
    $rootdirectory = $clihelper->create_directory($quizdirectory);
    echo "\nExporting quiz: {$quiz->name} to {$rootdirectory}\n";
    chdir($scriptdirectory);
    $output = shell_exec('php createrepo.php -r "' . $rootdirectory .  '" -i "' . $moodleinstance . '" -l "module" -n ' . (int) $instanceid . ' -t ' . $token . ' -z' . $ignorecat);
    echo $output;
    $quizmanifestname = cli_helper::get_manifest_path($moodleinstance, 'module', null,
                            $contextinfo->contextinfo->coursename, $quiz->name, $rootdirectory);
    chdir($scriptdirectory);
    $output = shell_exec('php exportquizstructurefrommoodle.php -z -r "" -i "' . $moodleinstance . '" -n ' . (int) $instanceid . ' -t ' . $token. ' -p "' . $coursemanifestname. '" -f "' . $quizmanifestname . '"');
    $quiz_locations[$instanceid] = basename($rootdirectory);
    $success = file_put_contents($quizfilepath, json_encode($quiz_locations));
    if ($success === false) {
        echo "\nUnable to update quizpath file: {$quizfilepath}\n Aborting.\n";
        exit();
    }
    echo $output;
}
// Commit the final quiz file.
chdir($basedirectory);
exec("git add --all");
exec('git commit -m "Initial Commit - final update"');
