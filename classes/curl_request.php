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
 * Wrapper class to allow mocking of curl requests in unit tests.
 *
 * Used outside Moodle.
 *
 * @package    qbank_gitsync
 * @copyright  2023 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace qbank_gitsync;

/**
 * cURL wrapper
 */
class curl_request {
    /** @var array cURL handle */
    private $curlhandle = null;

    /**
     * Wrap curl_init
     *
     * @param [type] $url
     */
    public function __construct($url) {
        $this->curlhandle = curl_init($url);
    }

    /**
     * Wrap curl_setopt
     *
     * @param [type] $name
     * @param [type] $value
     * @return void
     */
    public function set_option($name, $value) {
        curl_setopt($this->curlhandle, $name, $value);
    }

    /**
     * Wrap curl_exec
     */
    public function execute() {
        return curl_exec($this->curlhandle);
    }

    /**
     * Wrap curl_getinfo
     *
     * @param [type] $name
     */
    public function get_info($name) {
        return curl_getinfo($this->curlhandle, $name);
    }

    /**
     * Wrap curl_close
     *
     * @return void
     */
    public function close() {
        curl_close($this->curlhandle);
    }
}
