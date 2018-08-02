<?php
	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once "/var/www/getfile/config.php";
	require_once "/var/www/support/sdk_barebones_cms_api.php";

	BarebonesCMS::CleanFileCache($config["cache_dir"]);
?>