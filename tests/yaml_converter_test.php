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
    require_once __DIR__.'/../vendor/autoload.php';
}

/**
 * Tests for YAML converter in yaml_converter.php
 * @group qbank_gitsync
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
       // TODO check the rest of the XML structure.
    }

        public function test_yaml_to_xml()
    {
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

    public function test_array_to_xml_inverse()
    {
        $data = [
            'name' => 'Test',
            'questiontext' => 'What is 2+2?',
            'questiontextformat' => 'moodle',
            'input' => [
                [
                    'name' => 'ans1',
                    'tans' => '1'
                ],
                [
                    'name' => 'ans1',
                    'tans' => '2'
                ]
            ],
            'prt' => [
                [
                    'name' => 'prt1',
                    'value' => '23',
                    'node' => [
                        [
                            'name' => '0',
                            'sans' => '011',
                            'tans' => '022'
                        ],
                        [
                            'name' => '1',
                            'sans' => '033',
                            'tans' => '044'
                        ]
                    ]
                ]
            ]
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

    public function test_obj_diff()
    {
        $a = (object) ['a' => 1, 'b' => 2];
        $b = (object) ['a' => 1, 'b' => 3];
        $diff = yaml_converter::obj_diff($a, $b);
        $this->assertArrayHasKey('b', $diff);
        $this->assertEquals(3, $diff['b']);
    }

    public function test_arr_diff()
    {
        $a = ['x' => 5, 'y' => 6, 'z' => (0.1+0.7)*10, 'a' => [1 => 'x', 2 => 'y']];
        $b = ['x' => 5, 'y' => 7, 'z' => 8, 'a' => [1 => 'x', 2 => 'z']];
        $diff = yaml_converter::arr_diff($a, $b);
        $this->assertEquals(2, count($diff));
        $this->assertArrayHasKey('y', $diff);
        $this->assertEquals(7, $diff['y']);
        $this->assertEquals(1, count($diff['a']));
        $this->assertEquals('z', $diff['a'][2]);
    }

    public function test_get_default()
    {
        $default = yaml_converter::get_default('question', 'name');
        $this->assertEquals('Default', $default);
    }

    public function test_detect_difference()
    {
        if (!defined('Symfony\Component\Yaml\Yaml::DUMP_COMPACT_NESTED_MAPPING')) {
            $this->markTestSkipped('Symfony YAML extension is not available.');
            return;
        }
        $xml = '<quiz><question type="stack"><name><text>Test</text></name></question></quiz>';
        $yaml = yaml_converter::detect_differences($xml, null);
        $this->assertStringContainsString('name: Test', $yaml);
    }

    public function test_detect_difference_yml()
    {
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
            'questiontext' => "<p>Question</p><p>[[input:ans1]] [[validation:ans1]]</p>\n    <p>[[input:ans2]] [[validation:ans2]]</p>\n",
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
                    'showvalidation' => '1'
                ],
                [
                    'name' => 'ans2',
                    'type' => 'algebraic',
                    'tans' => 'ta2',
                    'forbidfloat' => '1',
                    'requirelowestterms' => '0',
                    'checkanswertype' => '0',
                    'mustverify' => '1',
                    'showvalidation' => '1'
                ]
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
                            'quiet' => '1'
                        ]
                    ]
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
                            'falsescore' => '1'
                        ]
                    ]
                ]
            ],
            'deployedseed' => [
                1,
                2,
                3
            ],
            'qtest' => [
                [
                    'testcase' => '1',
                    'description' => 'A test',
                    'testinput' => [
                        [
                            'name' => 'ans1'
                        ],
                        [
                            'name' => 'ans2',
                            'value' => 'ta2'
                        ]
                    ],
                    'expected' => [
                        [
                            'name' => 'prt1',
                            'expectedscore' => '1.0000000',
                            'expectedpenalty' => '0.0000000'
                        ],
                        [
                            'name' => 'prt2',
                            'expectedscore' => '1.0000000',
                            'expectedpenalty' => '0.0000000',
                            'expectedanswernote' => '2-0-T'
                        ]
                    ]
                ]
            ]
        ];
        $expectedstring = "name: 'Test question'\nquestiontext: |\n  <p>Question</p><p>[[input:ans1]] [[validation:ans1]]</p>" .
            "\n      <p>[[input:ans2]] [[validation:ans2]]</p>\nquestionvariables: 'ta1:1;ta2:2;'\nquestionsimplify: '1'\nprtcorrect: '<p>" .
            "<i class=\"fa fa-check\"></i> Correct answer*, well done.</p>'\nmultiplicationsign: cross\ninput:\n  - " .
            "name: ans1\n    type: algebraic\n    tans: ta1\n    boxsize: '25'\n    forbidfloat: '1'\n    " .
            "requirelowestterms: '0'\n    checkanswertype: '0'\n    mustverify: '1'\n    showvalidation: '1'\n  - name: " .
            "ans2\n    type: algebraic\n    tans: ta2\n    forbidfloat: '1'\n    requirelowestterms: '0'\n    " .
            "checkanswertype: '0'\n    mustverify: '1'\n    showvalidation: '1'\nprt:\n  - name: prt1\n    value: '2'\n    autosimplify: '1'\n    feedbackstyle: '1'\n    " .
            "node:\n      - name: '0'\n        answertest: AlgEquiv\n        sans: ans1\n        tans: ta1\n        quiet: '1'\n  - name: prt2\n    " .
            "value: '1.0000001'\n    autosimplify: '1'\n    feedbackstyle: '1'\n    node:\n      - name: '0'\n        answertest: AlgEquiv\n        sans: ans2\n        tans: ta2\n        quiet: '0'\n        falsescore: '1'\n" .
            "deployedseed:\n  - '1'\n  - '2'\n  - '3'\nqtest:\n  - testcase: '1'\n    description: 'A test'\n    " .
            "testinput:\n      - name: ans1\n      - name: ans2\n        value: ta2\n    expected:\n      - name: prt1" .
            "\n        expectedscore: '1.0000000'\n        expectedpenalty: '0.0000000'\n      " .
            "- name: prt2\n        expectedscore: '1.0000000'\n        expectedpenalty:" .
            " '0.0000000'\n        expectedanswernote: 2-0-T\n";
        $this->assertStringContainsString($expectedstring, $diff);
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
                    'showvalidation' => '1'
                ]
            ],
            'prt' => [
                    'name' => 'prt1',
                    'autosimplify' => '1',
                    'feedbackstyle' => '1',
                    'node' => [
                        [
                            'name' => '0',
                            'answertest' => 'AlgEquiv',
                            'sans' => 'ans1',
                            'tans' => 'ta1',
                            'quiet' => '0'
                        ]
                    ]
                ],
        ];
        $diff = yaml_converter::detect_differences($blankxml, null);
        $diffarray = Yaml::parse($diff);
        $this->assertEquals(4, count($diffarray));
        $this->assertEqualsCanonicalizing($expected, $diffarray);

        // Test the difference detection with an info XML question.
        $infoxml = '<quiz><question type="stack"><defaultgrade>0</defaultgrade></question></quiz>';
        $expected = [
            'name' => 'Default',
            'questionsimplify' => '1',
            'defaultgrade' => '0',
            'input' => [],
            'prt' => []
        ];
        $diff = yaml_converter::detect_differences($infoxml, null);
        $diffarray = Yaml::parse($diff);
        $this->assertEquals(5, count($diffarray));

        $this->assertEqualsCanonicalizing($expected, $diffarray);
    }
}
