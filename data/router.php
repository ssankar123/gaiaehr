<?php

/**
 * GaiaEHR (Electronic Health Records)
 * Copyright (C) 2013 Certun, LLC.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// TODO: This ROUTER much be part of Matcha::Connect to handle request from the client,
// TODO: this way the Matcha::Connect is in control
header('Content-type: text/html; charset=utf-8');
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

session_cache_limiter('private');
session_cache_expire(1);
session_name('GaiaEHR');
session_start();
session_regenerate_id(false);
setcookie(session_name(),session_id(),time()+86400, '/', "gaiaehr.com", false, true);

$site = isset($_SESSION['user']['site']) ? $_SESSION['user']['site'] : 'default';
if(!defined('_GaiaEXEC'))
	define('_GaiaEXEC', 1);
require_once(str_replace('\\', '/', dirname(dirname(__FILE__))) . '/registry.php');


/**
 * Load the configuration for the router.php (rpc) Remote Procedure Calls
 */
require('config.php');

/**
 * If the site configuration exist, fire up the database connection
 * otherwise don't try to connect.
 */
$conf = ROOT . '/sites/' . $site . '/conf.php';
if(file_exists($conf)){
	require_once(ROOT . '/sites/' . $site . '/conf.php');
	require_once(ROOT . '/classes/MatchaHelper.php');
    include_once(ROOT . '/dataProvider/ACL.php');
    include_once(ROOT . '/dataProvider/Globals.php');
    include_once(ROOT . '/dataProvider/Modules.php');

	if(!isset($_SESSION['install']) || (isset($_SESSION['install']) && $_SESSION['install'] != true)) {
        $modules = new Modules();
        $API = array_merge($API, $modules->getEnabledModulesAPI());
    }
}

class BogusAction {
	public $action;
	public $method;
	public $data;
	public $tid;
	public $module;
}

$isForm = false;
$isUpload = false;
$module = null;
$data = file_get_contents('php://input');

if(isset($data)){
	header('Content-Type: text/javascript');
	$data = json_decode($data);
	if(isset($_REQUEST['module'])){
		$module = $_REQUEST['module'];
	}
} else {
	if(isset($_POST['extAction'])){
		// form post
		$isForm = true;
		$isUpload = $_POST['extUpload'] == 'true';
		$data = new BogusAction();
		$data->action = $_POST['extAction'];
		$data->method = $_POST['extMethod'];
		$data->tid = isset($_POST['extTID']) ? $_POST['extTID'] : null;
		// not set for upload
		$data->data = array(
			$_POST,
			$_FILES
		);
		if(isset($_REQUEST['module']))
			$module = $_REQUEST['module'];

	} else {
		die('Invalid request.');
	}
}

function doRpc($cdata) {
	global $API, $module;
	try {
		if(!isset($cdata->action)){
			throw new Exception('Call to undefined action: ' . $cdata->action);
		}
		$action = $cdata->action;
		$a = $API[$action];

		$method = $cdata->method;

        // TODO: Create a config file for those classes and methods that not require authorization
        // TODO: Create am authorization for the SiteSetup. This has security flaws
		if(
			isset($_SESSION['user']) &&
			(isset($_SESSION['user']['auth']) && $_SESSION['user']['auth'] == true) ||
			(isset($_SESSION['user']['portal_authorized']) && $_SESSION['user']['portal_authorized'] == true ) ||
			($action == 'authProcedures' && $method == 'login') ||
			($action == 'PortalAuthorize' && $method == 'login') ||
			($action == 'PortalAuthorize' && $method == 'check') ||
			($action == 'CombosData' && $method == 'getActiveFacilities') ||
			($action == 'i18nRouter' && $method == 'getAvailableLanguages') ||
            ($action == 'CombosData' && $method == 'getTimeZoneList') || // Used by SiteSetup
            ($action == 'CombosData' && $method == 'getThemes') // Used by SiteSetup
		){

			$mdef = $a['methods'][$method];
			if(!$mdef){
				throw new Exception("Call to undefined method: $method on action $action");
			}

			$r = array(
				'type' => 'rpc',
				'tid' => $cdata->tid,
				'action' => $action,
				'method' => $method
			);
			if(isset($module))
            {
				require_once(ROOT . "/modules/$module/dataProvider/$action.php");
				$action = "\\modules\\$module\\dataProvider\\$action";
				$o = new $action();
			}
            else
            {
				require_once(ROOT . "/dataProvider/$action.php");
				$o = new $action();
			}

			if(isset($mdef['len']))
            {
				$params = isset($cdata->data) && is_array($cdata->data) ? $cdata->data : array();
			}
            else
            {
				$params = array($cdata->data);
			}

			if(isset($_SESSION['hooks']) && isset($_SESSION['hooks'][$action][$method]['Before'])){
				foreach($_SESSION['hooks'][$action][$method]['Before']['hooks'] as $i => $hook){
					include_once($hook['file']);
					$Hook = new $i();
					$params = array(call_user_func_array(array($Hook, $hook['method']), $params));
					unset($Hook);
				}
			}

			$r['result'] = call_user_func_array(array($o, $method), $params);
			unset($o);

			if(isset($_SESSION['hooks']) && isset($_SESSION['hooks'][$action][$method]['After'])){
				foreach($_SESSION['hooks'][$action][$method]['After']['hooks'] as $i => $hook){
					include_once($hook['file']);
					$Hook = new $i();
					$r['result'] = call_user_func(array($Hook, $hook['method']), $r['result']);
					unset($Hook);
				}
			}
		}else{
			throw new Exception('Not Authorized');
		}

	} catch(Exception $e) {
		$r['type'] = 'exception';
		$r['message'] = $e->getMessage();
		$r['where'] = $e->getTraceAsString();
	}
	return $r;
}

function utf8_encode_deep(&$input) {
	if (is_string($input)) {
		if(mb_check_encoding($input, 'UTF-8')) return;
		$input = utf8_encode($input);
	} else if (is_array($input)) {
		foreach ($input as &$value) {
			utf8_encode_deep($value);
		}
		unset($value);
	} else if (is_object($input)) {
		$vars = array_keys(get_object_vars($input));
		foreach ($vars as $var) {
			utf8_encode_deep($input->$var);
		}
	}
}

$response = null;
if(is_array($data)){
	$response = array();
	foreach($data as $d){
		$response[] = doRpc($d);
	}
} else {
	$response = doRpc($data);
}

utf8_encode_deep($response);

if($isForm && $isUpload){
	print '<html><body><textarea>';
	$json = htmlentities(json_encode($json), ENT_NOQUOTES | ENT_SUBSTITUTE , 'UTF-8');
    $json = mb_convert_encoding($json, 'UTF-8');
	print $json;
	print '</textarea></body></html>';
} else {
	header('Content-Type: application/json; charset=utf-8');
	$json = htmlentities(json_encode($response), ENT_NOQUOTES | ENT_SUBSTITUTE , 'UTF-8');
    $json = json_encode($response);
    $json = mb_convert_encoding($json, 'UTF-8');
	print $json;
}

/**
 * Close the connection to the database if the site configuration was found.
 */
if(file_exists($conf)) Matcha::$__conn = null;
