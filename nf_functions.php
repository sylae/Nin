<?php

/*
 * Begin the framework.
 */
function nf_begin($dir, $options = array())
{
	global $nf_www_dir;
	global $nf_dir;
	global $nf_uri;
	global $nf_cfg;
	
	nf_init_config($options);
	
	$nf_www_dir = $dir;
	$nf_dir = __DIR__;
	
	nf_init_autoloader();
	
	if($nf_cfg['sql']['enabled']) {
		if(!nf_sql_connect($nf_cfg['sql']['hostname'], $nf_cfg['sql']['username'], $nf_cfg['sql']['password'], $nf_cfg['sql']['database'])) {
			nf_error(7);
		}
	}
	
	$nf_uri = $_SERVER['REQUEST_URI'];
	$uri_part = strstr($nf_uri, '?', true);
	if($uri_part) {
		$nf_uri = $uri_part;
	}
	nf_handle_uri($nf_uri);
}

/**
 * Called by nf_begin() to merge the given options with $nf_cfg.
 */
function nf_init_config($options)
{
	global $nf_cfg;
	foreach($options as $k => $v) {
		if(!isset($nf_cfg[$k])) {
			$nf_cfg[$k] = $v;
			continue;
		}
		$nf_cfg[$k] = array_merge($nf_cfg[$k], $v);
	}
}

/**
 * Used by nf_autoload() to find an appropriate filename for the class.
 */
function nf_autoload_find($path, $classname)
{
	$test = $path . $classname . '.php';
	if(file_exists($test)) return $test;
	
	$test = $path . ucfirst($classname) . '.php';
	if(file_exists($test)) return $test;
	
	$test = $path . strtolower($classname) . '.php';
	if(file_exists($test)) return $test;
	
	return false;
}

/**
 * __autload() implementation.
 */
function nf_autoload($classname)
{
	global $nf_www_dir;
	global $nf_cfg;
	
	// Look for models
	$filename = nf_autoload_find($nf_www_dir . '/' . $nf_cfg['paths']['models'] . '/', $classname);
	if($filename !== false) {
		include($filename);
		return;
	}
	
	// Look for components
	$filename = nf_autoload_find($nf_www_dir . '/' . $nf_cfg['paths']['components'] . '/', $classname);
	if($filename !== false) {
		include($filename);
		return;
	}
	
	// If couldn't be found at all
	nf_error(8, $classname);
}

/**
 * Initialize the autoloader.
 */
function nf_init_autoloader()
{
	spl_autoload_register('nf_autoload');
}

/**
 * Translate a string.
 */
function nf_t($str)
{
	//TODO
	return $str;
}

/**
 * Function that is called for all handled errors.
 */
function nf_error($num, $details = '')
{
	$error = nf_t('Unknown');
	switch($num) {
		case 1: $error = nf_t('Invalid controller name'); break;
		case 2: $error = nf_t('Invalid action name'); break;
		case 3: $error = nf_t('Controller does not exist'); break;
		case 4: $error = nf_t('Controller class does not have the right name'); break;
		case 5: $error = nf_t('Action does not exist'); break;
		case 6: $error = nf_t('Action requires parameters not given'); break;
		case 7: $error = nf_t('Failed to connect to SQL database'); break;
		case 8: $error = nf_t('Class could not be found'); break;
		case 9: $error = nf_t('Table does not have a primary key'); break;
		case 10: $error = nf_t('SQL query failed'); break;
	}
	if($details != '') {
		$error .= ' (Details: "' . $details . '")';
	}
	
	//TODO, hook?
	echo 'nf error: ' . $error . '<br>';
}

/**
 * Error in resolving the routing path. This can be a fallback.
 * Called from routing functions such as nf_handle_uri() and nf_begin_page().
 */
function nf_error_routing($num, $details = '')
{
	global $nf_cfg;
	global $nf_uri;
	global $nf_uri_fallback;
	
	if($nf_uri_fallback) {
		nf_error($num, $details);
		return;
	}
	$nf_uri_fallback = true;
	
	if(!$nf_cfg['routing']['preferRules']) {
		$uri = nf_handle_routing_rules($nf_uri);
		if($uri !== $nf_uri) {
			$nf_uri = $uri;
			nf_handle_uri($nf_uri);
			return;
		}
	}
	
	nf_error($num, $details);
}

