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
 * This script handles conversion between XML questions and YAML fragments.
 *
 * @package    qbank_gitsync
 * @copyright  2025 University of Edinburgh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

namespace qbank_gitsync;
use SimpleXMLElement;
use Symfony\Component\Yaml\Yaml;

class yaml_converter {
    public static $defaults = null;
    public const TEXTFIELDS = [
        'name', 'questiontext', 'generalfeedback', 'stackversion', 'questionvariables',
        'specificfeedback', 'questionnote',
        'questiondescription', 'prtcorrect', 'prtpartiallycorrect', 'prtincorrect',
        'feedbackvariables', 'truefeedback', 'falsefeedback'
    ];
    public const ARRAYFIELDS = [
        'input', 'prt', 'node', 'deployedseed', 'qtest', 'testinput', 'expected'
    ];

    // phpcs:ignore moodle.Commenting.MissingDocblock.Function
    public static function loadyaml($yaml, $defaultsfile) {
        self::$defaults = yaml_parse_file($defaultsfile);
        try {
            $xmldata = self::yaml_to_xml($yaml);
        } catch (\Exception $e) {
            throw new \Exception("The provided file does not contain valid YAML");
        }
        $question = $xmldata->question;

        // Based on Moodle's base question type.
        $question->name->text = (string) $question->name->text ?
            $question->name->text :
            self::set_field($question, 'name->text', self::get_default('question', 'name'));
        $question->questiontext->text = (string) $question->questiontext->text ?
            $question->questiontext->text :
            self::set_field($question, 'questiontext->text', self::get_default(
                'question', 'questiontext'
            ));
        $question->questiontext['format'] = (string) $question->questiontext['format'] ?
            $question->questiontext['format'] :
            self::get_default('question', 'questiontextformat');
        $question->generalfeedback->text = (string) $question->generalfeedback->text ?
            $question->generalfeedback->text :
            self::set_field($question, 'generalfeedback->text', self::get_default('question', 'generalfeedback'));
        $question->generalfeedback['format'] = (string) $question->generalfeedback['format'] ?
            $question->generalfeedback['format'] :
            self::get_default('question', 'generalfeedbackformat');
        $question->defaultgrade = (array) $question->defaultgrade ?
            $question->defaultgrade :
            self::set_field($question, 'defaultgrade', self::get_default('question', 'defaultgrade'));
        $question->penalty = (array) $question->penalty ?
            $question->penalty :
            self::set_field($question, 'penalty', self::get_default('question', 'penalty'));

        // Based on initialise_question_instance from questiontype.php.
        $question->stackversion = (string) $question->stackversion->text ?
            $question->stackversion->text :
            self::set_field($question, 'stackversion', self::get_default('question', 'stackversion'));
        $question->questionvariables = (string) $question->questionvariables->text ?
            $question->questionvariables->text :
            self::set_field($question, 'questionvariables', self::get_default('question', 'questionvariables'));
        $question->questionnote->text = (string) $question->questionnote->text ?
            $question->questionnote->text :
            self::set_field($question, 'questionnote', self::get_default('question', 'questionnote'));
        $question->questionnote['format'] = (string) $question->questionnote['format'] ?
            $question->questionnote['format'] :
            self::get_default('question', 'questionnoteformat');
        $question->specificfeedback->text = (string) $question->specificfeedback->text ?
            $question->specificfeedback->text :
            self::set_field($question, 'specificfeedback', self::get_default('question', 'specificfeedback'));
        $question->specificfeedback['format'] = (string) $question->specificfeedback['format'] ?
            $question->specificfeedback['format'] :
            self::get_default('question', 'specificfeedbackformat');
        $question->questiondescription->text = (string) $question->questiondescription->text ?
            $question->questiondescription->text :
            self::set_field($question, 'questiondescription', self::get_default('question', 'questiondescription'));
        $question->questiondescription['format'] = (string) $question->questiondescription['format'] ?
            $question->questiondescription['format'] :
            self::get_default('question', 'questiondescriptionformat');
        $question->prtcorrect = (string) $question->prtcorrect->text ?
            $question->prtcorrect->text :
            self::set_field($question, 'prtcorrect', self::get_default('question', 'prtcorrect'));
        $question->prtcorrect['format'] = (string) $question->prtcorrect['format'] ?
            $question->prtcorrect['format'] :
            self::get_default('question', 'prtcorrectformat');
        $question->prtpartiallycorrect = (string) $question->prtpartiallycorrect->text ?
            $question->prtpartiallycorrect->text :
            self::set_field($question, 'prtpartiallycorrect', self::get_default('question', 'prtpartiallycorrect'));
        $question->prtpartiallycorrect['format'] = (string) $question->prtpartiallycorrect['format'] ?
            $question->prtpartiallycorrect['format'] :
            self::get_default('question', 'prtpartiallycorrectformat');
        $question->prtincorrect = (string) $question->prtincorrect->text ?
            $question->prtincorrect->text :
            self::set_field($question, 'prtincorrect', self::get_default('question', 'prtincorrect'));
        $question->prtincorrect['format'] = (string) $question->prtincorrect['format'] ?
            $question->prtincorrect['format'] :
            self::get_default('question', 'prtincorrectformat');
        $question->variantsselectionseed = (string) $question->variantsselectionseed ?
            $question->variantsselectionseed :
            self::set_field($question, 'variantsselectionseed', self::get_default('question', 'variantsselectionseed'));
        $question->isbroken = (array) $question->isbroken ?
            $question->isbroken :
            self::set_field($question, 'isbroken', self::get_default('question', 'isbroken'));
        $question->multiplicationsign =
            (string) $question->multiplicationsign ?
            $question->multiplicationsign :
            self::set_field($question, 'multiplicationsign', self::get_default('question', 'multiplicationsign'));
        $question->complexno =
            (string) $question->complexno ?
            $question->complexno :
            self::set_field($question, 'complexno', self::get_default('question', 'complexno'));
        $question->inversetrig =
            (string) $question->inversetrig ?
            $question->inversetrig :
            self::set_field($question, 'inversetrig', self::get_default('question', 'inversetrig'));
        $question->logicsymbol =
            (string) $question->logicsymbol ?
            $question->logicsymbol :
            self::set_field($question, 'logicsymbol', self::get_default('question', 'logicsymbol'));
        $question->matrixparens =
            (string) $question->matrixparens ?
            $question->matrixparens :
            self::set_field($question, 'matrixparens', self::get_default('question', 'matrixparens'));
        $question->sqrtsign =
            (string) $question->sqrtsign ?
            $question->sqrtsign :
            self::set_field($question, 'sqrtsign', self::get_default('question', 'sqrtsign'));
        $question->simplify =
            (string) $question->questionsimplify ?
            $question->questionsimplify :
            self::set_field($question, 'simplify', self::get_default('question', 'questionsimplify'));
        $question->assumepos =
            (string) $question->assumepositive ?
            $question->assumepositive :
            self::set_field($question, 'assumepos', self::get_default('question', 'assumepositive'));
        $question->assumereal =
            (string) $question->assumereal ?
            $question->assumereal :
            self::set_field($question, 'assumereal', self::get_default('question', 'assumereal'));
        $question->decimals =
            (string) $question->decimals ?
            $question->decimals :
            self::set_field($question, 'decimals', self::get_default('question', 'decimals'));
        $question->scientificnotation =
            (string) $question->scientificnotation ?
            $question->scientificnotation :
            self::set_field($question, 'scientificnotation', self::get_default('question', 'scientificnotation'));

        foreach ($question->input as $inputdata) {
            $inputdata->boxsize = (array) $inputdata->boxsize ?
                $inputdata->boxsize :
                self::set_field($inputdata, 'boxsize', self::get_default('input', 'boxsize'));
            $inputdata->insertstars = (array) $inputdata->insertstars ?
                $inputdata->insertstars :
                self::set_field($inputdata, 'insertstars', self::get_default('input', 'insertstars'));
            $inputdata->syntaxhint = isset($inputdata->syntaxhint) ?
                $inputdata->syntaxhint :
                self::set_field($inputdata, 'syntaxhint', self::get_default('input', 'syntaxhint'));
            $inputdata->syntaxattribute = (array) $inputdata->syntaxattribute ?
                $inputdata->syntaxattribute :
                self::set_field($inputdata, 'syntaxattribute', self::get_default('input', 'syntaxattribute'));
            $inputdata->forbidwords = isset($inputdata->forbidwords) ?
                $inputdata->forbidwords :
                self::set_field($inputdata, 'forbidwords', self::get_default('input', 'forbidwords'));
            $inputdata->allowwords = isset($inputdata->allowwords) ?
                $inputdata->allowwords :
                self::set_field($inputdata, 'allowwords', self::get_default('input', 'allowwords'));
            $inputdata->forbidfloat = (array) $inputdata->forbidfloat ?
                $inputdata->forbidfloat :
                self::set_field($inputdata, 'forbidfloat', self::get_default('input', 'forbidfloat'));
            $inputdata->requirelowestterms = (array) $inputdata->requirelowestterms ?
                $inputdata->requirelowestterms :
                self::set_field($inputdata, 'requirelowestterms', self::get_default('input', 'requirelowestterms'));
            $inputdata->checkanswertype = (array) $inputdata->checkanswertype ?
                $inputdata->checkanswertype :
                self::set_field($inputdata, 'checkanswertype', self::get_default('input', 'checkanswertype'));
            $inputdata->mustverify = (array) $inputdata->mustverify ?
                $inputdata->mustverify :
                self::set_field($inputdata, 'mustverify', self::get_default('input', 'mustverify'));
            $inputdata->showvalidation = (array) $inputdata->showvalidation ?
                $inputdata->showvalidation :
                self::set_field($inputdata, 'showvalidation', self::get_default('input', 'showvalidation'));
            $inputdata->options = isset($inputdata->options) ?
                $inputdata->options :
                self::set_field($inputdata, 'options', self::get_default('input', 'options'));
        }

        foreach ($question->prt as $prtdata) {
            $prtdata->autosimplify = (array) $prtdata->autosimplify ?
                $prtdata->autosimplify :
                self::set_field($prtdata, 'autosimplify', self::get_default('prt', 'autosimplify'));
            $prtdata->feedbackstyle = (array) $prtdata->feedbackstyle ?
                $prtdata->feedbackstyle :
                self::set_field($prtdata, 'feedbackstyle', self::get_default('prt', 'feedbackstyle'));
            $prtdata->value = (array) $prtdata->value ?
                $prtdata->value :
                self::set_field($prtdata, 'value', self::get_default('prt', 'value'));

            $prtdata->feedbackvariables = (string) $prtdata->feedbackvariables->text ?
                $prtdata->feedbackvariables->text :
                self::set_field($prtdata, 'feedbackvariables', self::get_default('prt', 'feedbackvariables'));

            foreach ($prtdata->node as $node) {
                $node->description = isset($node->description) ?
                    $node->description :
                    self::set_field($node, 'description', self::get_default('node', 'description'));
                $node->answertest = isset($node->answertest) ?
                    $node->answertest :
                    self::set_field($node, 'answertest', self::get_default('node', 'answertest'));
                $node->testoptions = isset($node->testoptions) ?
                    $node->testoptions :
                    self::set_field($node, 'testoptions', self::get_default('node', 'testoptions'));
                $node->quiet = isset($node->quiet) ?
                    $node->quiet :
                    self::set_field($node, 'quiet', self::get_default('node', 'quiet'));
                $node->truescoremode = isset($node->truescoremode) ?
                    $node->truescoremode :
                    self::set_field($node, 'truescoremode', self::get_default('node', 'truescoremode'));
                $node->truescore = isset($node->truescore) ?
                    $node->truescore :
                    self::set_field($node, 'truescore', self::get_default('node', 'truescore'));
                $node->truepenalty = isset($node->truepenalty) ?
                    $node->truepenalty :
                    self::set_field($node, 'truepenalty', self::get_default('node', 'truepenalty'));
                $node->truenextnode = isset($node->truenextnode) ?
                    $node->truenextnode :
                    self::set_field($node, 'truenextnode', self::get_default('node', 'truenextnode'));
                $node->trueanswernote = isset($node->trueanswernote) ?
                    $node->trueanswernote :
                    self::set_field($node, 'trueanswernote', self::get_default('node', 'trueanswernote'));
                $node->truefeedback = isset($node->truefeedback->text) ?
                    $node->truefeedback->text :
                    self::set_field($node, 'truefeedback', self::get_default('node', 'truefeedback'));
                $node->truefeedback['format'] = isset($node->truefeedback['format']) ?
                    $node->truefeedback['format'] :
                    self::get_default('node', 'truefeedbackformat');
                $node->falsescoremode = isset($node->falsescoremode) ?
                    $node->falsescoremode :
                    self::set_field($node, 'falsescoremode', self::get_default('node', 'falsescoremode'));
                $node->falsescore = isset($node->falsescore) ?
                    $node->falsescore :
                    self::set_field($node, 'falsescore', self::get_default('node', 'falsescore'));
                $node->falsepenalty = isset($node->falsepenalty) ?
                    $node->falsepenalty :
                    self::set_field($node, 'falsepenalty', self::get_default('node', 'falsepenalty'));
                $node->falsenextnode = isset($node->falsenextnode) ?
                    $node->falsenextnode :
                    self::set_field($node, 'falsenextnode', self::get_default('node', 'falsenextnode'));
                $node->falseanswernote = isset($node->falseanswernote) ?
                    $node->falseanswernote :
                    self::set_field($node, 'falseanswernote', self::get_default('node', 'falseanswernote'));
                $node->falsefeedback = isset($node->falsefeedback->text) ?
                    $node->falsefeedback->text :
                    self::set_field($node, 'falsefeedback', self::get_default('node', 'falsefeedback'));
                $node->falsefeedback['format'] = isset($node->falsefeedback['format']) ?
                    $node->falsefeedback['format'] :
                    self::get_default('node', 'falsefeedbackformat');
            }
        }

        foreach ($question->qtest as $test) {
            foreach ($test->testinput as $testinput) {
                $testinput->name = (string) $testinput->name ? $testinput->name :
                    self::set_field($testinput, 'name', self::get_default('testinput', 'name'));
                $testinput->value = (array) $testinput->value ? $testinput->value :
                    self::set_field($testinput, 'value', self::get_default('testinput', 'value'));
            }
            $test->description = (array) $test->description ? $test->description :
                self::set_field($test, 'description', self::get_default('qtest', 'description'));
            $test->testcase = (array) $test->testcase ? $test->testcase :
                self::set_field($test, 'testcase', self::get_default('qtest', 'testcase'));
            foreach ($test->expected as $expected) {
                $expected->name = (string) $expected->name ? $expected->name :
                    self::set_field($expected, 'name', self::get_default('expected', 'name'));
                $expected->expectedscore =
                    (array) $expected->expectedscore ?
                    $expected->expectedscore :
                    self::set_field($expected, 'expectedscore', self::get_default('expected', 'expectedscore'));
                $expected->expectedpenalty =
                    (array) $expected->expectedpenalty ?
                    $expected->expectedpenalty :
                    self::set_field($expected, 'expectedpenalty', self::get_default('expected', 'expectedpenalty'));
                $expected->expectedanswernote =
                    (array) $expected->expectedanswernote ?
                    $expected->expectedanswernote :
                    self::set_field($expected, 'expectedanswernote', self::get_default('expected', 'expectedanswernote'));
            }
        }

        return $xmldata;
    }

