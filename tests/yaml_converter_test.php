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
 * Unit tests for YAML converter
 *
 * @package    qbank_gitsync
 * @copyright  2025 University of Edinburgh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qbank_gitsync;

defined('MOODLE_INTERNAL') || die();
use Symfony\Component\Yaml\Yaml;
if (is_file(__DIR__.'/../vendor/autoload.php')) {
    require_once(__DIR__.'/../vendor/autoload.php');
}

/**
 * Tests for YAML converter in yaml_converter.php
 * @group qbank_gitsync
 * @covers \qbank_gitsync
 */
final class yaml_converter_test extends \advanced_testcase {

    public function test_loadxml(): void {
        if (!defined('Symfony\Component\Yaml\Yaml::DUMP_COMPACT_NESTED_MAPPING')) {
            $this->markTestSkipped('Symfony YAML extension is not available.');
            return;
        }
        $defaults = Yaml::parseFile(__DIR__ . '/../questiondefaults.yml');
        $questionyaml = file_get_contents(__DIR__ . '/fixtures/fullquestion.yml');
        $xml = \qbank_gitsync\yaml_converter::loadyaml($questionyaml , $defaults);
        $this->assertEquals('Test question', (string) $xml->question->name->text);
        $this->assertEquals(1,
            preg_match('/<p>Question<\/p><p>\[\[input:ans1\]\] \[\[validation:ans1\]\]<\/p>\n    <p>' .
                '\[\[input:ans2\]\] \[\[validation:ans2\]\]<\/p>/s', (string) $xml->question->questiontext->text));
        $this->assertEquals('html', (string) $xml->question->questiontext['format']);
        $this->assertEquals(false, isset($xml->question->questiontext->format));
        $this->assertEquals('', (string) $xml->question->generalfeedback->text);
        $this->assertEquals('html', (string) $xml->question->generalfeedback['format']);
        $this->assertEquals('1', (string) $xml->question->defaultgrade);
        $this->assertEquals('0.1', (string) $xml->question->penalty);
        $this->assertEquals('0', (string) $xml->question->hidden);
        $this->assertEquals('', (string) $xml->question->idnumber);
        $this->assertEquals('2025042500', (string) $xml->question->stackversion->text);
        $this->assertEquals('ta1:1;ta2:2;', (string) $xml->question->questionvariables->text);
        $this->assertEquals('<p>[[feedback:prt1]]</p>', (string) $xml->question->specificfeedback->text);
        $this->assertEquals('html', (string) $xml->question->specificfeedback['format']);
        $this->assertEquals('<p>{@ta1@}</p>', (string) $xml->question->questionnote->text);
        $this->assertEquals('html', (string) $xml->question->questionnote['format']);
        $this->assertEquals('', (string) $xml->question->questiondescription->text);
        $this->assertEquals('html', (string) $xml->question->questiondescription['format']);

        $this->assertEquals('1', (string) $xml->question->questionsimplify);
        $this->assertEquals('0', (string) $xml->question->assumepositive);
        $this->assertEquals('0', (string) $xml->question->assumepositive);
        $this->assertEquals('<p><i class="fa fa-check"></i> Correct answer*, well done.</p>',
            (string) $xml->question->prtcorrect->text);
        $this->assertEquals('html', (string) $xml->question->prtcorrect['format']);
        $this->assertEquals('<p><i class="fa fa-adjust"></i> Your answer is partially correct.</p>',
            (string) $xml->question->prtpartiallycorrect->text);
        $this->assertEquals('html', (string) $xml->question->prtpartiallycorrect['format']);
        $this->assertEquals('<p><i class="fa fa-times"></i> Incorrect answer.</p>',
            (string) $xml->question->prtincorrect->text);
        $this->assertEquals('html', (string) $xml->question->prtincorrect['format']);
        $this->assertEquals('.', (string) $xml->question->decimals);
        $this->assertEquals('*10', (string) $xml->question->scientificnotation);
        $this->assertEquals('cross', (string) $xml->question->multiplicationsign);
        $this->assertEquals('1', (string) $xml->question->sqrtsign);
        $this->assertEquals('i', (string) $xml->question->complexno);
        $this->assertEquals('cos-1', (string) $xml->question->inversetrig);
        $this->assertEquals('lang', (string) $xml->question->logicsymbol);
        $this->assertEquals('[', (string) $xml->question->matrixparens);
        $this->assertEquals('0', (string) $xml->question->isbroken);
        $this->assertEquals('', (string) $xml->question->variantsselectionseed);

        // Check input fields.
        $this->assertCount(2, $xml->question->input);
        $input1 = $xml->question->input[0];
        $input2 = $xml->question->input[1];
        $this->assertEquals('ans1', (string) $input1->name);
        $this->assertEquals('algebraic', (string) $input1->type);
        $this->assertEquals('ta1', (string) $input1->tans);
        $this->assertEquals('25', (string) $input1->boxsize);
        $this->assertEquals('1', (string) $input1->strictsyntax);
        $this->assertEquals('0', (string) $input1->insertstars);
        $this->assertEquals('', (string) $input1->syntaxhint);
        $this->assertEquals('0', (string) $input1->syntaxattribute);
        $this->assertEquals('', (string) $input1->forbidwords);
        $this->assertEquals('', (string) $input1->allowwords);
        $this->assertEquals('1', (string) $input1->forbidfloat);
        $this->assertEquals('0', (string) $input1->requirelowestterms);
        $this->assertEquals('0', (string) $input1->checkanswertype);
        $this->assertEquals('1', (string) $input1->mustverify);
        $this->assertEquals('1', (string) $input1->showvalidation);
        $this->assertEquals('', (string) $input1->options);

        $this->assertEquals('ans2', (string) $input2->name);
        $this->assertEquals('algebraic', (string) $input2->type);
        $this->assertEquals('ta2', (string) $input2->tans);
        $this->assertEquals('1', (string) $input2->forbidfloat);
        $this->assertEquals('0', (string) $input2->requirelowestterms);
        $this->assertEquals('0', (string) $input2->checkanswertype);
        $this->assertEquals('1', (string) $input2->mustverify);
        $this->assertEquals('1', (string) $input2->showvalidation);

        // Check prt fields.
        $this->assertCount(2, $xml->question->prt);
        $prt1 = $xml->question->prt[0];
        $prt2 = $xml->question->prt[1];
        $this->assertEquals('prt1', (string) $prt1->name);
        $this->assertEquals('2', (string) $prt1->value);
        $this->assertEquals('1', (string) $prt1->autosimplify);
        $this->assertEquals('1', (string) $prt1->feedbackstyle);
        $this->assertEquals('', (string) $prt1->feedbackvariables);
        $this->assertCount(1, $prt1->node);
        $this->assertEquals('0', (string) $prt1->node[0]->name);
        $this->assertEquals('', (string) $prt1->node[0]->description);
        $this->assertEquals('AlgEquiv', (string) $prt1->node[0]->answertest);
        $this->assertEquals('ans1', (string) $prt1->node[0]->sans);
        $this->assertEquals('ta1', (string) $prt1->node[0]->tans);
        $this->assertEquals('', (string) $prt1->node[0]->testoptions);
        $this->assertEquals('1', (string) $prt1->node[0]->quiet);
        $this->assertEquals('=', (string) $prt1->node[0]->truescoremode);
        $this->assertEquals('1', (string) $prt1->node[0]->truescore);
        $this->assertEquals('', (string) $prt1->node[0]->truepenalty);
        $this->assertEquals('-1', (string) $prt1->node[0]->truenextnode);
        $this->assertEquals('prt1-1-T', (string) $prt1->node[0]->trueanswernote);
        $this->assertEquals('', (string) $prt1->node[0]->truefeedback->text);
        $this->assertEquals('html', (string) $prt1->node[0]->truefeedback['format']);
        $this->assertEquals('=', (string) $prt1->node[0]->falsescoremode);
        $this->assertEquals('0', (string) $prt1->node[0]->falsescore);
        $this->assertEquals('', (string) $prt1->node[0]->falsepenalty);
        $this->assertEquals('-1', (string) $prt1->node[0]->falsenextnode);
        $this->assertEquals('prt1-1-F', (string) $prt1->node[0]->falseanswernote);
        $this->assertEquals('', (string) $prt1->node[0]->falsefeedback->text);
        $this->assertEquals('html', (string) $prt1->node[0]->falsefeedback['format']);

        $this->assertEquals('prt2', (string) $prt2->name);
        $this->assertEquals('1.0000001', (string) $prt2->value);
        $this->assertEquals('1', (string) $prt2->autosimplify);
        $this->assertEquals('1', (string) $prt2->feedbackstyle);
        $this->assertCount(1, $prt2->node);
        $this->assertEquals('0', (string) $prt2->node[0]->name);
        $this->assertEquals('AlgEquiv', (string) $prt2->node[0]->answertest);
        $this->assertEquals('ans2', (string) $prt2->node[0]->sans);
        $this->assertEquals('ta2', (string) $prt2->node[0]->tans);
        $this->assertEquals('0', (string) $prt2->node[0]->quiet);
        $this->assertEquals('1', (string) $prt2->node[0]->falsescore);

        // Check deployedseed.
        $this->assertCount(3, $xml->question->deployedseed);
        $this->assertEquals('1', (string) $xml->question->deployedseed[0]);
        $this->assertEquals('2', (string) $xml->question->deployedseed[1]);
        $this->assertEquals('3', (string) $xml->question->deployedseed[2]);

        // Check qtest.
        $this->assertCount(1, $xml->question->qtest);
        $qtest = $xml->question->qtest[0];
        $this->assertEquals('1', (string) $qtest->testcase);
        $this->assertEquals('A test', (string) $qtest->description);
        $this->assertCount(2, $qtest->testinput);
        $this->assertEquals('ans1', (string) $qtest->testinput[0]->name);
        $this->assertEquals('ta1', (string) $qtest->testinput[0]->value);
        $this->assertEquals('ans2', (string) $qtest->testinput[1]->name);
        $this->assertEquals('ta2', (string) $qtest->testinput[1]->value);
        $this->assertCount(2, $qtest->expected);
        $this->assertEquals('prt1', (string) $qtest->expected[0]->name);
        $this->assertEquals('1.0000000', (string) $qtest->expected[0]->expectedscore);
        $this->assertEquals('0.0000000', (string) $qtest->expected[0]->expectedpenalty);
        $this->assertEquals('1-0-T', (string) $qtest->expected[0]->expectedanswernote);
        $this->assertEquals('prt2', (string) $qtest->expected[1]->name);
        $this->assertEquals('1.0000000', (string) $qtest->expected[1]->expectedscore);
        $this->assertEquals('0.0000000', (string) $qtest->expected[1]->expectedpenalty);
        $this->assertEquals('2-0-T', (string) $qtest->expected[1]->expectedanswernote);
    }