/**
 * Handle the REQUEST_URI (without the URL params) and invoke the controller/action.
 * This gets called from nf_begin().
 */
function nf_handle_uri($uri)
{
	global $nf_cfg;
	global $nf_uri;
	
	$nf_uri = $uri = substr($uri, strlen($nf_cfg['paths']['base'])-1);
	if($nf_cfg['routing']['preferRules']) {
		$nf_uri = $uri = nf_handle_routing_rules($uri);
	}
	
	$parts = array();
	$token = strtok($uri, '/');
	while($token !== false) {
		$parts[] = $token;
		$token = strtok('/');
	}
	
	$controller = 'index';
	$action = 'index';
	
	$partcount = count($parts);
	
	if($partcount >= 1) {
		$controller = strtolower($parts[0]);
		if(!preg_match($nf_cfg['validation']['regex_controllers'], $controller)) {
			nf_error_routing(1);
			return;
		}
	}
	
	if($partcount >= 2) {
		$action = strtolower($parts[1]);
		if(!preg_match($nf_cfg['validation']['regex_actions'], $action)) {
			nf_error_routing(2);
			return;
		}
	}
	
	nf_begin_page($controller, $action);
}

/**
 * Handle the REQUEST_URI (without the URL params) bsaed on the custom routing rules.
 * This returns true if there's a handled routing rule, or false if not.
 * This gets called from nf_handle_uri().
 */
function nf_handle_routing_rules($uri)
{
	global $nf_cfg;
	
	foreach($nf_cfg['routing']['rules'] as $regex => $route) {
		$matches = false;
		if(preg_match($regex, $uri, $matches)) {
			$keys = array();
			foreach($matches as $k => $v) {
				if(is_string($k)) {
					$keys[$k] = $v;
				}
			}
			$_GET = array_merge($_GET, $keys);
			$_REQUEST = array_merge($_REQUEST, $keys);
			return $route;
		}
	}
	
	return $uri;
}

/**
 * Begin the given page by controller name and action name.
 * This gets called from nf_handle_uri().
 */
function nf_begin_page($controllername, $actionname)
{
	global $nf_www_dir;
	global $nf_cfg;
	
	$filename = $nf_www_dir . '/' . $nf_cfg['paths']['controllers'] . '/' . $controllername . '.php';
	if(file_exists($filename)) {
		include($filename);
	} else {
		nf_error_routing(3, $filename);
		return;
	}
	
	$classname = ucfirst($controllername) . 'Controller';
	if(!class_exists($classname)) {
		nf_error_routing(4, $classname);
		return;
	}
	$controller = new $classname;
	
	$functionname = 'action' . ucfirst($actionname);
	if(!method_exists($controller, $functionname)) {
		nf_error_routing(5, $functionname);
		return;
	}
	
	$r = new ReflectionClass($classname);
	$m = $r->getMethod($functionname);
	
	$params = $m->getParameters();
	$args = array();
	foreach($params as $param) {
		if(isset($_REQUEST[$param->getName()])) {
			$args[] = $_REQUEST[$param->getName()];
		} else {
			if($param->isDefaultValueAvailable()) {
				$args[] = $param->getDefaultValue();
			} else {
				nf_error(6, $param->getName());
				return;
			}
		}
	}
	call_user_func_array(array($controller, $functionname), $args);
}

/**
 * Connect to the SQL database.
 */
function nf_sql_connect($host, $user, $pass, $db)
{
	global $nf_sql;
	if($nf_sql) {
		return false;
	}
	$nf_sql = new mysqli($host, $user, $pass, $db);
	return $nf_sql->connect_errno == 0;
}

/**
 * Perform a query on the SQL database.
 */
function nf_sql_query($query)
{
	global $nf_sql;
	$ret = $nf_sql->query($query);
	if($ret === false) {
		nf_error(10, 'Error was: ' . $nf_sql->error . ' - Query was: ' . $query);
	}
	return $ret;
}

/**
 * Escape the given string for SQL queries.
 */
function nf_sql_escape($str)
{
	global $nf_sql;
	return $nf_sql->real_escape_string($str);
}

/**
 * Encoding the given mixed-type object for a SQL query.
 */
function nf_sql_encode($o)
{
	if(is_string($o)) {
		return "'" . nf_sql_escape($o) . "'";
	}
	
	if(is_numeric($o)) {
		if(is_float($o)) {
			return floatval($o);
		}
		return intval($o);
	}
	
	return $o;
}