    public static function set_field($question, $field, $default) {
        if (!isset($question->$field)) {
            $parts = explode('->', $field);
            $current = $question;
            foreach ($parts as $part) {
                if (!isset($current->$part)) {
                    $current = $current->addChild($part);
                } else {
                    $current = $current->$part;
                }
            }
        }
        $current[0] = $default;
    }

    public static function get_default($defaultcategory, $defaultname) {
        if (!self::$defaults) {
            self::$defaults = yaml_parse_file(__DIR__ . '/../../questiondefaults.yml');
        }

        if (isset(self::$defaults[$defaultcategory][$defaultname])) {
            return self::$defaults[$defaultcategory][$defaultname];
        }
        // We could return $default here but we'd rather the default file was fixed.
        return null;
    }

    /**
     * Converts a YAML string to a SimpleXMLElement object.
     *
     * @param string $yamlstring The YAML string to convert.
     * @return SimpleXMLElement The resulting XML object.
     * @throws \stack_exception If the YAML string is invalid.
     */
    public static function yaml_to_xml($yamlstring) {
        $yaml = yaml_parse($yamlstring);
        if (!$yaml) {
            throw new \stack_exception("The provided file does not contain valid YAML or XML.");
        }
        $xml = new SimpleXMLElement("<quiz></quiz>");
        $question = $xml->addChild('question');
        $question->addAttribute('type', 'stack');

        self::array_to_xml($yaml, $question);
        // Name is a special case. Has text tag but no format.
        $name = (string) $xml->question->name ? (string) $xml->question->name : self::get_default('question', 'name');
        $xml->question->name = new SimpleXMLElement('<root></root>');
        $xml->question->name->addChild('text', $name);
        return $xml;
    }