    public function test_loadxml_summary_default(): void {
        if (!defined('Symfony\Component\Yaml\Yaml::DUMP_COMPACT_NESTED_MAPPING')) {
            $this->markTestSkipped('Symfony YAML extension is not available.');
            return;
        }
        $defaults = Yaml::parseFile(__DIR__ . '/fixtures/questiondefaultssugar.yml');
        $questionyaml = file_get_contents(__DIR__ . '/fixtures/fullquestion.yml');
        $xml = \qbank_gitsync\yaml_converter::loadyaml($questionyaml , $defaults);

        // Check prt fields.
        $this->assertCount(2, $xml->question->prt);
        $prt1 = $xml->question->prt[0];
        $prt2 = $xml->question->prt[1];
        $this->assertCount(1, $prt1->node);
        $this->assertEquals('0', (string) $prt1->node[0]->name);
        $this->assertEquals('', (string) $prt1->node[0]->description);
        $this->assertEquals('AlgEquiv', (string) $prt1->node[0]->answertest);
        $this->assertEquals('ans1', (string) $prt1->node[0]->sans);
        $this->assertEquals('ta1', (string) $prt1->node[0]->tans);
        $this->assertEquals('', (string) $prt1->node[0]->testoptions);
        $this->assertEquals('1', (string) $prt1->node[0]->quiet);
        $this->assertEquals('=', (string) $prt1->node[0]->truescoremode);

        $this->assertCount(1, $prt2->node);
        $this->assertEquals('0', (string) $prt2->node[0]->name);
        $this->assertEquals('AlgEquiv', (string) $prt2->node[0]->answertest);
        $this->assertEquals('ans2', (string) $prt2->node[0]->sans);
        $this->assertEquals('ta2', (string) $prt2->node[0]->tans);
        $this->assertEquals('0', (string) $prt2->node[0]->quiet);
        $this->assertEquals('1', (string) $prt2->node[0]->falsescore);
    }


