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
 * Unit tests for import_questions function of gitsync webservice
 *
 * @package    qbank_gitsync
 * @copyright  2023 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_gitsync\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/question/bank/gitsync/lib.php');

use context_course;
use externallib_advanced_testcase;
use external_api;

/**
 * Test the import_questions webservice function.
 * @runTestsInSeparateProcesses
 * @group qbank_gitsync
 *
 * @covers \qbank_gitsync\external\import_questions::execute
 */
final class import_questions_test extends externallib_advanced_testcase {
    /** @var core_question_generator plugin generator */
    protected \core_question_generator $generator;
    /** @var generated course object */
    protected \stdClass $course;
    /** @var \stdClass generated module object */
    protected \stdClass $module;
    /** @var \stdClass context of course */
    protected \context_course $coursecontext;
    /** @var \stdClass context of module */
    protected \stdClass $context;
    /** @var \stdClass generated question_category object */
    protected \stdClass $qcategory;
    /** @var generated user object */
    protected \stdClass $user;
    /** @var string filepath of directory containing test files */
    protected string $testrepo;
    /** Name of question to be generated and updated. */
    const QNAME = 'Example STACK question';

    public function setUp(): void {
        parent::setUp();
        global $CFG;
        $this->resetAfterTest();
        // The function under test must own the outermost transaction.
        $this->preventResetByRollback();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $this->course = $this->getDataGenerator()->create_course();
        $this->coursecontext = \context_course::instance($this->course->id);
        $this->module = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id, 'name' => 'QUIZ TEST']);
        $this->context = \context_module::instance($this->module->cmid);
        if (function_exists('question_get_default_category')) {
            // This function exists from Moodle 4.5 onwards but the second parameter
            // which creates the category if it doesn't exist is 5.0 onwards.
            $category = question_get_default_category($this->context->id, true);
            if (!$category) {
                $category = question_make_default_categories([$this->context]);
            }
        } else {
            // Deprecated from 5.0.
            $category = question_make_default_categories([$this->context]);
        }
        $this->qcategory = $this->generator->create_question_category(
            ['contextid' => \context_course::instance($this->course->id)->id]
        );
        $user = $this->getDataGenerator()->create_user();
        $this->user = $user;
        $this->setUser($user);
        $this->testrepo = $CFG->dirroot . '/question/bank/gitsync/testrepoparent/testrepo/';
    }

    /**
     * Import one file through the webservice function.
     *
     * @param string $contentpath Path to test file containing question
     * @param string $qcategoryname category of the question, empty for a category file
     * @param string $questionbankentryid existing question bank entry, if any
     * @param string $importedversion last imported version
     * @param string $exportedversion last exported version
     * @return array result for the question
     */
    public function import(
        string $contentpath,
        string $qcategoryname = '',
        string $questionbankentryid = '',
        string $importedversion = '',
        string $exportedversion = ''
    ): array {
        $results = import_questions::execute([[
            'questionbankentryid' => $questionbankentryid, 'importedversion' => $importedversion,
            'exportedversion' => $exportedversion, 'qcategoryname' => $qcategoryname, 'contextlevel' => '70',
            'coursename' => $this->course->fullname, 'modulename' => $this->module->name, 'coursecategory' => '',
            'qcategoryid' => '', 'instanceid' => '', 'content' => file_get_contents($contentpath),
        ]]);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(import_questions::execute_returns(), $results);
        $this->assertCount(1, $results);
        return $results[0];
    }

    /**
     * Give required capabilities to user.
     *
     * @return context_course
     */
    public function give_capabilities(): context_course {
        global $DB;
        // Set the required capabilities - webservice access and export rights on course.
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $this->coursecontext->id);
        return $this->coursecontext;
    }

    /**
     * Test the execute function when capabilities are present.
     */
    public function test_capabilities(): void {
        $this->give_capabilities();
        $returnvalue = $this->import($this->testrepo . 'top/cat-1/gitsync_category.xml');

        // Assert that there was a response.
        // The actual response is tested in other tests.
        $this->assertNotNull($returnvalue);
    }

    /**
     * Test the execute function fails when not logged in.
     */
    public function test_not_logged_in(): void {
        global $DB;
        $this->setUser();
        // Exception messages don't seem to get translated.
        $result = $this->import($this->testrepo . 'top/cat-1/gitsync_category.xml');
        $this->assertStringContainsString(
            'not logged in',
            $result['error']
        );
    }

    /**
     * Test the execute function fails when no webservice import capability assigned.
     */
    public function test_no_webservice_access(): void {
        global $DB;
        $managerroleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerroleid, $this->user->id, $this->coursecontext->id);
        $this->unassignUserCapability('qbank/gitsync:importquestions', \context_system::instance()->id, $managerroleid);
        $result = $this->import($this->testrepo . 'top/cat-1/gitsync_category.xml');
        $this->assertStringContainsString(
            'you do not currently have permissions to do that (Import)',
            $result['error']
        );
    }

    /**
     * Test the execute function fails when user has no access to supplied context.
     */
    public function test_import_capability(): void {
        $result = $this->import($this->testrepo . 'top/cat-1/gitsync_category.xml');
        $this->assertStringContainsString(
            'Not enrolled',
            $result['error']
        );
    }

    /**
     * Test import of category.
     * The category is created; no question id is returned; the correct logging occurs.
     */
    public function test_category_import(): void {
        global $DB;
        $this->give_capabilities();
        $createdcategory = $DB->get_record('question_categories', ['name' => 'cat 1'], '*');
        $this->assertEquals($createdcategory, false);
        $sink = $this->redirectEvents();

        // Create a category.
        $returnvalue = $this->import($this->testrepo . 'top/cat-1/gitsync_category.xml');
        $createdcategory = $DB->get_record('question_categories', ['name' => 'cat 1'], '*', $strictness = MUST_EXIST);
        $this->assertEquals($createdcategory->contextid, $this->context->id);
        $parentcategory = $DB->get_record(
            'question_categories',
            ['name' => 'top', 'contextid' => $this->context->id],
            '*',
            $strictness = MUST_EXIST
        );
        $this->assertEquals($createdcategory->parent, $parentcategory->id);
        $this->assertEquals($createdcategory->info, 'First imported folder');

        $this->assertEquals($returnvalue['questionbankentryid'], null);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 1);
        $event = reset($events);
        $this->assertInstanceOf('\core\event\question_category_created', $event);
    }

    /**
     * Test import of subcategory.
     */
    public function test_subcategory_import(): void {
        global $DB;
        $this->give_capabilities();
        $createdcategory = $DB->get_record('question_categories', ['name' => 'cat 2'], '*');
        $this->assertEquals($createdcategory, false);
        $createdsubcategory = $DB->get_record('question_categories', ['name' => 'subcat 2_1'], '*');
        $this->assertEquals($createdsubcategory, false);
        $sink = $this->redirectEvents();

        // Create a category and subcategory.
        $returnvalue = $this->import($this->testrepo . 'top/cat-2/subcat-2_1/gitsync_category.xml');
        $createdcategory = $DB->get_record('question_categories', ['name' => 'cat 2'], '*', $strictness = MUST_EXIST);
        $this->assertEquals($createdcategory->contextid, $this->context->id);
        $parentcategory = $DB->get_record(
            'question_categories',
            ['name' => 'top', 'contextid' => $this->context->id],
            '*',
            $strictness = MUST_EXIST
        );
        $this->assertEquals($createdcategory->parent, $parentcategory->id);
        // Category will have been created without info. When doing this for real, categories should be created in descending order.
        $this->assertEquals($createdcategory->info, '');

        $createdsubcategory = $DB->get_record('question_categories', ['name' => 'subcat 2_1'], '*', $strictness = MUST_EXIST);
        $this->assertEquals($createdsubcategory->contextid, $this->context->id);
        $this->assertEquals($createdsubcategory->parent, $createdcategory->id);
        $this->assertEquals($createdsubcategory->info, 'Imported subfolder');

        $this->assertEquals($returnvalue['questionbankentryid'], null);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 2);
        $this->assertInstanceOf('\core\event\question_category_created', $events['0']);
        $this->assertInstanceOf('\core\event\question_category_created', $events['1']);
    }

    /**
     * Test question import.
     */
    public function test_question_import(): void {
        global $DB;
        $this->give_capabilities();
        $createdquestion = $DB->get_record('question', ['name' => 'Third Question'], '*');
        $this->assertEquals($createdquestion, false);

        $returnvalue = $this->import($this->testrepo . 'top/cat-2/subcat-2_1/gitsync_category.xml');
        $createdsubcategory = $DB->get_record('question_categories', ['name' => 'subcat 2_1'], '*', $strictness = MUST_EXIST);

        $sink = $this->redirectEvents();
        $returnvalue = $this->import($this->testrepo . 'top/cat-2/subcat-2_1/Third-Question.xml', 'top/cat 2/subcat 2_1');
        $createdquestion = $DB->get_record('question', ['name' => 'Third Question'], '*', $strictness = MUST_EXIST);
        $qversion = $DB->get_record(
            'question_versions',
            ['questionid' => $createdquestion->id],
            '*',
            $strictness = MUST_EXIST
        );
        $qbankentry = $DB->get_record(
            'question_bank_entries',
            ['id' => $qversion->questionbankentryid],
            '*',
            $strictness = MUST_EXIST
        );
        $qcategory = $DB->get_record(
            'question_categories',
            ['id' => $qbankentry->questioncategoryid],
            '*',
            $strictness = MUST_EXIST
        );
        $this->assertEquals($qcategory->id, $createdsubcategory->id);

        $this->assertEquals($returnvalue['questionbankentryid'], $qbankentry->id);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 2);
        $this->assertInstanceOf('\core\event\question_created', $events['0']);
        $this->assertInstanceOf('\core\event\questions_imported', $events['1']);
    }

    /**
     * Test question update.
     */
    public function test_question_update(): void {
        global $DB;
        $this->give_capabilities();
        // Generate question and obtain its QBE id.
        $question = $this->generator->create_question(
            'shortanswer',
            null,
            ['name' => self::QNAME, 'category' => $this->qcategory->id]
        );
        $qbankentryid = $DB->get_field(
            'question_versions',
            'questionbankentryid',
            ['questionid' => $question->id],
            $strictness = MUST_EXIST
        );
        $this->assertEquals(false, $DB->record_exists(
            'question_versions',
            ['questionbankentryid' => $qbankentryid, 'version' => 2]
        ));
        $sink = $this->redirectEvents();
        // Update question.
        $returnvalue = $this->import($this->testrepo . 'top/cat-2/subcat-2_1/Third-Question.xml', '', $qbankentryid, '1', '1');
        // Check version number has increased.
        $this->assertEquals(true, $DB->record_exists(
            'question_versions',
            ['questionbankentryid' => $qbankentryid, 'version' => 2]
        ));

        $this->assertEquals($returnvalue['questionbankentryid'], $qbankentryid);
        $events = $sink->get_events();
        $this->assertEquals(count($events), 1);
        $this->assertInstanceOf('\qbank_importasversion\event\question_version_imported', $events['0']);
    }

    /**
     * Test version check.
     */
    public function test_version_check(): void {
        global $DB;
        $this->give_capabilities();
        // Generate question and obtain its QBE id.
        $question = $this->generator->create_question(
            'shortanswer',
            null,
            ['name' => self::QNAME, 'category' => $this->qcategory->id]
        );
        $qbankentryid = $DB->get_field(
            'question_versions',
            'questionbankentryid',
            ['questionid' => $question->id],
            $strictness = MUST_EXIST
        );
        $this->assertEquals(false, $DB->record_exists(
            'question_versions',
            ['questionbankentryid' => $qbankentryid, 'version' => 2]
        ));
        $sink = $this->redirectEvents();
        // Update question.
        $result = $this->import($this->testrepo . 'top/cat-2/subcat-2_1/Third-Question.xml', '', $qbankentryid, '3', '4');
        $this->assertStringContainsString(
            'Could not import question : Example STACK question ' .
                                      'Current version in Moodle is 1. Last imported version is 3. ' .
                                      'Last exported version is 4. You need to export the question.',
            $result['error']
        );

        // Check version number has not increased.
        $this->assertEquals(true, $DB->record_exists(
            'question_versions',
            ['questionbankentryid' => $qbankentryid, 'version' => 1]
        ));
    }

    /**
     * A failed question is reported and rolled back without stopping the others.
     */
    public function test_independent_results(): void {
        global $DB;
        $this->give_capabilities();
        $question = fn($content, $qcategoryname) => [
            'questionbankentryid' => '', 'importedversion' => '', 'exportedversion' => '',
            'qcategoryname' => $qcategoryname, 'contextlevel' => '70', 'coursename' => $this->course->fullname,
            'modulename' => $this->module->name, 'coursecategory' => '', 'qcategoryid' => '', 'instanceid' => '',
            'content' => $content,
        ];
        $before = $DB->count_records('question');
        $results = import_questions::execute([
            $question(file_get_contents($this->testrepo . 'top/cat-1/gitsync_category.xml'), ''),
            $question(file_get_contents($this->testrepo . 'top/cat-1/First-Question.xml'), 'top/cat 1'),
            $question('This is not XML.', 'top/cat 1'),
            $question(file_get_contents($this->testrepo . 'top/cat-2/Second-Question.xml'), 'top/cat 1'),
        ]);
        $results = external_api::clean_returnvalue(import_questions::execute_returns(), $results);

        $this->assertCount(4, $results);
        $this->assertSame(['', ''], [$results[0]['questionbankentryid'], $results[0]['error']]);
        $this->assertSame('', $results[2]['questionbankentryid']);
        $this->assertNotSame('', $results[2]['error']);
        foreach ([1 => 'First Question', 3 => 'Second Question'] as $index => $name) {
            $this->assertSame('', $results[$index]['error']);
            $this->assertSame('1', $results[$index]['version']);
            $questionid = $DB->get_field('question', 'id', ['name' => $name], MUST_EXIST);
            $this->assertEquals(
                $results[$index]['questionbankentryid'],
                $DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $questionid])
            );
        }
        $this->assertSame($before + 2, $DB->count_records('question'));
    }
}
