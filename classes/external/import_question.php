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
 * Import a single question with accompanying metadata via webservice.
 *
 * @package   qbank_gitsync
 * @copyright 2023 The University of Edinburgh
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_gitsync\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/externallib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');
require_once($CFG->dirroot. '/question/bank/gitsync/lib.php');

use context;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use moodle_exception;
use qformat_xml;
use question_bank;
use core_question\local\bank\question_edit_contexts;

/**
 * A webservice function to import a single question with metadata.
 */
class import_question extends external_api {
    /**
     * Returns description of webservice function parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'questionbankentryid' => new external_value(PARAM_SEQUENCE, 'Moodle questionbankentryid if question exists already'),
            'importedversion' => new external_value(PARAM_SEQUENCE, 'Last imported version of question'),
            'exportedversion' => new external_value(PARAM_SEQUENCE, 'Last exported version of question'),
            'qcategoryname' => new external_value(PARAM_TEXT, 'Category of question in form top/$category/$subcat1/$subcat2'),
            'fileinfo' => new external_single_structure([
                'component' => new external_value(PARAM_TEXT, 'File component'),
                'contextid' => new external_value(PARAM_TEXT, 'File context'),
                'filearea' => new external_value(PARAM_TEXT, 'File area'),
                'userid' => new external_value(PARAM_TEXT, 'File area'),
                'filename' => new external_value(PARAM_TEXT, 'File name'),
                'filepath' => new external_value(PARAM_TEXT, 'File path'),
                'itemid' => new external_value(PARAM_SEQUENCE, 'File item id'),
            ]),
            'contextlevel' => new external_value(PARAM_TEXT, 'Context level: 10, 40, 50, 70'),
            'coursename' => new external_value(PARAM_TEXT, 'Unique course name'),
            'modulename' => new external_value(PARAM_TEXT, 'Unique (within course) module name'),
            'coursecategory' => new external_value(PARAM_TEXT, 'Unique course category name'),
            'qcategoryid' => new external_value(PARAM_SEQUENCE, 'Question category id'),
            'instanceid' => new external_value(PARAM_SEQUENCE, 'Course, module or coursecategory id'),
        ]);
    }

    /**
     * Returns description of webservice function output.
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'questionbankentryid' => new external_value(PARAM_SEQUENCE, 'questionbankentry id'),
            'version' => new external_value(PARAM_SEQUENCE, 'question version'),
        ]);
    }

    /**
     * Import a question from XML file
     *
     * @param string|null $questionbankentryid questionbankentry id
     * @param string|null $importedversion last exported version of question
     * @param string|null $exportedversion last imported version of question
     * @param string|null $qcategoryname category of the question in form top/$category/$subcat1/$subcat2
     * @param array $fileinfo Moodle file information of previously uploaded file
     * @param int $contextlevel Moodle code for context level e.g. 10 for system
     * @param string|null $coursename Unique course name (optional unless course or module context level)
     * @param string|null $modulename Unique (within course) module name (optional unless module context level)
     * @param string|null $coursecategory course category name (optional unless course catgeory context level)
     * @param string|null $qcategoryid ID of the question category to search (supercedes $qcategoryname)
     * @param string|null $instanceid ID of the relevant object for the given context level e.g. course id
     *  for course level) to search for questions (supercedes $coursename, $modulename & $coursecategory)
     * @return object \stdClass with property questionbankentryid'
     */
    public static function execute(?string $questionbankentryid, ?string $importedversion, ?string $exportedversion,
                                    ?string $qcategoryname, array $fileinfo,
                                    int $contextlevel, ?string $coursename = null, ?string $modulename = null,
                                    ?string $coursecategory = null,  ?string $qcategoryid = null,
                                    ?string $instanceid = null): object {
        global $CFG, $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'questionbankentryid' => $questionbankentryid,
            'importedversion' => $importedversion,
            'exportedversion' => $exportedversion,
            'qcategoryname' => $qcategoryname,
            'fileinfo' => $fileinfo,
            'contextlevel' => $contextlevel,
            'coursename' => $coursename,
            'modulename' => $modulename,
            'coursecategory' => $coursecategory,
            'qcategoryid' => $qcategoryid,
            'instanceid' => $instanceid,
        ]);
        $questiondata = null;
        $question = null;
        $thiscontext = null;
        $qformat = null;
        if ($params['questionbankentryid']) {
            $questiondata = get_question_data($params['questionbankentryid']);
            $thiscontext = context::instance_by_id($questiondata->contextid);
            if (strval($questiondata->version) !== $params['importedversion']
                       && strval($questiondata->version) !== $params['exportedversion']) {
                // This should only happen if another user updated the question during the import process.
                $qinfo = get_minimal_question_data($params['questionbankentryid']);
                throw new moodle_exception('importversionerror', 'qbank_gitsync', null,
                            ['name' => $qinfo->name,
                             'currentversion' => $questiondata->version,
                             'importedversion' => $params['importedversion'],
                             'exportedversion' => $params['exportedversion'],
                            ]);
            }
        } else {
            $thiscontext = get_context($params['contextlevel'], $params['coursecategory'],
                                       $params['coursename'], $params['modulename'],
                                       $params['instanceid'])->context;
        }

        $qformat = new qformat_xml();
        $qformat->set_display_progress(false);

        // The webservice user needs to have access to the context. They could be given Manager
        // role at site level to access everything or access could be restricted to certain courses.
        self::validate_context($thiscontext);
        require_capability('qbank/gitsync:importquestions', $thiscontext);

        $iscategory = false;
        if ($params['questionbankentryid']) {
            $question = question_bank::load_question($questiondata->questionid);
        } else if (isset($params['qcategoryid']) && $params['qcategoryid'] !== '') {
            $category = $DB->get_record('question_categories', ['id' => $qcategoryid]);
            $qformat->setCategory($category);
            $qformat->setCatfromfile(false);
        } else if ($params['qcategoryname']) {
            // Category name should be in form top/$category/$subcat1/$subcat2 and
            // have been gleaned directly from category xml file.
            // Find the 'top' category for the context ($parent==0) and
            // then descend through the hierarchy until we find the category we need.
            $catnames = split_category_path($params['qcategoryname']);
            $parent = 0;
            foreach ($catnames as $key => $catname) {
                $category = $DB->get_record('question_categories', ['name' => $catname,
                                            'contextid' => $thiscontext->id,
                                            'parent' => $parent,
                                           ]);
                $parent = $category->id;
            }
            $qformat->setCategory($category);
            $qformat->setCatfromfile(false);
        } else {
            // No categoryname was supplied so we're dealing with a category question file.
            // We supply the base category for the context and let the import process
            // figure out if it needs to create a new category based on the info in the file.
            $category = $DB->get_record("question_categories", ['name' => 'top', 'contextid' => $thiscontext->id]);
            $qformat->setCategory($category);
            $qformat->setCatfromfile(true);
            $iscategory = true;
        }
        $fs = get_file_storage();
        $file = $fs->get_file($params['fileinfo']['contextid'], $params['fileinfo']['component'],
                              $params['fileinfo']['filearea'], $params['fileinfo']['itemid'],
                              $params['fileinfo']['filepath'], $params['fileinfo']['filename']);
        $filename = $file->get_filename();
        $requestdir = make_request_directory();
        $tempfile = $file->copy_content_to_temp("{$requestdir}/{$filename}");

        if ($params['questionbankentryid']) {
            question_require_capability_on($question, 'edit');
        } else {
            $qformat->setFilename($tempfile);
            $qformat->setContextfromfile(false);
            $qformat->setStoponerror(true);
            $contexts = new question_edit_contexts($thiscontext);
            $qformat->setContexts($contexts->having_one_edit_tab_cap('import'));
        }

        if (!$qformat->importpreprocess()) {
            throw new moodle_exception('importerror', 'qbank_gitsync', null, $filename);
        }

        if ($params['questionbankentryid']) {
            \qbank_importasversion\importer::import_file($qformat, $question, $tempfile);
        } else {
            if (!$qformat->importprocess()) {
                throw new moodle_exception('importerror', 'qbank_gitsync', null, $filename);
            }
        }

        // In case anything needs to be done after.
        if (!$success = $qformat->importpostprocess()) {
            throw new moodle_exception('importerror', 'qbank_gitsync', null, $filename);
        }

        $file->delete();
        $response = new \stdClass();
        $response->questionbankentryid = null;
        $response->version = null;
        // Log imported question and return id of new question ready to make manifest file.
        if (!$params['questionbankentryid'] && !$iscategory) {
            $eventparams = [
                'contextid' => $qformat->category->contextid,
                'other' => ['format' => 'xml', 'categoryid' => $qformat->category->id],
            ];
            $event = \core\event\questions_imported::create($eventparams);
            $event->trigger();

            $newquestionbankentryid = $DB->get_field('question_versions', 'questionbankentryid',
                            ['questionid' => $qformat->questionids[0]], $strictness = MUST_EXIST);
            $response->questionbankentryid = $newquestionbankentryid;
        }
        if ($params['questionbankentryid']) {
            $response->questionbankentryid = $params['questionbankentryid'];
        }
        if ($response->questionbankentryid) {
            $response->version = $DB->get_field_sql(
                'SELECT MAX(version) FROM {question_versions} WHERE questionbankentryid = :questionbankentryid',
                ['questionbankentryid' => $response->questionbankentryid]
            );
        }
        return $response;
    }
}