    public function test_loadxml_summary(): void {
        if (!defined('Symfony\Component\Yaml\Yaml::DUMP_COMPACT_NESTED_MAPPING')) {
            $this->markTestSkipped('Symfony YAML extension is not available.');
            return;
        }
        $defaults = Yaml::parseFile(__DIR__ . '/fixtures/questiondefaultssugar.yml');
        $questionyaml = file_get_contents(__DIR__ . '/fixtures/fullquestionsummary.yml');
        $xml = \qbank_gitsync\yaml_converter::loadyaml($questionyaml , $defaults);

        // Check prt fields.
        $this->assertCount(2, $xml->question->prt);
        $prt1 = $xml->question->prt[0];
        $prt2 = $xml->question->prt[1];
        $this->assertCount(1, $prt1->node);
        $this->assertEquals('0', (string) $prt1->node[0]->name);
        $this->assertEquals('', (string) $prt1->node[0]->description);
        $this->assertEquals('Diff', (string) $prt1->node[0]->answertest);
        $this->assertEquals('ans1', (string) $prt1->node[0]->sans);
        $this->assertEquals('diff(p,v)', (string) $prt1->node[0]->tans);
        $this->assertEquals('v', (string) $prt1->node[0]->testoptions);
        $this->assertEquals('1', (string) $prt1->node[0]->quiet);
        $this->assertEquals('=', (string) $prt1->node[0]->truescoremode);

        $this->assertCount(1, $prt2->node);
        $this->assertEquals('0', (string) $prt2->node[0]->name);
        $this->assertEquals('AlgEquiv', (string) $prt2->node[0]->answertest);
        $this->assertEquals('ans2', (string) $prt2->node[0]->sans);
        $this->assertEquals('-rdm*sin(v)', (string) $prt2->node[0]->tans);
        $this->assertEquals('', (string) $prt2->node[0]->testoptions);
        $this->assertEquals('0', (string) $prt2->node[0]->quiet);
        $this->assertEquals('1', (string) $prt2->node[0]->falsescore);
    }

