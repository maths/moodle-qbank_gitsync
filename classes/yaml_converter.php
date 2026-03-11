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

/**
 * Handles conversion between YAML and XML for Stack questions.
 * It provides methods to load YAML, convert it to XML, and vice versa.
 * It also sets default values for various fields based on a defaults file.
 */
class yaml_converter {
    /**
     * @var array|null Default values for the question.
     */
    public static $defaults = null;
    /**
     * @var array Question properties that have <text> elements in the xml.
     */
    public const TEXTFIELDS = [
        'name', 'questiontext', 'generalfeedback', 'stackversion', 'questionvariables',
        'specificfeedback', 'questionnote',
        'questiondescription', 'prtcorrect', 'prtpartiallycorrect', 'prtincorrect',
        'feedbackvariables', 'truefeedback', 'falsefeedback',
    ];
    /**
     * @var array Question properties that can have multiple elements in the xml.
     */
    public const ARRAYFIELDS = [
        'input', 'prt', 'node', 'deployedseed', 'qtest', 'testinput', 'expected',
    ];

    /**
     * @var array Question properties are always shown in difference file even if they match the default.
     */
    public const ALWAYS_SHOWN = [
        'questionsimplify', 'type', 'tans', 'forbidfloat', 'requirelowestterms', 'checkanswertype',
        'mustverify', 'showvalidation', 'autosimplify', 'feedbackstyle', 'answertest', 'sans',
        'quiet', 'name',
    ];

