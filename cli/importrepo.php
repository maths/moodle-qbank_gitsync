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
 * @subpackage cli
 * @copyright  2023 University of Edinburgh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

// TODO Do we want to make any of these command line variables.
$directory = '/home/efarrow1/question_repos/first/questions';
$moodleurl = 'http://stack.stack.virtualbox.org/edmundlocal';
$token = '4ec7cd3f62e08f595df5e9c90ea7cfcd';
$wsurl = $moodleurl . '/webservice/rest/server.php';
$contextlevel = 'module'; // Set to system, coursecategory, course or module.
$questionid = '0'; // TODO Holding value until import of existing questions developed.
$contextidentifier1 = 'Course 1'; // Unique course or category name.
$contextidentifier2 = 'Test 1'; // Unique (within course) module name.
// Name of file containing category information in each directory and subdirectory.
define('CATEGORYFILE', 'gitsync_category');

/**
 * Convert our contextlevel string to Moodle's internal code.
 *
 * @param string $level
 * @return integer
 */
function getcontextlevel(string $level): int {
    switch ($level) {
        case 'system':
            return 10;
        case 'coursecategory':
            return 40;
        case 'course':
            return 50;
        case 'module':
            return 70;
        default:
            echo "Context level '{$level}' is not valid.\n";
            exit;
    }
}

$repoiterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$ch = curl_init($wsurl);
$post = [
    'wstoken' => $token,
    'wsfunction' => 'qbank_gitsync_import_question',
    'moodlewsrestformat' => 'json',
    'questionid' => $questionid,
    'contextlevel' => getcontextlevel($contextlevel),
    'contextidentifier1' => $contextidentifier1,
    'contentidentifier2' => $contextidentifier2,
];
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Find all the category files first and create categories where needed.
foreach ($repoiterator as $repoitem) {
    if ($repoitem->isFile()) {
        if (pathinfo($repoitem, PATHINFO_EXTENSION) === 'xml' && pathinfo($repoitem, PATHINFO_FILENAME) === CATEGORYFILE) {
            $post['categoryname'] = '';
            // Full path of file (including filename) on the local computer.
            $post['filepath'] = $repoitem->getPathname();
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            $response = curl_exec($ch);
        }
    }
}

// Find all the question files and import them.
foreach ($repoiterator as $repoitem) {
    if ($repoitem->isFile()) {
        if (pathinfo($repoitem, PATHINFO_EXTENSION) === 'xml' && pathinfo($repoitem, PATHINFO_FILENAME) !== CATEGORYFILE) {
            // Path of file (without filename) relative to base $directory.
            $post['categoryname'] = $repoiterator->getSubPath();
            $post['filepath'] = $repoitem->getPathname();
            if ($post['categoryname']) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                $response = curl_exec($ch);
                echo var_dump($response);
            } else {
                echo "Root directory should not contain files, only a 'top' directory.\n";
                echo $post['filepath'] . ' not imported.';
            }
        }
    }
}

curl_close($ch);