    public function test_yaml_to_xml(): void {
        if (!defined('Symfony\Component\Yaml\Yaml::DUMP_COMPACT_NESTED_MAPPING')) {
            $this->markTestSkipped('Symfony YAML extension is not available.');
            return;
        }
        $yaml = file_get_contents(__DIR__ . '/fixtures/fullquestion.yml');
        $xml = yaml_converter::yaml_to_xml($yaml);
        $this->assertEquals('Test question', (string)$xml->question->name->text);
        $this->assertEquals(1,
            preg_match('/<p>Question<\/p><p>\[\[input:ans1\]\] \[\[validation:ans1\]\]<\/p>\n    <p>' .
                '\[\[input:ans2\]\] \[\[validation:ans2\]\]<\/p>/s', (string) $xml->question->questiontext->text));
        $this->assertEquals('html', (string)$xml->question->questiontext['format']);
        $this->assertEquals(false, isset($xml->question->questiontext->format));
    }

    public function test_array_to_xml_inverse(): void {
        $data = [
            'name' => 'Test',
            'questiontext' => 'What is 2+2?',
            'questiontextformat' => 'moodle',
            'input' => [
                [
                    'name' => 'ans1',
                    'tans' => '1',
                ],
                [
                    'name' => 'ans1',
                    'tans' => '2',
                ],
            ],
            'prt' => [
                [
                    'name' => 'prt1',
                    'value' => '23',
                    'node' => [
                        [
                            'name' => '0',
                            'sans' => '011',
                            'tans' => '022',
                        ],
                        [
                            'name' => '1',
                            'sans' => '033',
                            'tans' => '044',
                        ],
                    ],
                ],
            ],
        ];
        $xml = new \SimpleXMLElement('<question></question>');
        yaml_converter::array_to_xml($data, $xml);
        $this->assertEquals('Test', $xml->name);
        $this->assertEquals('What is 2+2?', $xml->questiontext->text);
        $this->assertEquals('moodle', $xml->questiontext['format']);
        $this->assertEquals(2, count($xml->input));
        $this->assertEquals(1, count($xml->prt));
        $this->assertEquals('prt1', $xml->prt->name);
        $this->assertEquals(2, count($xml->prt[0]->node));
        $this->assertEquals('1', $xml->prt[0]->node[1]->name);
        $this->assertEquals('033', $xml->prt[0]->node[1]->sans);
        $this->assertEquals('044', $xml->prt[0]->node[1]->tans);
        $array = yaml_converter::xml_to_array($xml);
        $this->assertEqualsCanonicalizing($data, $array);
    }