    /**
     * Check YAML has been installed on the local machine, and if not offer meaningful error.
     */
    private static function require_yaml() {
        if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
            throw new \Exception('You asked for yaml. ' .
                'If you wish to store questions in YAML format you will need to install Symfony YAML, ' .
                'which appears to be missing on your installation. ' .
                'Symfony YAML can be installed via composer.');
        }
        require_once(__DIR__ . '/../vendor/autoload.php');
    }

    // phpcs:ignore moodle.Commenting.MissingDocblock.Function
    public static function load_string_as_xml($data, $defaults, $useyaml = true) {
        if ($defaults) {
            self::$defaults = $defaults;
        } else {
            self::$defaults = self::load_defaults(__DIR__ . '/../' . cli_helper::DEFAULTS_FILE . ($useyaml ? 'yml' : 'xml'));
        }
        if ($useyaml) {
            $xmldata = self::yamlstring_to_xml($data);
        } else {
            $xmldata = new SimpleXMLElement($data);
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
            $question->questiontext['format'] = self::get_default('question', 'questiontextformat');
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
            if (preg_match("/\[\[input:" . self::get_default('input', 'name') . "\]\]/", $question->questiontext->text)) {
                self::set_field($question, 'specificfeedback->text', self::get_default('question', 'specificfeedback'));
            } else {
                self::set_field($question, 'specificfeedback->text', '');
            }
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
        if (!(array) $question->hidden) {
            self::set_field($question, 'hidden', self::get_default('question', 'hidden'));
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
            self::set_field($question, 'questionsimplify', self::get_default('question', 'questionsimplify'));
        }
        if (!(array) $question->assumepositive) {
            self::set_field($question, 'assumepositive', self::get_default('question', 'assumepositive'));
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

        if (!$question->input || !count($question->input)) {
            $inputname = self::get_default('input', 'name');
            if (preg_match("/\[\[input:{$inputname}\]\]/", $question->questiontext->text)) {
                self::set_field($question, 'input->0', '');
            } else {
                // We've not got any inputs. Set default mark to 0.
                self::set_field($question, 'defaultgrade', '0');
            }
        }

        foreach ($question->input as $inputdata) {
            if (!(array) $inputdata->name) {
                self::set_field($inputdata, 'name', self::get_default('input', 'name'));
            }
            if (!(array) $inputdata->type) {
                self::set_field($inputdata, 'type', self::get_default('input', 'type'));
            }
            if (!(array) $inputdata->tans) {
                self::set_field($inputdata, 'tans', self::get_default('input', 'tans'));
            }
            if (!(array) $inputdata->strictsyntax) {
                self::set_field($inputdata, 'strictsyntax', self::get_default('input', 'strictsyntax'));
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

        if (!$question->prt || !count($question->prt)) {
            $prtname = self::get_default('prt', 'name');
            if (
                preg_match(
                    "/\[\[feedback:{$prtname}\]\]/",
                    $question->questiontext->text . $question->specificfeedback->text
                )
            ) {
                self::set_field($question, 'prt->0', '');
            }
        }

        foreach ($question->prt as $prtdata) {
            if (!(array) $prtdata->name) {
                self::set_field($prtdata, 'name', self::get_default('prt', 'name'));
            }
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

            if (!$prtdata->node || !count($prtdata->node)) {
                self::set_field($prtdata, 'node', '');
            }

            foreach ($prtdata->node as $node) {
                if (!isset($node->name)) {
                    self::set_field($node, 'name', self::get_default('node', 'name'));
                }
                if (!isset($node->description)) {
                    self::set_field($node, 'description', self::get_default('node', 'description'));
                }
                if (!isset($node->answertest)) {
                    self::set_field($node, 'answertest', self::get_default('node', 'answertest'));
                }
                if (!isset($node->sans)) {
                    self::set_field($node, 'sans', self::get_default('node', 'sans'));
                }
                if (!isset($node->tans)) {
                    self::set_field($node, 'tans', self::get_default('node', 'tans'));
                }
                if (!isset($node->testoptions)) {
                    self::set_field($node, 'testoptions', self::get_default('node', 'testoptions'));
                }
                self::parse_answertest($node);
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
                if (!isset($testinput->value)) {
                    self::set_field($testinput, 'value', self::get_default('testinput', 'value'));
                }
            }
            if (!isset($test->description)) {
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
                if (!isset($expected->expectedanswernote)) {
                    self::set_field($expected, 'expectedanswernote', self::get_default('expected', 'expectedanswernote'));
                }
            }
        }

        return $xmldata;
    }

    /**
     * Splits an answertest string into its components and adds the fields to the node.
     * @param mixed $node
     * @return void
     */
    public static function parse_answertest(&$node) {
        if (substr($node->answertest, 0, 2) === 'AT') {
            [$answertest, $sans, $tans, $testoptions] = self::split_answertest($node->answertest);
            self::set_field($node, 'answertest', substr($answertest, 2));
            self::set_field($node, 'sans', $sans);
            self::set_field($node, 'tans', $tans);
            self::set_field($node, 'testoptions', $testoptions);
        }
    }

    /**
     * Set a field (including parents) in the XML element if it does not already exist.
     * @param mixed $element element to add the field to
     * @param mixed $field in format parent->child if multiple levels
     * @param mixed $default value to set the field to
     * @return void
     */
    public static function set_field(&$element, $field, $default): void {
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

    /**
     * Returns the default value for a question property.
     *
     * @param string $defaultcategory The category of the property required - question, input, prt, node, qtest.
     * @param string $defaultname The name of the property.
     * @return mixed The default value.
     */
    public static function get_default($defaultcategory, $defaultname) {
        if (!self::$defaults) {
            self::require_yaml();
            self::$defaults = Yaml::parseFile(__DIR__ . '/../questiondefaults.yml');
        }

        if (isset(self::$defaults[$defaultcategory][$defaultname])) {
            return self::$defaults[$defaultcategory][$defaultname];
        }

        if (
            $defaultcategory === 'node'
                && in_array($defaultname, ['sans', 'tans', 'testoptions'])
        ) {
            $answertest = self::get_default('node', 'answertest');
            if (substr($answertest, 0, 2) === 'AT') {
                [$answertest, $sans, $tans, $testoptions] = self::split_answertest($answertest);
                if ($defaultname === 'sans') {
                    return $sans;
                } else if ($defaultname === 'tans') {
                    return $tans;
                } else if ($defaultname === 'testoptions') {
                    return $testoptions;
                }
            }
        }
        // We could return $default here but we'd rather the default file was fixed.
        return null;
    }

    /**
     * Converts a YAML string to a SimpleXMLElement object.
     *
     * @param string $yamlstring The YAML string to convert.
     * @return SimpleXMLElement The resulting XML object.
     * @throws \Exception If the YAML string is invalid.
     */
    public static function yamlstring_to_xml($yamlstring) {
        self::require_yaml();
        $yaml = Yaml::parse($yamlstring);
        if (!$yaml) {
            throw new \Exception("The provided file does not contain valid YAML or XML.");
        }
        $xml = self::yaml_to_xml($yaml);
        return $xml;
    }

    /**
     * Converts YAML to an XML string.
     *
     * @param array $yaml The YAML to convert.
     * @return string The resulting XML string.
     */
    public static function yaml_to_xmlstring($yaml) {
        $xml = self::yaml_to_xml($yaml);
        return $xml->asXML();
    }

    /**
     * Converts YAML string to an XML string.
     *
     * @param string $yamlstring The YAML string to convert.
     * @return string The resulting XML string.
     */
    public static function xmlstring_to_yamlstring($xmlstring) {
        $xml = new SimpleXMLElement($xmlstring);
        $yaml = self::xml_to_array($xml);
        self::require_yaml();
        $yaml = Yaml::dump($yaml, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_COMPACT_NESTED_MAPPING);
        return $yaml;
    }

    /**
     * Converts YAML to a SimpleXMLElement object.
     *
     * @param [] $yaml The YAML to convert.
     * @return SimpleXMLElement $xml The resulting XML.
     */
    public static function yaml_to_xml($yaml) {
        $xml = new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8'?><quiz></quiz>");
        if (!isset($yaml['question'])) {
            $question = $xml->addChild('question');
            self::array_to_xml($yaml, $question);
        } else {
            self::array_to_xml($yaml, $xml);
        }

        // Name is a special case. Has text tag but no format.
        $name = isset($xml->question->name) ? (string) $xml->question->name : self::get_default('question', 'name');
        $xml->question->name = new SimpleXMLElement('<root></root>');
        $xml->question->name->text = $name;
        $xml->question->addAttribute('type', 'stack');
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
                    $xml->{$nodekey}['format'] = $value;
                } else {
                    continue;
                }
            } else if (in_array($key, self::TEXTFIELDS)) {
                // Convert basic YAML field to node with text and format fields.
                if ($key !== 'name') {
                    // Name is used in multiple places and sometimes has text property and sometimes not.
                    // Handled in yamlstring_to_xml().
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
     * @param array Previous output.
     * @param boolean Are we converting a default file. We need to make it flatter.
     * @return array The resulting array.
     */
    public static function xml_to_array($xmldata, &$output = [], $isdefault = false) {
        foreach ($xmldata as $key => $value) {
            if (in_array($key, self::TEXTFIELDS)) {
                if (isset($value->text)) {
                    $output[$key] = (string) $value->text;
                } else {
                    $output[$key] = (string) $value;
                }
                if (isset($xmldata->{$key}['format'])) {
                    $output[$key . 'format'] = (string) $xmldata->{$key}['format'];
                }
            } else if ($value instanceof SimpleXMLElement && $value->count()) {
                if (in_array($key, self::ARRAYFIELDS) && !$isdefault) {
                    $output[$key][] = self::xml_to_array($value);
                } else {
                    $output[$key] = [];
                    self::xml_to_array($value, $output[$key]);
                }
            } else {
                if (in_array($key, self::ARRAYFIELDS) && !$isdefault) {
                    $output[$key][] = (string) $value;
                } else {
                    $output[$key] = (string) $value;
                }
            }
        }
        return $output;
    }

    /**
     * Load a parse file with default values for questions.
     * @param string filepath
     * @param boolean $isyaml Is default file YAML? (Rather than XML?).
     * @return array YAML
     */
    public static function load_defaults($defaultfile, $isyaml = true) {
        try {
            if ($isyaml) {
                self::require_yaml();
                $defaults = Yaml::parseFile($defaultfile);
            } else {
                $xml = file_get_contents($defaultfile);
                $defaults = new SimpleXMLElement($xml);
                $output = [];
                $defaults = self::xml_to_array($defaults, $output, true);
            }
        } catch (\Exception $e) {
            $defaults = null;
        }
        if (!$defaults) {
            echo "\nUnable to access or parse default file: {$defaultfile}\nAborting.\n";
            echo "{$e->getMessage()}\n";
            echo "Make sure your 'useyaml' setting is correct for this repo.\n";
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

    /**
     * Detects differences between the provided XML and the default question structure.
     *
     * @param string $xml The XML string to compare.
     * @param boolean $useyaml Return YAML? (Rather than XML?).
     * @return string The differences.
     */
    public static function detect_differences($xml, $defaults, $useyaml = true) {
        if ($defaults) {
            self::$defaults = $defaults;
        } else {
            self::$defaults = self::load_defaults(__DIR__ . '/../' . cli_helper::DEFAULTS_FILE . ($useyaml ? 'yml' : 'xml'));
        }
        $xmldata = new SimpleXMLElement($xml);
        $plaindata = self::xml_to_array($xmldata);
        $diff = self::obj_diff(self::$defaults['question'], $plaindata['question']);
        $isquestiontext = isset($plaindata['question']['questiontext']);
        $isdefaultinput = preg_match(
            "/\[\[input:" . self::get_default('input', 'name') . "\]\]/",
            self::get_default('question', 'questiontext')
        );
        $isrequesteddefaultinput = isset($plaindata['question']['questiontext']) && preg_match(
            "/\[\[input:" . self::get_default('input', 'name') . "\]\]/",
            $plaindata['question']['questiontext']
        );
        $isfeedback = isset($plaindata['question']['specificfeedback']);
        $isdefaultprt = preg_match(
            "/\[\[feedback:" . self::get_default('prt', 'name') . "\]\]/",
            self::get_default('question', 'specificfeedback')
        );
        $isrequesteddefaultprt = isset($plaindata['question']['questiontext']) &&
            isset($plaindata['question']['specificfeedback']) &&
            preg_match(
                "/\[\[feedback:{" . self::get_default('prt', 'name') . "}\]\]/",
                $plaindata['question']['questiontext'] . $plaindata['question']['specificfeedback']
            );
        if (!empty($plaindata['question']['input'])) {
            $diffinputs = [];
            foreach ($plaindata['question']['input'] as $input) {
                $diffinput = self::obj_diff(self::$defaults['input'], $input);
                $diffinputs[] = $diffinput;
            }
            $diff['input'] = $diffinputs;
            // We need to create an input if questiontext contains [[input:ansnamedefault]] or
            // questiontext doesn't exist and default contains [[input:ansnamedefault]].
        } else if ((!$isquestiontext && $isdefaultinput) || $isrequesteddefaultinput) {
            $diff['input'] = [['name' => self::get_default('input', 'name'),
                'type' => self::get_default('input', 'type'),
                'tans' => self::get_default('input', 'tans'),
                'forbidfloat' => self::get_default('input', 'forbidfloat'),
                'requirelowestterms' => self::get_default('input', 'requirelowestterms'),
                'checkanswertype' => self::get_default('input', 'checkanswertype'),
                'mustverify' => self::get_default('input', 'mustverify'),
                'showvalidation' => self::get_default('input', 'showvalidation')]];
            // We need to create a PRT if questiontext contains [[input:ansnamedefault]] or
            // questiontext doesn't exist and default contains [[input:ansnamedefault]].
        } else {
            $diff['input'] = [];
            if (self::get_default('question', 'defaultgrade') !== 0) {
                $diff['defaultgrade'] = '0';
            } else {
                unset($diff['defaultgrade']);
            }
        }
        if (!empty($plaindata['question']['prt'])) {
            $diffprts = [];
            foreach ($plaindata['question']['prt'] as $prt) {
                $diffprt = self::obj_diff(self::$defaults['prt'], $prt);
                foreach ($prt['node'] as $node) {
                    $diffnode = self::obj_diff(self::$defaults['node'], $node);
                    if (
                        substr(self::get_default('node', 'answertest'), 0, 2) === 'AT' &&
                            substr($diffnode['answertest'], 0, 2) !== 'AT'
                    ) {
                        // This occurs if answertest set in XML but summary in defaults.
                        // We need to build a summary from supplied XML fields and default summary.
                        $diffanswertest = isset($node['answertest']) ?
                            'AT' . $node['answertest'] : self::split_answertest(self::get_default('node', 'answertest'))[0];
                        $diffsans = isset($node['sans']) ? $node['sans'] : self::get_default('node', 'sans');
                        $difftans = isset($node['tans']) ? $node['tans'] : self::get_default('node', 'tans');
                        $difftestoptions = isset($node['testoptions']) ?
                            $node['testoptions'] : self::get_default('node', 'testoptions');
                        $diffnode['answertest'] =
                            "{$diffanswertest}({$diffsans},{$difftans}" .
                            ($difftestoptions !== '' ? ",{$difftestoptions}" : '') . ')';
                        unset($diffnode['sans']);
                        unset($diffnode['tans']);
                        unset($diffnode['testoptions']);
                    }
                    $diffprt['node'][] = $diffnode;
                }
                $diffprts[] = $diffprt;
            }
            $diff['prt'] = $diffprts;
        } else if (
            ((!$isfeedback && $isdefaultprt) || $isrequesteddefaultprt) &&
            ((!$isquestiontext && $isdefaultinput) || $isrequesteddefaultinput)
        ) {
            $prtnode = ['name' => self::get_default('node', 'name'),
                    'answertest' => self::get_default('node', 'answertest')];
            if (substr($prtnode['answertest'], 0, 2) !== 'AT') {
                $prtnode['sans'] = self::get_default('node', 'sans');
                $prtnode['tans'] = self::get_default('node', 'tans');
            }
            $prtnode['quiet'] = self::get_default('node', 'quiet');
            $diff['prt'] = [['name' => self::get_default('prt', 'name'),
                'autosimplify' => self::get_default('prt', 'autosimplify'),
                'feedbackstyle' => self::get_default('prt', 'feedbackstyle'),
                'node' => [$prtnode]]];
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
                foreach ($test['testinput'] ?? [] as $tinput) {
                    $difftinput = [];
                    $difftinput['name'] = $tinput['name'];
                    $difftinput = array_merge($difftinput, self::obj_diff(self::$defaults['testinput'], $tinput));
                    $difftest['testinput'][] = $difftinput;
                }
                foreach ($test['expected'] ?? [] as $texpected) {
                    $difftexpected = [];
                    $difftexpected['name'] = $texpected['name'];
                    $difftexpected = array_merge($difftexpected, self::obj_diff(self::$defaults['expected'], $texpected));
                    $difftest['expected'][] = $difftexpected;
                }
                $difftests[] = $difftest;
            }
            $diff['qtest'] = $difftests;
        }
        if ($useyaml) {
            self::require_yaml();
            $yaml = Yaml::dump($diff, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_COMPACT_NESTED_MAPPING);
            return $yaml;
        } else {
            $xmlstring = self::yaml_to_xmlstring($diff);
            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($xmlstring);
            return $dom->saveXML();
        }
    }

    /**
     * Compares two objects and returns the differences as an array.
     *
     * @param object $obj1 The first object to compare.
     * @param object $obj2 The second object to compare.
     * @return array An associative array of differences.
     */
    public static function obj_diff($obj1, $obj2): array {
        $a1 = (array) $obj1;
        $a2 = (array) $obj2;
        return self::arr_diff($a1, $a2);
    }

    /**
     * Compares two arrays and returns the differences as an associative array.
     *
     * @param array $a1 The first array to compare.
     * @param array $a2 The second array to compare.
     * @return array An associative array of differences.
     */
    public static function arr_diff($a1, $a2): array {
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
                if (is_array($v)) {
                    $rad = self::arr_diff($v, (array) $a2[$k]);
                    if (count($rad)) {
                        $r[$k] = $rad;
                    }
                    // Required to avoid rounding errors due to the
                    // conversion from string representation to double.
                } else if (is_double($v)) {
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

    /**
     * Adds a CDATA section to an XML node if the value contains special characters.
     *
     * @param SimpleXMLElement $xml The XML node to add the CDATA to.
     * @param string $value The value to add as CDATA.
     */
    public static function add_cdata(&$xml, $value) {
        if (!empty($value) && $value && htmlspecialchars($value, ENT_COMPAT) != $value) {
            $node = dom_import_simplexml($xml);
            $no = $node->ownerDocument;
            $node->appendChild($no->createCDATASection($value));
        } else {
            $xml[0] = $value;
        }
    }

    /**
     * Split a string into a 4-item array such that:
     * 'AAAA(X(X,X)XX, YYY[Y,Y], ZZZ, WWW)'
     * becomes:
     * [0] => 'AAAA'
     * [1] => 'X(X,X)XX'
     * [2] => 'YYY[Y,Y]'
     * [3] => 'ZZZ, WWW'
     * @param string $answertest
     * @return array
     */
    public static function split_answertest($answertest) {
        $result = [];
        $firstbracketpos = strpos($answertest, '(');
        if ($firstbracketpos === false) {
            // No brackets found — return original code and empty fields.
            return [$answertest, '', '', ''];
        }
        $result[] = substr($answertest, 0, $firstbracketpos);
        $testprops = substr($answertest, $firstbracketpos + 1, strrpos($answertest, ')') - $firstbracketpos - 1);

        $parenlevel = 0;
        $squarelevel = 0;
        $current = '';
        $count = 0;
        $len = strlen($testprops);
        for ($i = 0; $i < $len; $i++) {
            $char = $testprops[$i];
            if ($char === '(') {
                $parenlevel++;
                $current .= $char;
            } else if ($char === ')') {
                $parenlevel--;
                $current .= $char;
            } else if ($char === '[') {
                $squarelevel++;
                $current .= $char;
            } else if ($char === ']') {
                $squarelevel--;
                $current .= $char;
            } else if ($char === ',' && $parenlevel === 0 && $squarelevel === 0 && $count < 2) {
                // Split only on top-level commas (not inside () or []) and only for first two splits.
                $result[] = trim($current);
                $current = '';
                $count++;
            } else {
                $current .= $char;
            }
        }
        $result[] = trim($current);
        // Ensure always 4 items.
        while (count($result) < 4) {
            $result[] = '';
        }
        return $result;
    }
}
