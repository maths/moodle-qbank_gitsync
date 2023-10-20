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
 * Trait to re-use code for tidying the manifest file.
 *
 * Used in import_repo (when deleting) and export_repo
 *
 *
 * @package    qbank_gitsync
 * @copyright  2023 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace qbank_gitsync;
/**
 * Code used for both deleting and exporting questions.
 *
 * Uses quite a few internal properties of export_repo and import_repo
 * so probably clearer to use a trait than add to cli_helper.
 */
trait tidy_trait {
    /**
     * Loop through questions in manifest file and
     * remove from file if the matching question is no longer in Moodle.
     *
     * @return void
     */
    public function tidy_manifest():void {
        $response = $this->listcurlrequest->execute();
        $questionsinmoodle = json_decode($response);
        if (is_null($questionsinmoodle)) {
            echo "Broken JSON returned from Moodle:\n";
            echo $response . "\n";
            echo "Failed to tidy manifest.\n";
        } else if (!is_array($questionsinmoodle)) {
            if (property_exists($questionsinmoodle, 'exception')) {
                echo "{$questionsinmoodle->message}\n";
            }
            echo "Failed to tidy manifest.\n";
        } else {
            $existingquestions = array_column($questionsinmoodle, null, 'questionbankentryid');
            $newentrylist = [];
            foreach ($this->manifestcontents->questions as $currententry) {
                if (isset($existingquestions[$currententry->questionbankentryid])) {
                    array_push($newentrylist, $currententry);
                }
            }
            $this->manifestcontents->questions = $newentrylist;
            $success = file_put_contents($this->manifestpath, json_encode($this->manifestcontents));
            if ($success === false) {
                echo "\nUnable to update manifest file: {$this->manifestpath}\n";
                echo "Failed to tidy manifest\n";
            }
        }
    }
}