    public function test_obj_diff(): void {
        $a = (object) ['a' => 1, 'b' => 2];
        $b = (object) ['a' => 1, 'b' => 3];
        $diff = yaml_converter::obj_diff($a, $b);
        $this->assertArrayHasKey('b', $diff);
        $this->assertEquals(3, $diff['b']);
    }

    public function test_arr_diff(): void {
        $a = ['x' => 5, 'y' => 6, 'z' => (0.1 + 0.7) * 10, 'a' => [1 => 'x', 2 => 'y']];
        $b = ['x' => 5, 'y' => 7, 'z' => 8, 'a' => [1 => 'x', 2 => 'z']];
        $diff = yaml_converter::arr_diff($a, $b);
        $this->assertEquals(2, count($diff));
        $this->assertArrayHasKey('y', $diff);
        $this->assertEquals(7, $diff['y']);
        $this->assertEquals(1, count($diff['a']));
        $this->assertEquals('z', $diff['a'][2]);
    }

    public function test_get_default(): void {
        if (!defined('Symfony\Component\Yaml\Yaml::DUMP_COMPACT_NESTED_MAPPING')) {
            $this->markTestSkipped('Symfony YAML extension is not available.');
            return;
        }
        $default = yaml_converter::get_default('question', 'name');
        $this->assertEquals('Default', $default);
    }

    public function test_detect_difference(): void {
        if (!defined('Symfony\Component\Yaml\Yaml::DUMP_COMPACT_NESTED_MAPPING')) {
            $this->markTestSkipped('Symfony YAML extension is not available.');
            return;
        }
        $xml = '<quiz><question type="stack"><name><text>Test</text></name></question></quiz>';
        $yaml = yaml_converter::detect_differences($xml, null);
        $this->assertStringContainsString('name: Test', $yaml);
    }

