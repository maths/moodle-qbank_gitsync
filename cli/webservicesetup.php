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
 * Set up a webservice. Based on https://gist.github.com/timhunt/51987ad386faca61fe013904c242e9b4 by Tim Hunt.
 *
 * @package    qbank_gitsync
 * @copyright  2023 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir.'/phpunit/classes/util.php');

$systemcontext = context_system::instance();

// Enable web services and REST protocol.
set_config('enablewebservices', true);
$enabledprotocols = get_config('core', 'webserviceprotocols');
if (stripos($enabledprotocols, 'rest') === false) {
    set_config('webserviceprotocols', $enabledprotocols . ',rest');
}

// Create a web service user.
$datagenerator = testing_util::get_data_generator();
$webserviceuser = $datagenerator->create_user([
    'username' => 'ws-gitsync-user', 'firstname' => 'Externalgitsync',
    'lastname' => 'User',
]);

// Create a web service role.
$wsroleid = create_role('WS Role for Externalgitsync', 'ws-gitsync-role', '');
set_role_contextlevels($wsroleid, [CONTEXT_SYSTEM]);
assign_capability('webservice/rest:use', CAP_ALLOW, $wsroleid, $systemcontext->id, true);
assign_capability('qbank/gitsync:exportquestions', CAP_ALLOW, $wsroleid, $systemcontext->id, true);
assign_capability('qbank/gitsync:importquestions', CAP_ALLOW, $wsroleid, $systemcontext->id, true);
assign_capability('qbank/gitsync:deletequestions', CAP_ALLOW, $wsroleid, $systemcontext->id, true);
assign_capability('qbank/gitsync:listquestions', CAP_ALLOW, $wsroleid, $systemcontext->id, true);

// Give the user the role.
role_assign($wsroleid, $webserviceuser->id, $systemcontext->id);

// Enable the gitsync webservice.
$webservicemanager = new webservice();
$service = $webservicemanager->get_external_service_by_shortname('qbank_gitsync_ws');
$service->enabled = true;
$service->uploadfiles = "1";
$webservicemanager->update_external_service($service);

// Authorise the user to use the service.
$webservicemanager->add_ws_authorised_user((object) [
    'externalserviceid' => $service->id,
    'userid' => $webserviceuser->id,
]);
