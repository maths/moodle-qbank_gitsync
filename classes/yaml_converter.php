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

class YamlConverter {
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
    public static function loadxml($xml, $defaultsfile) {
        YamlConverter::$defaults = yaml_parse_file($defaultsfile);
        try {
            $xmldata = YamlConverter::yaml_to_xml($xml);
        } catch (\Exception $e) {
            throw new \Exception("The provided file does not contain valid YAML");
        }
        $question = $xmldata->question;

        // Based on Moodle's base question type.
        $question->name->text = (string) $question->name->text ?
            $question->name->text :
            YamlConverter::set_field($question, 'name->text', YamlConverter::get_default('question', 'name'));
        $question->questiontext->text = (string) $question->questiontext->text ?
            $question->questiontext->text :
            YamlConverter::set_field($question, 'questiontext->text', YamlConverter::get_default(
                'question', 'questiontext'
            ));
        $question->questiontext->format = (string) $question->questiontext->format ?
            $question->questiontext->format :
            YamlConverter::set_field($question, 'questiontext->format', YamlConverter::get_default('question', 'questiontextformat'));
        $question->generalfeedback->text = (string) $question->generalfeedback->text ?
            $question->generalfeedback->text :
            YamlConverter::set_field($question, 'generalfeedback->text', YamlConverter::get_default('question', 'generalfeedback'));
        $question->generalfeedback->format = (string) $question->generalfeedback['format'] ?
            $question->generalfeedback['format'] :
            YamlConverter::set_field($question, 'generalfeedback->format', YamlConverter::get_default('question', 'generalfeedbackformat'));
        $question->defaultgrade = (array) $question->defaultgrade ?
            $question->defaultgrade :
            YamlConverter::set_field($question, 'defaultgrade', YamlConverter::get_default('question', 'defaultgrade'));
        $question->penalty = (array) $question->penalty ?
            $question->penalty :
            YamlConverter::set_field($question, 'penalty', YamlConverter::get_default('question', 'penalty'));

        // Based on initialise_question_instance from questiontype.php.
        $question->stackversion = (string) $question->stackversion->text ?
            $question->stackversion->text :
            YamlConverter::set_field($question, 'stackversion', YamlConverter::get_default('question', 'stackversion'));
        $question->questionvariables = (string) $question->questionvariables->text ?
            $question->questionvariables->text :
            YamlConverter::set_field($question, 'questionvariables', YamlConverter::get_default('question', 'questionvariables'));
        $question->questionnote = (string) $question->questionnote->text ?
            $question->questionnote->text :
            YamlConverter::set_field($question, 'questionnote', YamlConverter::get_default('question', 'questionnote'));
        $question->questionnoteformat = (string) $question->questionnote['format'] ?
            $question->questionnote['format'] :
            YamlConverter::set_field($question, 'questionnoteformat', YamlConverter::get_default('question', 'questionnoteformat'));
        $question->specificfeedback = (string) $question->specificfeedback->text ?
            $question->specificfeedback->text :
            YamlConverter::set_field($question, 'specificfeedback', YamlConverter::get_default('question', 'specificfeedback'));
        $question->specificfeedbackformat = (string) $question->specificfeedback['format'] ?
            $question->specificfeedback['format'] :
            YamlConverter::set_field($question, 'specificfeedbackformat', YamlConverter::get_default('question', 'specificfeedbackformat'));
        $question->questiondescription = (string) $question->questiondescription->text ?
            $question->questiondescription->text :
            YamlConverter::set_field($question, 'questiondescription', YamlConverter::get_default('question', 'questiondescription'));
        $question->questiondescriptionformat = (string) $question->questiondescription['format'] ?
            $question->questiondescription['format'] :
            YamlConverter::set_field($question, 'questiondescriptionformat', YamlConverter::get_default('question', 'questiondescriptionformat'));
        $question->prtcorrect = (string) $question->prtcorrect->text ?
            $question->prtcorrect->text :
            YamlConverter::set_field($question, 'prtcorrect', YamlConverter::get_default('question', 'prtcorrect'));
        $question->prtcorrectformat = (string) $question->prtcorrect['format'] ?
            $question->prtcorrect['format'] :
            YamlConverter::set_field($question, 'prtcorrectformat', YamlConverter::get_default('question', 'prtcorrectformat'));
        $question->prtpartiallycorrect = (string) $question->prtpartiallycorrect->text ?
            $question->prtpartiallycorrect->text :
            YamlConverter::set_field($question, 'prtpartiallycorrect', YamlConverter::get_default('question', 'prtpartiallycorrect'));
        $question->prtpartiallycorrectformat = (string) $question->prtpartiallycorrect['format'] ?
            $question->prtpartiallycorrect['format'] :
            YamlConverter::set_field($question, 'prtpartiallycorrectformat', YamlConverter::get_default('question', 'prtpartiallycorrectformat'));
        $question->prtincorrect = (string) $question->prtincorrect->text ?
            $question->prtincorrect->text :
            YamlConverter::set_field($question, 'prtincorrect', YamlConverter::get_default('question', 'prtincorrect'));
        $question->prtincorrectformat = (string) $question->prtincorrect['format'] ?
            $question->prtincorrect['format'] :
            YamlConverter::set_field($question, 'prtincorrectformat', YamlConverter::get_default('question', 'prtincorrectformat'));
        $question->variantsselectionseed = (string) $question->variantsselectionseed ?
            $question->variantsselectionseed :
            YamlConverter::set_field($question, 'variantsselectionseed', YamlConverter::get_default('question', 'variantsselectionseed'));
        $question->isbroken = (array) $question->isbroken ?
            self::parseboolean($question->isbroken) :
            YamlConverter::set_field($question, 'isbroken', YamlConverter::get_default('question', 'isbroken'));
        $question->multiplicationsign =
            (string) $question->multiplicationsign ?
            $question->multiplicationsign :
            YamlConverter::set_field($question, 'multiplicationsign', YamlConverter::get_default('question', 'multiplicationsign'));
        $question->complexno =
            (string) $question->complexno ?
            $question->complexno :
            YamlConverter::set_field($question, 'complexno', YamlConverter::get_default('question', 'complexno'));
        $question->inversetrig =
            (string) $question->inversetrig ?
            $question->inversetrig :
            YamlConverter::set_field($question, 'inversetrig', YamlConverter::get_default('question', 'inversetrig'));
        $question->logicsymbol =
            (string) $question->logicsymbol ?
            $question->logicsymbol :
            YamlConverter::set_field($question, 'logicsymbol', YamlConverter::get_default('question', 'logicsymbol'));
        $question->matrixparens =
            (string) $question->matrixparens ?
            $question->matrixparens :
            YamlConverter::set_field($question, 'matrixparens', YamlConverter::get_default('question', 'matrixparens'));
        $question->sqrtsign =
            (string) $question->sqrtsign ?
            self::parseboolean($question->sqrtsign) :
            YamlConverter::set_field($question, 'sqrtsign', (bool) YamlConverter::get_default('question', 'sqrtsign'));
        $question->simplify =
            (string) $question->questionsimplify ?
            self::parseboolean($question->questionsimplify) :
            YamlConverter::set_field($question, 'simplify', (bool) YamlConverter::get_default('question', 'questionsimplify'));
        $question->assumepos =
            (string) $question->assumepositive ?
            self::parseboolean($question->assumepositive) :
            YamlConverter::set_field($question, 'assumepos', (bool) YamlConverter::get_default('question', 'assumepositive'));
        $question->assumereal =
            (string) $question->assumereal ?
            self::parseboolean($question->assumereal) :
            YamlConverter::set_field($question, 'assumereal', (bool) YamlConverter::get_default('question', 'assumereal'));
        $question->decimals =
            (string) $question->decimals ?
            $question->decimals :
            YamlConverter::set_field($question, 'decimals', YamlConverter::get_default('question', 'decimals'));
        $question->scientificnotation =
            (string) $question->scientificnotation ?
            $question->scientificnotation :
            YamlConverter::set_field($question, 'scientificnotation', YamlConverter::get_default('question', 'scientificnotation'));

        $inputmap = [];
        foreach ($question->input as $input) {
            $inputmap[(string) $input->name] = $input;
        }

        if (empty($inputmap) && $question->defaultgrade) {
            $defaultinput = new \SimpleXMLElement('<input></input>');
            $defaultinput->addChild('name', YamlConverter::get_default('input', 'name'));
            $defaultinput->addChild('tans', YamlConverter::get_default('input', 'tans'));
            $inputmap[YamlConverter::get_default('input', 'name')] = $defaultinput;
        }

        foreach ($inputmap as $inputdata) {
            $inputdata->boxsize = (array) $inputdata->boxsize ?
                $inputdata->boxsize :
                YamlConverter::get_default('input', 'boxsize');
            $inputdata->insertstars = (array) $inputdata->insertstars ?
                $inputdata->insertstars :
                YamlConverter::get_default('input', 'insertstars', get_config('input', 'insertstars'));
            $inputdata->syntaxhint = isset($inputdata->syntaxhint) ?
                $inputdata->syntaxhint :
                YamlConverter::get_default('input', 'syntaxhint');
            $inputdata->syntaxattribute = (array) $inputdata->syntaxattribute ?
                $inputdata->syntaxattribute : YamlConverter::get_default('input', 'syntaxattribute');
            $inputdata->forbidwords = isset($inputdata->forbidwords) ?
                $inputdata->forbidwords :
                YamlConverter::get_default('input', 'forbidwords');
            $inputdata->allowwords = isset($inputdata->allowwords) ?
                $inputdata->allowwords : YamlConverter::get_default('input', 'allowwords');
            $inputdata->forbidfloat = (array) $inputdata->forbidfloat ?
                $inputdata->forbidfloat :
                YamlConverter::get_default('input', 'forbidfloat');
            $inputdata->requirelowestterms = (array) $inputdata->requirelowestterms ?
                $inputdata->requirelowestterms :
                YamlConverter::get_default(
                    'input', 'requirelowestterms', get_config('input', 'requirelowestterms')
                );
            $inputdata->checkanswertype = (array) $inputdata->checkanswertype ?
                $inputdata->checkanswertype :
                YamlConverter::get_default(
                    'input', 'checkanswertype', get_config('input', 'checkanswertype')
                );
            $inputdata->mustverify = (array) $inputdata->mustverify ?
                $inputdata->mustverify :
                YamlConverter::get_default('input', 'mustverify');
            $inputdata->showvalidation = (array) $inputdata->showvalidation ?
                $inputdata->showvalidation :
                YamlConverter::get_default('input', 'showvalidation', get_config('qtype_stack', 'inputshowvalidation'));
            $inputdata->options = isset($inputdata->options) ? $inputdata->options :
                YamlConverter::get_default('input', 'options');
        }

        $totalvalue = 0;
        $allformative = true;
        $prtmap = [];
        foreach ($question->prt as $prt) {
            $prtmap[(string) $prt->name] = $prt;
        }

        if (empty($prtmap) && $question->defaultmark) {
            $defaultprt = new \SimpleXMLElement('<prt></prt>');
            $defaultprt->addChild('name', YamlConverter::get_default('prt', 'name'));
            $defaultnode = $defaultprt->addChild('node');
            $defaultnode->addChild('name', YamlConverter::get_default('node', 'name'));
            $defaultnode->addChild('sans', YamlConverter::get_default('node', 'sans'));
            $defaultnode->addChild('tans', YamlConverter::get_default('node', 'tans'));
            $defaultnode->addChild('trueanswernote', YamlConverter::get_default('node', 'trueanswernote'));
            $defaultnode->addChild('falseanswernote', YamlConverter::get_default('node', 'falseanswernote'));
            $prtmap[YamlConverter::get_default('prt', 'name')] = $defaultprt;
        }

        foreach ($prtmap as $prtdata) {
            // At this point we do not have the PRT method is_formative() available to us.
            if (!isset($prtdata->feedbackstyle) || ((int) $prtdata->feedbackstyle) > 0) {
                $totalvalue += isset($prtdata->value) ? (float) $prtdata->value : YamlConverter::get_default('prt', 'value');
                $allformative = false;
            }
        }
        if (count($prtmap) > 0 && !$allformative && $totalvalue < 0.0000001) {
            throw new \stack_exception('There is an error authoring your question. ' .
                'The $totalvalue, the marks available for the question, must be positive in question ' .
                $question->name);
        }

        foreach ($prtmap as $prtdata) {
            $prtvalue = 0;
            if (!$allformative) {
                $value = $prtdata->value ? (float) $prtdata->value : YamlConverter::get_default('prt', 'value');
                $prtvalue = $value / $totalvalue;
            }

            $data = new \stdClass();
            $data->name = (string) $prtdata->name;
            $data->autosimplify = (array) $prtdata->autosimplify ? self::parseboolean($prtdata->autosimplify) :
                YamlConverter::get_default('prt', 'autosimplify', true);
            $data->feedbackstyle = (array) $prtdata->feedbackstyle ? (int) $prtdata->feedbackstyle :
                YamlConverter::get_default('prt', 'feedbackstyle', 1);
            $data->value = (array) $prtdata->value ? (float) $prtdata->value :
                YamlConverter::get_default('prt', 'value', 1.0);
            $data->firstnodename = null;

            $data->feedbackvariables = (string) $prtdata->feedbackvariables->text ? (string) $prtdata->feedbackvariables->text :
                YamlConverter::get_default('prt', 'feedbackvariables', '');

            $data->nodes = [];
            foreach ($prtdata->node as $node) {
                $newnode = new \stdClass();

                $newnode->nodename = (string) $node->name;
                $newnode->description = isset($node->description) ? (string) $node->description : '';
                $newnode->answertest = isset($node->answertest) ? (string) $node->answertest :
                    YamlConverter::get_default('node', 'answertest');
                $newnode->sans = (string) $node->sans;
                $newnode->tans = (string) $node->tans;
                $newnode->testoptions = (string) $node->testoptions ? (string) $node->testoptions :
                    YamlConverter::get_default('node', 'testoptions');
                $newnode->quiet = isset($node->quiet) ? self::parseboolean($node->quiet) :
                    YamlConverter::get_default('node', 'quiet');

                $newnode->truescoremode = (array) $node->truescoremode ?
                    (string) $node->truescoremode : YamlConverter::get_default('node', 'truescoremode');
                $newnode->truescore = (array) $node->truescore ?
                (string) $node->truescore : YamlConverter::get_default('node', 'truescore');
                $newnode->truepenalty = (array) $node->truepenalty ?
                    (string) $node->truepenalty : YamlConverter::get_default('node', 'truepenalty');
                $newnode->truenextnode = (array) $node->truenextnode ?
                    (string) $node->truenextnode : YamlConverter::get_default('node', 'truenextnode');
                $newnode->trueanswernote = (string) $node->trueanswernote ?
                    (string) $node->trueanswernote : YamlConverter::get_default('node', 'trueanswernote');
                $newnode->truefeedback = (string) $node->truefeedback->text ?
                    (string) $node->truefeedback->text : YamlConverter::get_default('node', 'truefeedback');
                $newnode->truefeedbackformat =
                    (string) $node->truefeedback['format'] ?
                    (string) $node->truefeedback['format'] : YamlConverter::get_default('node', 'truefeedbackformat', 'html');

                $newnode->falsescoremode = (array) $node->falsescoremode ?
                    (string) $node->falsescoremode : YamlConverter::get_default('node', 'falsescoremode');
                $newnode->falsescore = (array) $node->falsescore ? (string) $node->falsescore :
                    YamlConverter::get_default('node', 'falsescore', '0.0');
                $newnode->falsepenalty = (array) $node->falsepenalty ?
                    (string) $node->falsepenalty : YamlConverter::get_default('node', 'falsepenalty');
                $newnode->falsenextnode = (array) $node->falsenextnode ?
                    (string) $node->falsenextnode : YamlConverter::get_default('node', 'falsenextnode');
                $newnode->falseanswernote = (string) $node->falseanswernote ?
                    (string) $node->falseanswernote : YamlConverter::get_default('node', 'falseanswernote');
                $newnode->falsefeedback = (string) $node->falsefeedback->text ?
                    (string) $node->falsefeedback->text : YamlConverter::get_default('node', 'falsefeedback');
                $newnode->falsefeedbackformat =
                    (string) $node->falsefeedback['format'] ?
                    (string) $node->falsefeedback['format'] :
                    YamlConverter::get_default('node', 'falsefeedbackformat', 'html');

                $data->nodes[(int) $node->name] = $newnode;
            }

            $question->prts[(string) $prtdata->name] = new \stack_potentialresponse_tree_lite($data,
                $prtvalue, $question);
        }

        $deployedseeds = [];
        foreach ($question->deployedseed as $seed) {
            $deployedseeds[] = (int) $seed;
        }

        $question->deployedseeds = $deployedseeds;
        $testcases = [];

        if ($includetests) {
            foreach ($question->qtest as $test) {
                $testinputs = [];
                foreach ($test->testinput as $testinput) {
                    $testiname = (string) $testinput->name ? (string) $testinput->name :
                        YamlConverter::get_default('testinput', 'name');
                    $testivalue = (array) $testinput->value ? (string) $testinput->value :
                        YamlConverter::get_default('testinput', 'value');
                    $testinputs[$testiname] = $testivalue;
                }
                $testdescription = (array) $test->description ? (string) $test->description :
                    YamlConverter::get_default('qtest', 'description');
                $testtestcase = (array) $test->testcase ? (string) $test->testcase :
                    YamlConverter::get_default('qtest', 'testcase');
                $testcase = new \stack_question_test($testdescription, $testinputs, $testtestcase);
                foreach ($test->expected as $expected) {
                    $testename = (string) $expected->name ? (string) $expected->name :
                        YamlConverter::get_default('expected', 'name');
                    $testcase->add_expected_result($testename,
                            new \stack_potentialresponse_tree_state(1, true,
                                (array) $expected->expectedscore ?
                                    (string) $expected->expectedscore :
                                    YamlConverter::get_default('expected', 'expectedscore'),
                                (array) $expected->expectedpenalty ?
                                    (string) $expected->expectedpenalty :
                                    YamlConverter::get_default('expected', 'expectedpenalty'),
                                '', [
                                    (array) $expected->expectedanswernote ?
                                    (string) $expected->expectedanswernote :
                                    YamlConverter::get_default('expected', 'expectedanswernote')
                                ]));
                }
                $testcases[] = $testcase;
            }
        }

        return ['question' => $question, 'testcases' => $testcases];
    }