    public function test_detect_difference_yml(): void {
        if (!defined('Symfony\Component\Yaml\Yaml::DUMP_COMPACT_NESTED_MAPPING')) {
            $this->markTestSkipped('Symfony YAML extension is not available.');
            return;
        }
        // Test the difference detection with a full question.
        $yaml = file_get_contents(__DIR__ . '/fixtures/fullquestion.yml');
        $diff = yaml_converter::detect_differences($yaml, null);
        $diffarray = Yaml::parse($diff);
        $this->assertEquals(10, count($diffarray));
        $expected = [
            'name' => 'Test question',
            'questiontext' => "<p>Question</p><p>[[input:ans1]] [[validation:ans1]]</p>\n    <p>[[input:ans2]] " .
                "[[validation:ans2]]</p>\n",
            'questionvariables' => 'ta1:1;ta2:2;',
            'questionsimplify' => '1',
            'prtcorrect' => '<p><i class="fa fa-check"></i> Correct answer*, well done.</p>',
            'multiplicationsign' => 'cross',
            'input' => [
                [
                    'name' => 'ans1',
                    'type' => 'algebraic',
                    'tans' => 'ta1',
                    'boxsize' => 25,
                    'forbidfloat' => '1',
                    'requirelowestterms' => '0',
                    'checkanswertype' => '0',
                    'mustverify' => '1',
                    'showvalidation' => '1',
                ],
                [
                    'name' => 'ans2',
                    'type' => 'algebraic',
                    'tans' => 'ta2',
                    'forbidfloat' => '1',
                    'requirelowestterms' => '0',
                    'checkanswertype' => '0',
                    'mustverify' => '1',
                    'showvalidation' => '1',
                ],
            ],
            'prt' => [
                [
                    'name' => 'prt1',
                    'value' => '2',
                    'autosimplify' => '1',
                    'feedbackstyle' => '1',
                    'node' => [
                        [
                            'name' => '0',
                            'answertest' => 'AlgEquiv',
                            'sans' => 'ans1',
                            'tans' => 'ta1',
                            'quiet' => '1',
                        ],
                    ],
                ],
                [
                    'name' => 'prt2',
                    'value' => '1.0000001',
                    'autosimplify' => '1',
                    'feedbackstyle' => '1',
                    'node' => [
                        [
                            'name' => '0',
                            'answertest' => 'AlgEquiv',
                            'sans' => 'ans2',
                            'tans' => 'ta2',
                            'quiet' => '0',
                            'falsescore' => '1',
                        ],
                    ],
                ],
            ],
            'deployedseed' => [
                1,
                2,
                3,
            ],
            'qtest' => [
                [
                    'testcase' => '1',
                    'description' => 'A test',
                    'testinput' => [
                        [
                            'name' => 'ans1',
                        ],
                        [
                            'name' => 'ans2',
                            'value' => 'ta2',
                        ],
                    ],
                    'expected' => [
                        [
                            'name' => 'prt1',
                            'expectedscore' => '1.0000000',
                            'expectedpenalty' => '0.0000000',
                        ],
                        [
                            'name' => 'prt2',
                            'expectedscore' => '1.0000000',
                            'expectedpenalty' => '0.0000000',
                            'expectedanswernote' => '2-0-T',
                        ],
                    ],
                ],
            ],
        ];
        $expectedstring = "name: 'Test question'\nquestiontext: |\n  <p>Question</p><p>[[input:ans1]] [[validation:ans1]]</p>" .
            "\n      <p>[[input:ans2]] [[validation:ans2]]</p>\nquestionvariables: 'ta1:1;ta2:2;" .
            "'\nquestionsimplify: '1'\nprtcorrect: '<p>" .
            "<i class=\"fa fa-check\"></i> Correct answer*, well done.</p>'\nmultiplicationsign: cross\ninput:\n  - " .
            "name: ans1\n    type: algebraic\n    tans: ta1\n    boxsize: '25'\n    forbidfloat: '1'\n    " .
            "requirelowestterms: '0'\n    checkanswertype: '0'\n    mustverify: '1'\n    showvalidation: '1'\n  - name: " .
            "ans2\n    type: algebraic\n    tans: ta2\n    forbidfloat: '1'\n    requirelowestterms: '0'\n    " .
            "checkanswertype: '0'\n    mustverify: '1'\n    showvalidation: '1'\nprt:\n" .
            "  - name: prt1\n    value: '2'\n    autosimplify: '1'\n    feedbackstyle: '1'\n    " .
            "node:\n      - name: '0'\n        answertest: AlgEquiv\n        sans: ans1\n        tans: ta1\n" .
            "        quiet: '1'\n  - name: prt2\n    " .
            "value: '1.0000001'\n    autosimplify: '1'\n    feedbackstyle: '1'\n    node:\n      - name: '0'\n" .
            "        answertest: AlgEquiv\n        sans: ans2\n        tans: ta2\n        quiet: '0'\n        falsescore: '1'\n" .
            "deployedseed:\n  - '1'\n  - '2'\n  - '3'\nqtest:\n  - testcase: '1'\n    description: 'A test'\n    " .
            "testinput:\n      - name: ans1\n      - name: ans2\n        value: ta2\n    expected:\n      - name: prt1" .
            "\n        expectedscore: '1.0000000'\n        expectedpenalty: '0.0000000'\n      " .
            "- name: prt2\n        expectedscore: '1.0000000'\n        expectedpenalty:" .
            " '0.0000000'\n        expectedanswernote: 2-0-T\n";
        $this->assertStringContainsString($expectedstring, $diff);
        $this->assertEqualsCanonicalizing($expected, $diffarray);

        // Check results when using answertest summary in defaults.
        $diff = yaml_converter::detect_differences(
            $yaml,
            yaml_converter::load_defaults(__DIR__ . '/fixtures/questiondefaultssugar.yml')
        );
        $diffarray = Yaml::parse($diff);
        $this->assertEquals(10, count($diffarray));
        $expected['prt'][0]['node'][0] = [
                            'name' => '0',
                            'answertest' => 'ATAlgEquiv(ans1,ta1)',
                            'quiet' => '1',
        ];
        $expected['prt'][1]['node'][0] = [
                            'name' => '0',
                            'answertest' => 'ATAlgEquiv(ans2,ta2)',
                            'quiet' => '0',
                            'falsescore' => '1',
        ];
        $this->assertEqualsCanonicalizing($expected, $diffarray);

        // Test the difference detection with a completely default XML question.
        $blankxml = '<quiz><question type="stack"></question></quiz>';
        $expected = [
            'name' => 'Default',
            'questionsimplify' => '1',
            'input' => [
                [
                    'name' => 'ans1',
                    'type' => 'algebraic',
                    'tans' => 'ta1',
                    'forbidfloat' => '1',
                    'requirelowestterms' => '0',
                    'checkanswertype' => '0',
                    'mustverify' => '1',
                    'showvalidation' => '1',
                ],
            ],
            'prt' => [
                [
                    'name' => 'prt1',
                    'autosimplify' => '1',
                    'feedbackstyle' => '1',
                    'node' => [
                        [
                            'name' => '0',
                            'answertest' => 'AlgEquiv',
                            'sans' => 'ans1',
                            'tans' => 'ta1',
                            'quiet' => '0',
                        ],
                    ],
                ],
            ],
        ];
        $diff = yaml_converter::detect_differences($blankxml, null);
        $diffarray = Yaml::parse($diff);
        $this->assertEquals(4, count($diffarray));
        $this->assertEqualsCanonicalizing($expected, $diffarray);

        // Check results when using answertest summary in defaults.
        $diff = yaml_converter::detect_differences(
            $blankxml,
            yaml_converter::load_defaults(__DIR__ . '/fixtures/questiondefaultssugar.yml')
        );
        $diffarray = Yaml::parse($diff);
        $this->assertEquals(4, count($diffarray));
        $expected['prt'][0]['node'][0] = [
                            'name' => '0',
                            'answertest' => 'ATAlgEquiv(ans1,ta1)',
                            'quiet' => '0',
        ];
        $this->assertEqualsCanonicalizing($expected, $diffarray);

        // Test the difference detection with an info XML question.
        $infoxml = '<quiz><question type="stack"><defaultgrade>0</defaultgrade></question></quiz>';
        $expected = [
            'name' => 'Default',
            'questionsimplify' => '1',
            'defaultgrade' => '0',
            'input' => [],
            'prt' => [],
        ];
        $diff = yaml_converter::detect_differences($infoxml, null);
        $diffarray = Yaml::parse($diff);
        $this->assertEquals(5, count($diffarray));

        $this->assertEqualsCanonicalizing($expected, $diffarray);

        // Check results when using answertest summary in defaults.
        $diff = yaml_converter::detect_differences(
            $infoxml,
            yaml_converter::load_defaults(__DIR__ . '/fixtures/questiondefaultssugar.yml')
        );
        $diffarray = Yaml::parse($diff);
        $this->assertEquals(5, count($diffarray));
        $this->assertEqualsCanonicalizing($expected, $diffarray);
    }

