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
if (is_file(__DIR__.'/../vendor/autoload.php')) {
    require_once __DIR__.'/../vendor/autoload.php';
}

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

    public const ALWAYS_SHOWN = [
        'questionsimplify', 'type', 'tans', 'forbidfloat', 'requirelowestterms', 'checkanswertype',
        'mustverify', 'showvalidation', 'autosimplify', 'feedbackstyle', 'answertest', 'sans',
        'quiet', 'name'
    ];

    // phpcs:ignore moodle.Commenting.MissingDocblock.Function
    public static function loadyaml($yaml, $defaults) {
        if ($defaults) {
            self::$defaults = $defaults;
        } else {
            self::$defaults = self::load_defaults(__DIR__ . '/../questiondefaults.yml');
        }
        try {
            $xmldata = self::yaml_to_xml($yaml);
        } catch (\Exception $e) {
            throw new \Exception("The provided file does not contain valid YAML");
        }
        $question = $xmldata->question;

        // Based on Moodle's base question type.
        if (!$question->name->text) {
            self::set_field($question, 'name->text', self::get_default('question', 'name'));
        }
        if (!isset($question->questiontext->text)) {
            self::set_field($question, 'questiontext->text', self::get_default('question', 'questiontext'));
        }
        if (!isset($question->questiontext['format'])) {
            self::get_default('question', 'questiontextformat');
        }
        if (!isset($question->generalfeedback->text)) {
            self::set_field($question, 'generalfeedback->text', self::get_default('question', 'generalfeedback'));
        }
        if (!isset($question->generalfeedback['format'])) {
            $question->generalfeedback['format'] = self::get_default('question', 'generalfeedbackformat');
        }
        if (!(array) $question->defaultgrade) {
            self::set_field($question, 'defaultgrade', self::get_default('question', 'defaultgrade'));
        }
        if (!(array) $question->penalty) {
            self::set_field($question, 'penalty', self::get_default('question', 'penalty'));
        }

        // Based on initialise_question_instance from questiontype.php.
        if (!isset($question->stackversion->text)) {
            self::set_field($question, 'stackversion->text', self::get_default('question', 'stackversion'));
        }
        if (!isset($question->questionvariables->text)) {
            self::set_field($question, 'questionvariables->text', self::get_default('question', 'questionvariables'));
        }
        if (!isset($question->questionnote->text)) {
            self::set_field($question, 'questionnote->text', self::get_default('question', 'questionnote'));
        }
        if (!isset($question->questionnote['format'])) {
            $question->questionnote['format'] = self::get_default('question', 'questionnoteformat');
        }
        if (!isset($question->specificfeedback->text)) {
            self::set_field($question, 'specificfeedback->text', self::get_default('question', 'specificfeedback'));
        }
        if (!isset($question->specificfeedback['format'])) {
            $question->specificfeedback['format'] = self::get_default('question', 'specificfeedbackformat');
        }
        if (!isset($question->questiondescription->text)) {
            self::set_field($question, 'questiondescription->text', self::get_default('question', 'questiondescription'));
        }
        if (!isset($question->questiondescription['format'])) {
            $question->questiondescription['format'] = self::get_default('question', 'questiondescriptionformat');
        }
        if (!isset($question->prtcorrect->text)) {
            self::set_field($question, 'prtcorrect->text', self::get_default('question', 'prtcorrect'));
        }
        if (!isset($question->prtcorrect['format'])) {
            $question->prtcorrect['format'] = self::get_default('question', 'prtcorrectformat');
        }
        if (!isset($question->prtpartiallycorrect->text)) {
            self::set_field($question, 'prtpartiallycorrect->text', self::get_default('question', 'prtpartiallycorrect'));
        }
        if (!isset($question->prtpartiallycorrect['format'])) {
            $question->prtpartiallycorrect['format'] = self::get_default('question', 'prtpartiallycorrectformat');
        }
        if (!isset($question->prtincorrect->text)) {
            self::set_field($question, 'prtincorrect->text', self::get_default('question', 'prtincorrect'));
        }
        if (!isset($question->prtincorrect['format'])) {
            $question->prtincorrect['format'] = self::get_default('question', 'prtincorrectformat');
        }
        if (!isset($question->variantsselectionseed)) {
            self::set_field($question, 'variantsselectionseed', self::get_default('question', 'variantsselectionseed'));
        }
        if (!(array) $question->isbroken) {
            self::set_field($question, 'isbroken', self::get_default('question', 'isbroken'));
        }
        if (!(array) $question->multiplicationsign) {
            self::set_field($question, 'multiplicationsign', self::get_default('question', 'multiplicationsign'));
        }
        if (!(array) $question->complexno) {
            self::set_field($question, 'complexno', self::get_default('question', 'complexno'));
        }
        if (!(array) $question->inversetrig) {
            self::set_field($question, 'inversetrig', self::get_default('question', 'inversetrig'));
        }
        if (!(array) $question->logicsymbol) {
            self::set_field($question, 'logicsymbol', self::get_default('question', 'logicsymbol'));
        }
        if (!(array) $question->matrixparens) {
            self::set_field($question, 'matrixparens', self::get_default('question', 'matrixparens'));
        }
        if (!(array) $question->sqrtsign) {
            self::set_field($question, 'sqrtsign', self::get_default('question', 'sqrtsign'));
        }
        if (!(array) $question->questionsimplify) {
            self::set_field($question, 'simplify', self::get_default('question', 'questionsimplify'));
        }
        if (!(array) $question->assumepositive) {
            self::set_field($question, 'assumepos', self::get_default('question', 'assumepositive'));
        }
        if (!(array) $question->assumereal) {
            self::set_field($question, 'assumereal', self::get_default('question', 'assumereal'));
        }
        if (!(array) $question->decimals) {
            self::set_field($question, 'decimals', self::get_default('question', 'decimals'));
        }
        if (!(array) $question->scientificnotation) {
            self::set_field($question, 'scientificnotation', self::get_default('question', 'scientificnotation'));
        }

        foreach ($question->input as $inputdata) {
            if (!(array) $inputdata->type) {
                self::set_field($inputdata, 'type', self::get_default('input', 'type'));
            }
            if (!(array) $inputdata->boxsize) {
                self::set_field($inputdata, 'boxsize', self::get_default('input', 'boxsize'));
            }
            if (!(array) $inputdata->insertstars) {
                self::set_field($inputdata, 'insertstars', self::get_default('input', 'insertstars'));
            }
            if (!isset($inputdata->syntaxhint)) {
                self::set_field($inputdata, 'syntaxhint', self::get_default('input', 'syntaxhint'));
            }
            if (!(array) $inputdata->syntaxattribute) {
                self::set_field($inputdata, 'syntaxattribute', self::get_default('input', 'syntaxattribute'));
            }
            if (!isset($inputdata->forbidwords)) {
                self::set_field($inputdata, 'forbidwords', self::get_default('input', 'forbidwords'));
            }
            if (!isset($inputdata->allowwords)) {
                self::set_field($inputdata, 'allowwords', self::get_default('input', 'allowwords'));
            }
            if (!(array) $inputdata->forbidfloat) {
                self::set_field($inputdata, 'forbidfloat', self::get_default('input', 'forbidfloat'));
            }
            if (!(array) $inputdata->requirelowestterms) {
                self::set_field($inputdata, 'requirelowestterms', self::get_default('input', 'requirelowestterms'));
            }
            if (!(array) $inputdata->checkanswertype) {
                self::set_field($inputdata, 'checkanswertype', self::get_default('input', 'checkanswertype'));
            }
            if (!(array) $inputdata->mustverify) {
                self::set_field($inputdata, 'mustverify', self::get_default('input', 'mustverify'));
            }
            if (!(array) $inputdata->showvalidation) {
                self::set_field($inputdata, 'showvalidation', self::get_default('input', 'showvalidation'));
            }
            if (!isset($inputdata->options)) {
                self::set_field($inputdata, 'options', self::get_default('input', 'options'));
            }
        }

        foreach ($question->prt as $prtdata) {
            if (!(array) $prtdata->autosimplify) {
                self::set_field($prtdata, 'autosimplify', self::get_default('prt', 'autosimplify'));
            }
            if (!(array) $prtdata->feedbackstyle) {
                self::set_field($prtdata, 'feedbackstyle', self::get_default('prt', 'feedbackstyle'));
            }
            if (!(array) $prtdata->value) {
                self::set_field($prtdata, 'value', self::get_default('prt', 'value'));
            }
            if (!isset($prtdata->feedbackvariables->text)) {
                self::set_field($prtdata, 'feedbackvariables->text', self::get_default('prt', 'feedbackvariables'));
            }

            foreach ($prtdata->node as $node) {
                if (!isset($node->description)) {
                    self::set_field($node, 'description', self::get_default('node', 'description'));
                }
                if (!isset($node->answertest)) {
                    self::set_field($node, 'answertest', self::get_default('node', 'answertest'));
                }
                if (!isset($node->testoptions)) {
                    self::set_field($node, 'testoptions', self::get_default('node', 'testoptions'));
                }
                if (!(array) $node->quiet) {
                    self::set_field($node, 'quiet', self::get_default('node', 'quiet'));
                }
                if (!(array) $node->truescoremode) {
                    self::set_field($node, 'truescoremode', self::get_default('node', 'truescoremode'));
                }
                if (!(array) $node->truescore) {
                    self::set_field($node, 'truescore', self::get_default('node', 'truescore'));
                }
                if (!(array) $node->truepenalty) {
                    self::set_field($node, 'truepenalty', self::get_default('node', 'truepenalty'));
                }
                if (!(array) $node->truenextnode) {
                    self::set_field($node, 'truenextnode', self::get_default('node', 'truenextnode'));
                }
                if (!isset($node->trueanswernote)) {
                    self::set_field($node, 'trueanswernote', self::get_default('node', 'trueanswernote'));
                }
                if (!isset($node->truefeedback->text)) {
                    self::set_field($node, 'truefeedback->text', self::get_default('node', 'truefeedback->text'));
                }
                if (!isset($node->truefeedback['format'])) {
                    $node->truefeedback['format'] = self::get_default('node', 'truefeedbackformat');
                }
                if (!(array) $node->falsescoremode) {
                    self::set_field($node, 'falsescoremode', self::get_default('node', 'falsescoremode'));
                }
                if (!(array) $node->falsescore) {
                    self::set_field($node, 'falsescore', self::get_default('node', 'falsescore'));
                }
                if (!(array) $node->falsepenalty) {
                    self::set_field($node, 'falsepenalty', self::get_default('node', 'falsepenalty'));
                }
                if (!(array) $node->falsenextnode) {
                    self::set_field($node, 'falsenextnode', self::get_default('node', 'falsenextnode'));
                }
                if (!isset($node->falseanswernote)) {
                    self::set_field($node, 'falseanswernote', self::get_default('node', 'falseanswernote'));
                }
                if (!isset($node->falsefeedback->text)) {
                    self::set_field($node, 'falsefeedback->text', self::get_default('node', 'falsefeedback->text'));
                }
                if (!isset($node->falsefeedback['format'])) {
                    $node->falsefeedback['format'] = self::get_default('node', 'falsefeedbackformat');
                }
            }
        }

        foreach ($question->qtest as $test) {
            foreach ($test->testinput as $testinput) {
                if (!isset($testinput->name)) {
                    self::set_field($testinput, 'name', self::get_default('testinput', 'name'));
                }
                if (!(array) $testinput->value) {
                    self::set_field($testinput, 'value', self::get_default('testinput', 'value'));
                }
            }
            if (!(array) $test->description) {
                self::set_field($test, 'description', self::get_default('qtest', 'description'));
            }
            if (!(array) $test->testcase) {
                self::set_field($test, 'testcase', self::get_default('qtest', 'testcase'));
            }
            foreach ($test->expected as $expected) {
                if (!isset($expected->name)) {
                    self::set_field($expected, 'name', self::get_default('expected', 'name'));
                }
                if (!(array) $expected->expectedscore) {
                    self::set_field($expected, 'expectedscore', self::get_default('expected', 'expectedscore'));
                }
                if (!(array) $expected->expectedpenalty) {
                    self::set_field($expected, 'expectedpenalty', self::get_default('expected', 'expectedpenalty'));
                }
                if (!(array) $expected->expectedanswernote) {
                    self::set_field($expected, 'expectedanswernote', self::get_default('expected', 'expectedanswernote'));
                }
            }
        }

        return $xmldata;
    }

    public static function set_field(&$element, $field, $default) {
        if (!isset($question->$field)) {
            $parts = explode('->', $field);
            $current = $element;
            foreach ($parts as $part) {
                $current->addChild($part);
                $current = $current->$part;
            }
            if ($part === 'text') {
                self::add_cdata($current, $default);
            } else {
                $current[0] = $default;
            }
        }
    }

    public static function get_default($defaultcategory, $defaultname) {
        if (!self::$defaults) {
            self::$defaults = Yaml::parseFile(__DIR__ . '/../questiondefaults.yml');
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
        $yaml = Yaml::parse($yamlstring);
        if (!$yaml) {
            throw new \stack_exception("The provided file does not contain valid YAML or XML.");
        }
        $xml = new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8'?><quiz></quiz>");
        $question = $xml->addChild('question');
        $question->addAttribute('type', 'stack');

        self::array_to_xml($yaml, $question);
        // Name is a special case. Has text tag but no format.
        $name = isset($xml->question->name) ? (string) $xml->question->name : self::get_default('question', 'name');
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
                $nodekey = str_replace('format', '', $key);
                if (!isset($xml->$nodekey)) {
                    $xml->addChild($nodekey);
                    $xml->$nodekey['format'] = $value;
                } else {
                    continue;
                }
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
                    $xml->$key = $value;
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
                if ($key === 'text') {
                    $textnode = $xml->addChild('text');
                    self::add_cdata($textnode, $value);
                } else {
                    $xml->$key = $value;
                }
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


    public static function load_defaults($defaultfile) {
        $defaults = Yaml::parseFile($defaultfile);
        if (!$defaults) {
            echo "\nUnable to access or parse default file: {$defaultfile}\nAborting.\n";
            self::call_exit();
        }
        return $defaults;
    }

    /**
     * Mockable function that just exits code.
     *
     * Required to stop PHPUnit displaying output after exit.
     *
     * @return void
     */
    public static function call_exit(): void {
        exit;
    }

    public static function detect_differences($xml, $defaults) {
        if ($defaults) {
            self::$defaults = $defaults;
        } else {
            self::$defaults = self::load_defaults(__DIR__ . '/../questiondefaults.yml');
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
                $diffinput = self::obj_diff(self::$defaults['input'], $input);
                $diffinputs[] = $diffinput;
            }
            $diff['input'] = $diffinputs;
        } else if (!isset($plaindata['question']['defaultgrade']) || $plaindata['question']['defaultgrade']) {
            $diff['input'] = [['name' => self::get_default('input', 'name'),
                'type' => self::get_default('input', 'type'),
                'tans' => self::get_default('input', 'tans'),
                'forbidfloat' => self::get_default('input', 'forbidfloat'),
                'requirelowestterms' => self::get_default('input', 'requirelowestterms'),
                'checkanswertype' => self::get_default('input', 'checkanswertype'),
                'mustverify' => self::get_default('input', 'mustverify'),
                'showvalidation' => self::get_default('input', 'showvalidation')]];
        } else {
            $diff['input'] = [];
        }
        if (!empty($plaindata['question']['prt'])) {
            $diffprts = [];
            foreach ($plaindata['question']['prt'] as $prt) {
                $diffprt = self::obj_diff(self::$defaults['prt'], $prt);
                foreach ($prt['node'] as $node) {
                    $diffnode = self::obj_diff(self::$defaults['node'], $node);
                    $diffprt['node'][] = $diffnode;
                }
                $diffprts[] = $diffprt;
            }
            $diff['prt'] = $diffprts;
        } else if (!isset($plaindata['question']['defaultgrade']) || $plaindata['question']['defaultgrade']) {
            $diff['prt'] = ['name' => self::get_default('prt', 'name'),
                'autosimplify' => self::get_default('prt', 'autosimplify'),
                'feedbackstyle' => self::get_default('prt', 'feedbackstyle'),
                'node' => [['name' => self::get_default('node', 'name'),
                    'answertest' => self::get_default('node', 'answertest'),
                    'sans' => self::get_default('node', 'sans'),
                    'tans' => self::get_default('node', 'tans'),
                    'quiet' => self::get_default('node', 'quiet'),]]];
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
            if (in_array($k, self::ALWAYS_SHOWN)) {
                if (array_key_exists($k, $a2)) {
                    $r[$k] = $a2[$k];
                } else {
                    $r[$k] = $v;
                }
                continue;
            }
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

    public static function add_cdata(&$xml, $value) {
        if (!empty($value) && htmlspecialchars($value, ENT_COMPAT) != $value) {
            $node = dom_import_simplexml($xml);
            $no = $node->ownerDocument;
            $node->appendChild($no->createCDATASection($value));
        } else {
            $xml[0] = $value;
        }
    }
}
