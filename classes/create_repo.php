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
 * Wrapper class for processing performed by command line interface for creating a repo from Moodle.
 *
 * Utilised in cli\createrepo.php
 *
 * Allows mocking and unit testing via PHPUnit.
 * Used outside Moodle.
 *
 * @package    qbank_gitsync
 * @copyright  2023 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace qbank_gitsync;
/**
 * Export a Git repo.
 */
class create_repo {
    /**
     * Settings for POST request
     *
     * These are the parameters for the webservice call.
     *
     * @var array
     */
    public array $postsettings;
    /**
     * Settings for list POST request
     *
     * These are the parameters for the webservice call.
     *
     * @var array
     */
    public array $listpostsettings;
    /**
     * cURL request handle for file upload
     *
     * @var curl_request
     */
    public curl_request $curlrequest;
    /**
     * cURL request handle for question list retrieve
     *
     * @var curl_request
     */
    public curl_request $listcurlrequest;
    /**
     * Relative path of subdirectory to import.
     *
     * @var string
     */
    public string $subdirectory;
    /**
     * Path to actual manifest file
     *
     * @var string
     */
    public string $manifestpath;
    /**
     * Path to temporary manifest file
     *
     * @var string
     */
    public string $tempfilepath;
    /**
     * Path of root of repo
     * i.e. folder containing manifest
     *
     * @var string
     */
    public string $directory;

    /**
     * Obtain a list of questions and categories from Moodle, iterate through them and
     * export them one at a time. Create repo directories and files.
     *
     * @param cli_helper $clihelper
     * @param array $moodleinstances pairs of names and URLs
     * @return void
     */
    public function process(cli_helper $clihelper, array $moodleinstances):void {
        // Convert command line options into variables.
        // (Moodle code rules don't allow 'extract()').
        $arguments = $clihelper->get_arguments();
        $moodleinstance = $arguments['moodleinstance'];
        $this->directory = $arguments['directory'];
        $this->subdirectory = '';
        if ($arguments['subdirectory']) {
            $this->subdirectory = $arguments['subdirectory'];
        }
        $token = $arguments['token'];
        $contextlevel = $arguments['contextlevel'];
        $coursename = $arguments['coursename'];
        $modulename = $arguments['modulename'];
        $coursecategory = $arguments['coursecategory'];
        $this->manifestpath = cli_helper::get_manifest_path($moodleinstance, $contextlevel, $coursecategory,
                                                $coursename, $modulename, $this->directory);
        $this->tempfilepath = $this->directory . '/' .
                              $moodleinstance . '_' . $contextlevel . cli_helper::TEMP_MANIFEST_FILE;
        $help = $arguments['help'];

        if ($help) {
            $clihelper->showhelp();
            exit;
        }

        $moodleurl = $moodleinstances[$moodleinstance];
        $wsurl = $moodleurl . '/webservice/rest/server.php';

        $this->curlrequest = $this->get_curl_request($wsurl);
        $this->postsettings = [
            'wstoken' => $token,
            'wsfunction' => 'qbank_gitsync_export_question',
            'moodlewsrestformat' => 'json',
            'questionbankentryid' => null,
            'includecategory' => true,
        ];
        $this->curlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->curlrequest->set_option(CURLOPT_POST, 1);
        $this->listcurlrequest = $this->get_curl_request($wsurl);
        $this->listpostsettings = [
            'wstoken' => $token,
            'wsfunction' => 'qbank_gitsync_get_question_list',
            'moodlewsrestformat' => 'json',
            'contextlevel' => cli_helper::get_context_level($contextlevel),
            'coursename' => $coursename,
            'modulename' => $modulename,
            'coursecategory' => $coursecategory,
            'qcategoryname' => substr($this->subdirectory, 1)
        ];
        $this->listcurlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->listcurlrequest->set_option(CURLOPT_POST, 1);
        $this->listcurlrequest->set_option(CURLOPT_POSTFIELDS, $this->listpostsettings);

        $this->export_to_repo();
        $manifestcontents = new \stdClass();
        $manifestcontents->context = null;
        $manifestcontents->questions = [];
        cli_helper::create_manifest_file($manifestcontents, $this->tempfilepath, $this->manifestpath);

        return;
    }

    /**
     * Wrapper for cURL request to allow mocking.
     *
     * @param string $wsurl webservice URL
     * @return curl_request
     */
    public function get_curl_request($wsurl):curl_request {
        return new \qbank_gitsync\curl_request($wsurl);
    }