    public function test_split_answertest_basic(): void {
        $input = 'ATAlgEquiv(x^2+2x+1, (x+1)^2, 1, ignoreorder)';
        $expected = [
            'ATAlgEquiv',
            'x^2+2x+1',
            '(x+1)^2',
            '1, ignoreorder',
        ];
        $this->assertEquals($expected, yaml_converter::split_answertest($input));
    }

    public function test_split_answertest_nested_parentheses(): void {
        $input = 'ATTest(foo(bar, baz), qux, quux, corge)';
        $expected = [
            'ATTest',
            'foo(bar, baz)',
            'qux',
            'quux, corge',
        ];
        $this->assertEquals($expected, yaml_converter::split_answertest($input));
    }

    public function test_split_answertest_missing_items(): void {
        $input = 'ATTest(foo)';
        $expected = [
            'ATTest',
            'foo',
            '',
            '',
        ];
        $this->assertEquals($expected, yaml_converter::split_answertest($input));
    }

    public function test_split_answertest_extra_commas(): void {
        $input = 'ATTest(foo, bar, baz, qux, quux)';
        $expected = [
            'ATTest',
            'foo',
            'bar',
            'baz, qux, quux',
        ];
        $this->assertEquals($expected, yaml_converter::split_answertest($input));
    }

    public function test_split_answertest_spaces(): void {
        $input = 'ATTest( foo ( bar ), baz , qux )';
        $expected = [
            'ATTest',
            'foo ( bar )',
            'baz',
            'qux',
        ];
        $this->assertEquals($expected, yaml_converter::split_answertest($input));
    }
}
