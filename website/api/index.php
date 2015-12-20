<?php

# Description: REST API "wrapper" for ShoplabAPI class
# Author: Fredrik Berglund
# Date: 2015-12-19

require_once 'functionality/router.php';
require_once 'functionality/api.php';

try {
	$route = new Router();
	$api = new ShoplabAPI();

	header('Content-type: application/json');

	if ($route->is_path('/')) {
		echo("hejsan");
	} else if ($route->is_path('/auth/')) {
		$user = isset($_REQUEST['user']) ? $_REQUEST['user'] : '';
		$pass = isset($_REQUEST['pass']) ? $_REQUEST['pass'] : '';

		$res = $api->auth($user, $pass);

		$output = array(
			'result' => $res ? true : false,
			'token' => $res ? "$res" : "",
			);

		echo(json_encode($output));
	} else {
		echo('404');
	}
} catch (Exception $e) {
	echo('An error occurred: ' . $e->getMessage());
}