    // phpcs:ignore moodle.Commenting.MissingDocblock.Function
    private static function handlefiles(\SimpleXMLElement $files) {
        $data = [];

        foreach ($files as $file) {
            $data[(string) $file['name']] = (string) $file;
        }

        return $data;
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

    // phpcs:ignore moodle.Commenting.MissingDocblock.Function
    private static function parseboolean(\SimpleXMLElement $element) {
        $v = (string) $element;
        if ($v === "0") {
            return false;
        }
        if ($v === "1") {
            return true;
        }

        throw new \stack_exception('invalid bool value');
    }

    public static function get_default($defaultcategory, $defaultname) {
        if (!YamlConverter::$defaults) {
            YamlConverter::$defaults = yaml_parse_file(__DIR__ . '/../../questiondefaults.yml');
        }

        if (isset(YamlConverter::$defaults[$defaultcategory][$defaultname])) {
            return YamlConverter::$defaults[$defaultcategory][$defaultname];
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

        YamlConverter::array_to_xml($yaml, $question);
        // Name is a special case. Has text tag but no format.
        $name = (string) $xml->question->name ? (string) $xml->question->name : YamlConverter::get_default('question', 'name');
        $xml->question->name = new SimpleXMLElement('<root></root>');
        $xml->question->name->addChild('text', $name);
        return $xml;
    }

    /**
     * Recursively converts an associative array to XML.
     */
    public static function array_to_xml($data, &$xml) {
        foreach($data as $key => $value) {
            if (strpos($key, 'format') !== false && in_array(str_replace('format', '', $key), YamlConverter::TEXTFIELDS)) {
                // Skip format attributes for text fields - they are handled with the text field below.
                continue;
            } else if (in_array($key, YamlConverter::TEXTFIELDS)) {
                // Convert basic YAML field to node with text and format fields.
                if ($key !== 'name') {
                    // Name is used in multiple places and sometimes has text property and sometimes not.
                    // Handled in yaml_to_xml().
                    $subnode = $xml->addChild($key);
                    $subvalue = ['text' => $value];
                    if (isset($data[$key . 'format'])) {
                        $subvalue['format'] = $data[$key . 'format'];
                    }
                    YamlConverter::array_to_xml($subvalue, $subnode);
                } else {
                    $xml->addChild($key, $value);
                }
            } else if (in_array($key, YamlConverter::ARRAYFIELDS)) {
                // Certain fields need special handling to strip out
                // numeric keys.
                foreach($value as $element) {
                    if (is_array($element)) {
                        $subnode = $xml->addChild($key);
                        YamlConverter::array_to_xml($element, $subnode);
                    } else {
                        $xml->addChild($key, $element);
                    }
                }
            } else if (is_array($value)) {
                $subnode = $xml->addChild($key);
                YamlConverter::array_to_xml($value, $subnode);
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
        foreach($xmldata as $key => $value) {
            if ($key === 'deployedseed') {
                // Convert deployedseed to an array of integers.
                $x= (int) $value;
            }
            if (in_array($key, YamlConverter::TEXTFIELDS)) {
                if (isset($value->text)) {
                    $output[$key] = (string) $value->text;
                } else {
                    $output[$key] = (string) $value;
                }
                if (isset($value->format)) {
                    $output[$key . 'format'] = (string) $value->format;
                }
            } else if ($value instanceof SimpleXMLElement && $value->count()) {
                if (in_array($key, YamlConverter::ARRAYFIELDS)) {
                    $output[$key][] = YamlConverter::xml_to_array($value);
                } else {
                    $output[$key] = [];
                    YamlConverter::xml_to_array($value, $output[$key]);
                }
            } else {
                if (in_array($key, YamlConverter::ARRAYFIELDS)) {
                    $output[$key][] = (string) $value;
                } else {
                    $output[$key] = (string) $value;
                }
            }
        }
        return $output;
    }

    public static function detect_differences($xml) {
        if (!YamlConverter::$defaults) {
                YamlConverter::$defaults = yaml_parse_file(__DIR__ . '/../questiondefaults.yml');
        }
        if (strpos($xml, '<question type="stack">') !== false) {
            $xmldata = new SimpleXMLElement($xml);
        } else {
            $xmldata = YamlConverter::yaml_to_xml($xml);
        }
        $plaindata = YamlConverter::xml_to_array($xmldata);
        $diff = YamlConverter::obj_diff(YamlConverter::$defaults['question'], $plaindata['question']);
        if (!empty($plaindata['question']['input'])) {
            $diffinputs = [];
            foreach ($plaindata['question']['input'] as $input) {
                $diffinput = [];
                $diffinput['name'] = $input['name'];
                $diffinput['tans'] = $input['tans'];
                $diffinput = array_merge($diffinput, YamlConverter::obj_diff(YamlConverter::$defaults['input'], $input));
                $diffinputs[] = $diffinput;
            }
            $diff['input'] = $diffinputs;
        } else if (!isset($plaindata['question']['defaultgrade']) || $plaindata['question']['defaultgrade']) {
            $diff['input'] = [['name' => YamlConverter::get_default('input', 'name'),
                'tans' => YamlConverter::get_default('input', 'tans')]];
        } else {
            $diff['input'] = [];
        }
        if (!empty($plaindata['question']['prt'])) {
            $diffprts = [];
            foreach ($plaindata['question']['prt'] as $prt) {
                $diffprt = [];
                $diffprt['name'] = $prt['name'];
                $diffprt = array_merge($diffprt, YamlConverter::obj_diff(YamlConverter::$defaults['prt'], $prt));
                foreach ($prt['node'] as $node) {
                    $diffnode = [];
                    $diffnode['name'] = $node['name'];
                    $diffnode['sans'] = $node['sans'];
                    $diffnode['tans'] = $node['tans'];
                    $diffnode = array_merge($diffnode, YamlConverter::obj_diff(YamlConverter::$defaults['node'], $node));
                    $diffprt['node'][] = $diffnode;
                }
                $diffprts[] = $diffprt;
            }
            $diff['prt'] = $diffprts;
        } else if (!isset($plaindata['question']['defaultgrade']) || $plaindata['question']['defaultgrade']) {
            $diff['prt'] = [['name' => YamlConverter::get_default('prt', 'name'),
                'node' => [['name' => YamlConverter::get_default('node', 'name'),
                    'sans' => YamlConverter::get_default('node', 'sans'),
                    'tans' => YamlConverter::get_default('node', 'tans')]]]];
        } else {
            $diff['prt'] = [];
        }
        if (!empty($plaindata['question']['deployedseed'])) {
            $deployedseeds = [];
            foreach ($plaindata['question']['deployedseed'] as $seed) {
                $deployedseeds[] = (string) $seed;
            }
            if (count($deployedseeds)) {
                $diff['deployedseed'] = $deployedseeds;
            }
        }
        if (!empty($plaindata['question']['qtest'])) {
            $difftests = [];
            foreach ($plaindata['question']['qtest'] as $test) {
                $difftest = [];
                $difftest['testcase'] = $test['testcase'];
                $difftest = array_merge($difftest, YamlConverter::obj_diff(YamlConverter::$defaults['qtest'], $test));
                foreach ($test['testinput'] as $tinput) {
                    $difftinput = [];
                    $difftinput['name'] = $tinput['name'];
                    $difftinput = array_merge($difftinput, YamlConverter::obj_diff(YamlConverter::$defaults['testinput'], $tinput));
                    $difftest['testinput'][] = $difftinput;
                }
                foreach ($test['expected'] as $texpected) {
                    $difftexpected = [];
                    $difftexpected['name'] = $texpected['name'];
                    $difftexpected = array_merge($difftexpected, YamlConverter::obj_diff(YamlConverter::$defaults['expected'], $texpected));
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
        return YamlConverter::arr_diff($a1, $a2);
    }

    public static function arr_diff($a1, $a2):array {
        $r = [];
        foreach ($a1 as $k => $v) {
            if (array_key_exists($k, $a2)) {
                if (is_array($v)){
                    $rad = YamlConverter::arr_diff($v, (array) $a2[$k]);
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