    /**
     * Recursively converts an associative array to XML.
     */
    public static function array_to_xml($data, &$xml) {
        foreach ($data as $key => $value) {
            if (strpos($key, 'format') !== false && in_array(str_replace('format', '', $key), self::TEXTFIELDS)) {
                // Skip format attributes for text fields - they are handled with the text field below.
                continue;
            } else if (in_array($key, self::TEXTFIELDS)) {
                // Convert basic YAML field to node with text and format fields.
                if ($key !== 'name') {
                    // Name is used in multiple places and sometimes has text property and sometimes not.
                    // Handled in yaml_to_xml().
                    $subnode = $xml->addChild($key);
                    $subvalue = ['text' => $value];
                    if (isset($data[$key . 'format'])) {
                        $subnode['format'] = $data[$key . 'format'];
                    }
                    self::array_to_xml($subvalue, $subnode);
                } else {
                    $xml->addChild($key, $value);
                }
            } else if (in_array($key, self::ARRAYFIELDS)) {
                // Certain fields need special handling to strip out
                // numeric keys.
                foreach ($value as $element) {
                    if (is_array($element)) {
                        $subnode = $xml->addChild($key);
                        self::array_to_xml($element, $subnode);
                    } else {
                        $xml->addChild($key, $element);
                    }
                }
            } else if (is_array($value)) {
                $subnode = $xml->addChild($key);
                self::array_to_xml($value, $subnode);
            } else {
                $xml->addChild($key, $value);
            }
        }
    }

