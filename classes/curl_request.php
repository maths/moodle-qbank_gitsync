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
     * Wrap curl_init.
     *
     * If the GITSYNC_CAINFO environment variable is set, explicitly applies
     * it as this handle's CURLOPT_CAINFO. This is deliberately not just left
     * to the curl.cainfo php.ini directive (which is what most guidance,
     * including this project's own doc/localsetup.md, points users to): on
     * at least some PHP/libcurl builds (observed with Homebrew PHP 8.5 on
     * macOS, libcurl 8.21.0/OpenSSL 3.6.3) curl_exec() silently does not
     * consult curl.cainfo at all, even though ini_get('curl.cainfo') shows
     * the value correctly set - so a user following the standard "point
     * curl.cainfo at your CA" advice for a local/self-signed Moodle instance
     * gets no error explaining why it still doesn't work, just the same
     * opaque "Broken JSON returned from Moodle" failure with an empty
     * response body. See doc/local-cainfo-not-applied.md for the full
     * writeup, reproduction steps and rationale.
     *
     * Only activates when the env var is explicitly set, so default
     * behaviour (system/ini-configured trust store) is completely
     * unaffected for the common case of a real Moodle instance with a
     * publicly-trusted certificate.
     *
     * @param [type] $url
     */
    public function __construct($url) {
        $this->curlhandle = curl_init($url);
        $cainfo = getenv('GITSYNC_CAINFO');
        if ($cainfo !== false && $cainfo !== '') {
            curl_setopt($this->curlhandle, CURLOPT_CAINFO, $cainfo);
        }
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
