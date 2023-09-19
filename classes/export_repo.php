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
 * Wrapper class for processing performed by command line interface for exporting a repo from Moodle.
 *
 * Utilised in cli\exportrepo.php
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
class export_repo {
    /**
     * Settings for POST request
     *
     * These are the parameters for the webservice call.
     *
     * @var array
     */
    public array $postsettings;
    /**
     * cURL request handle for file upload
     *
     * @var curl_request
     */
    public curl_request $curlrequest;
    /**
     * Full path to manifest file
     *
     * @var string
     */
    public string $manifestpath;

    /**
     * Iterate through the manifest file, request up to date versions via
     * the webservice and update local files.
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
        $this->manifestpath = $arguments['manifestpath'];
        $token = $arguments['token'];
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
            'questionbankentryid' => null
        ];
        $this->curlrequest->set_option(CURLOPT_RETURNTRANSFER, true);
        $this->curlrequest->set_option(CURLOPT_POST, 1);

        $this->export_to_repo();

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
        $manifestcontents = json_decode(file_get_contents($this->manifestpath));
        foreach ($manifestcontents->questions as $questioninfo) {
            $this->postsettings['questionbankentryid'] = $questioninfo->questionbankentryid;
            $this->curlrequest->set_option(CURLOPT_POSTFIELDS, $this->postsettings);
            $responsejson = json_decode($this->curlrequest->execute());
            if (property_exists($responsejson, 'exception')) {
                echo "{$responsejson->message}\n";
                if (property_exists($responsejson, 'debuginfo')) {
                    echo "{$responsejson->debuginfo}\n";
                }
                echo "{$questioninfo->filepath} not updated.\n";
            } else {
                $question = $this->reformat_question($responsejson->question);
                file_put_contents($questioninfo->filepath, $question);
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

        // Tidy the whole thing, cluding indenting CDATA as a whole.
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