    /**
     * Converts a SimpleXMLElement object to an array for conversion to YAML.
     *
     * @param SimpleXMLElement The resulting XML object.
     * @return array The resulting array.
     */
    public static function xml_to_array($xmldata, &$output = []) {
        foreach ($xmldata as $key => $value) {
            if ($key === 'deployedseed') {
                // Convert deployedseed to an array of integers.
                $x= (int) $value;
            }
            if (in_array($key, self::TEXTFIELDS)) {
                if (isset($value->text)) {
                    $output[$key] = (string) $value->text;
                } else {
                    $output[$key] = (string) $value;
                }
                if (isset($xmldata->$key['format'])) {
                    $output[$key . 'format'] = (string) $xmldata->$key['format'];
                }
            } else if ($value instanceof SimpleXMLElement && $value->count()) {
                if (in_array($key, self::ARRAYFIELDS)) {
                    $output[$key][] = self::xml_to_array($value);
                } else {
                    $output[$key] = [];
                    self::xml_to_array($value, $output[$key]);
                }
            } else {
                if (in_array($key, self::ARRAYFIELDS)) {
                    $output[$key][] = (string) $value;
                } else {
                    $output[$key] = (string) $value;
                }
            }
        }
        return $output;
    }

    public static function detect_differences($xml) {
        if (!self::$defaults) {
                self::$defaults = yaml_parse_file(__DIR__ . '/../questiondefaults.yml');
        }
        if (strpos($xml, '<question type="stack">') !== false) {
            $xmldata = new SimpleXMLElement($xml);
        } else {
            $xmldata = self::yaml_to_xml($xml);
        }
        $plaindata = self::xml_to_array($xmldata);
        $diff = self::obj_diff(self::$defaults['question'], $plaindata['question']);
        if (!empty($plaindata['question']['input'])) {
            $diffinputs = [];
            foreach ($plaindata['question']['input'] as $input) {
                $diffinput = [];
                $diffinput['name'] = $input['name'];
                $diffinput['tans'] = $input['tans'];
                $diffinput = array_merge($diffinput, self::obj_diff(self::$defaults['input'], $input));
                $diffinputs[] = $diffinput;
            }
            $diff['input'] = $diffinputs;
        } else if (!isset($plaindata['question']['defaultgrade']) || $plaindata['question']['defaultgrade']) {
            $diff['input'] = [['name' => self::get_default('input', 'name'),
                'tans' => self::get_default('input', 'tans')]];
        } else {
            $diff['input'] = [];
        }
        if (!empty($plaindata['question']['prt'])) {
            $diffprts = [];
            foreach ($plaindata['question']['prt'] as $prt) {
                $diffprt = [];
                $diffprt['name'] = $prt['name'];
                $diffprt = array_merge($diffprt, self::obj_diff(self::$defaults['prt'], $prt));
                foreach ($prt['node'] as $node) {
                    $diffnode = [];
                    $diffnode['name'] = $node['name'];
                    $diffnode['sans'] = $node['sans'];
                    $diffnode['tans'] = $node['tans'];
                    $diffnode = array_merge($diffnode, self::obj_diff(self::$defaults['node'], $node));
                    $diffprt['node'][] = $diffnode;
                }
                $diffprts[] = $diffprt;
            }
            $diff['prt'] = $diffprts;
        } else if (!isset($plaindata['question']['defaultgrade']) || $plaindata['question']['defaultgrade']) {
            $diff['prt'] = [['name' => self::get_default('prt', 'name'),
                'node' => [['name' => self::get_default('node', 'name'),
                    'sans' => self::get_default('node', 'sans'),
                    'tans' => self::get_default('node', 'tans')]]]];
        } else {
            $diff['prt'] = [];
        }
        if (!empty($plaindata['question']['deployedseed'])) {
            $deployedseed = [];
            foreach ($plaindata['question']['deployedseed'] as $seed) {
                $deployedseed[] = (string) $seed;
            }
            if (count($deployedseed)) {
                $diff['deployedseed'] = $deployedseed;
            }
        }
        if (!empty($plaindata['question']['qtest'])) {
            $difftests = [];
            foreach ($plaindata['question']['qtest'] as $test) {
                $difftest = [];
                $difftest['testcase'] = $test['testcase'];
                $difftest = array_merge($difftest, self::obj_diff(self::$defaults['qtest'], $test));
                foreach ($test['testinput'] as $tinput) {
                    $difftinput = [];
                    $difftinput['name'] = $tinput['name'];
                    $difftinput = array_merge($difftinput, self::obj_diff(self::$defaults['testinput'], $tinput));
                    $difftest['testinput'][] = $difftinput;
                }
                foreach ($test['expected'] as $texpected) {
                    $difftexpected = [];
                    $difftexpected['name'] = $texpected['name'];
                    $difftexpected = array_merge($difftexpected, self::obj_diff(self::$defaults['expected'], $texpected));
                    $difftest['expected'][] = $difftexpected;
                }
                $difftests[] = $difftest;
            }
            $diff['qtest'] = $difftests;
        }
        $yaml = Yaml::dump($diff, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_COMPACT_NESTED_MAPPING);
        return $yaml;
    }

    public static function obj_diff($obj1, $obj2):array {
        $a1 = (array) $obj1;
        $a2 = (array) $obj2;
        return self::arr_diff($a1, $a2);
    }

    public static function arr_diff($a1, $a2):array {
        $r = [];
        foreach ($a1 as $k => $v) {
            if (array_key_exists($k, $a2)) {
                if (is_array($v)){
                    $rad = self::arr_diff($v, (array) $a2[$k]);
                    if (count($rad)) { $r[$k] = $rad; }
                // required to avoid rounding errors due to the
                // conversion from string representation to double
                } else if (is_double($v)){
                    if (abs($v - $a2[$k]) > 0.000000000001) {
                        $r[$k] = $a2[$k];
                    }
                } else {
                    if ($v != $a2[$k]) {
                        $r[$k] = $a2[$k];
                    }
                }
            }
        }
        return $r;
    }
}