    /**
     * Loop through questions in manifest file, export each from Moodle and update local copy
     *
     * @return void
     */
    public function export_to_repo() {
        // Create manifest file.
        $manifestfile = fopen($this->manifestpath, 'a+');
        fclose($manifestfile);
        $questionsinmoodle = json_decode($this->listcurlrequest->execute());
        $tempfile = fopen($this->tempfilepath, 'a+');
        foreach ($questionsinmoodle as $questioninfo) {
            $this->postsettings['questionbankentryid'] = $questioninfo->questionbankentryid;
            $this->curlrequest->set_option(CURLOPT_POSTFIELDS, $this->postsettings);
            $responsejson = json_decode($this->curlrequest->execute());
            if (property_exists($responsejson, 'exception')) {
                echo "{$responsejson->message}\n";
                if (property_exists($responsejson, 'debuginfo')) {
                    echo "{$responsejson->debuginfo}\n";
                }
                echo "{$questioninfo->categoryname} - {$questioninfo->name} not downloaded.\n";
            } else {
                $categoryxml = simplexml_load_string($responsejson->question);
                unset($categoryxml->question[1]);
                $categorypath = $categoryxml->question->category->text->__toString();
                $questionxml = simplexml_load_string($responsejson->question);
                unset($questionxml->question[0]);
                $qname = $questionxml->question->name->text->__toString();
                $category = cli_helper::reformat_question($categoryxml->asXML());
                $question = cli_helper::reformat_question($questionxml->asXML());

                //TODO Is this needed?
                $directorylist = preg_split('~(?<!/)/(?!/)~', $categorypath);
                $directorylist = array_map(fn($dir) => trim(str_replace('//', '/', $dir)), $directorylist);
                $categorysofar = '';
                foreach ($directorylist as $categorydirectory) {
                    $categorysofar .= "/{$categorydirectory}";
                    $currentdirectory = $this->directory . $categorysofar;
                    if (!is_dir($currentdirectory)) {
                        mkdir($currentdirectory);
                    }
                }
                file_put_contents($currentdirectory . "/" . cli_helper::CATEGORY_FILE . "xml", $category);
                file_put_contents($currentdirectory . "/{$qname}.xml", $question);
                $fileoutput = [
                    'questionbankentryid' => $questioninfo->questionbankentryid,
                    'contextlevel' => $this->listpostsettings['contextlevel'],
                    'filepath' => $currentdirectory . "/{$qname}.xml",
                    'coursename' => $this->listpostsettings['coursename'],
                    'modulename' => $this->listpostsettings['modulename'],
                    'coursecategory' => $this->listpostsettings['coursecategory'],
                    'format' => 'xml',
                ];
                fwrite($tempfile, json_encode($fileoutput) . "\n");
            }
        }

        return;
    }

    /**
     * Tidy up question formatting and remove unwanted comment
     *
     * @param string $question original question XML
     * @return string tidied question XML
     */
    public function reformat_question(string $question):string {
        $locale = setlocale(LC_ALL, 0);
        // Options for HTML Tidy.
        // If in doubt, set to false to avoid unexpected 'repairs'.
        $sharedoptions = [
            'break-before-br' => true,
            'show-body-only' => true,
            'wrap' => '0',
            'indent' => true,
            'coerce-endtags' => false,
            'drop-empty-elements' => false,
            'drop-empty-paras' => false,
            'fix-backslash' => false,
            'fix-bad-comments' => false,
            'merge-emphasis' => false,
            'quote-ampersand' => false,
            'quote-nbsp' => false,
        ];
        if (!function_exists('tidy_repair_string')) {
            // Tidy not installed.
            return $question;
        }
        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = true;
        $dom->loadXML($question);

        $xpath = new \DOMXpath($dom);
        $tidyoptions = array_merge($sharedoptions, [
            'output-xhtml' => true
        ]);
        $tidy = new \tidy();

        // Find CDATA sections and format nicely.
        foreach ($xpath->evaluate("//*[@format='html']/text/text()") as $cdata) {
            if ($cdata->data) {
                $tidy->parseString($cdata->data, $tidyoptions);
                $tidy->cleanRepair();
                $output = tidy_get_output($tidy);
                $cdata->data = "\n{$output}\n";
            }
        }

        $cdataprettyxml = $dom->saveXML();

        // Remove question id comment.
        $xml = simplexml_load_string($cdataprettyxml);
        if (get_class($xml->comment) === 'SimpleXMLElement') {
            unset($xml->comment);
        }

        $noidxml = $xml->asXML();

        // Tidy the whole thing, including indenting CDATA as a whole.
        $tidyoptions = array_merge($sharedoptions, [
            'input-xml' => true,
            'output-xml' => true,
            'indent-cdata' => true
        ]);
        $tidy->parseString($noidxml, $tidyoptions);
        $tidy->cleanRepair();
        $result = tidy_get_output($tidy);
        // HTML Tidy switches to the default locale for the system. PHPUnit uses en_AU.
        // PHPUnit throws a warning unless we switch back to en_AU.
        setlocale(LC_ALL, $locale);

        return $result;
    }
}
