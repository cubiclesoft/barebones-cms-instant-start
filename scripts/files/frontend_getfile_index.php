<?php
	// Load the configuration.
	require_once "config.php";

	require_once $config["rootpath"] . "/../support/sdk_barebones_cms_api.php";

	if (!isset($_REQUEST["id"]) || !is_string($_REQUEST["id"]))
	{
		http_response_code(404);

		echo "Missing asset ID.\n";

		exit();
	}

	if (!isset($_REQUEST["filename"]) || !is_string($_REQUEST["filename"]))
	{
		http_response_code(404);

		echo "Missing 'filename'.\n";

		exit();
	}

	// Verify the digital signature.  This helps prevent abuse of system resources.
	if (!isset($_REQUEST["sig"]) || !is_string($_REQUEST["sig"]) || !BarebonesCMS::IsValidFileSignature($_REQUEST["id"], (isset($_REQUEST["path"]) ? $_REQUEST["path"] : ""), $_REQUEST["filename"], (isset($_REQUEST["crop"]) ? $_REQUEST["crop"] : ""), (isset($_REQUEST["maxwidth"]) ? $_REQUEST["maxwidth"] : ""), $_REQUEST["sig"], $config["secret"]))
	{
		http_response_code(403);

		echo "Invalid 'sig'.\n";

		exit();
	}

	$cms = new BarebonesCMS();
	$cms->SetAccessInfo($config["read_url"], $config["read_apikey"]);

	$mimeinfomap = $cms->GetDefaultMimeInfoMap();

	$options = array(
		"cachedir" => $config["cache_dir"],
		"apidir" => $config["api_dir"],
		"path" => (isset($_REQUEST["path"]) && is_string($_REQUEST["path"]) ? $_REQUEST["path"] : ""),
		"download" => (isset($_REQUEST["download"]) && is_string($_REQUEST["download"]) ? $_REQUEST["download"] : false),
		"crop" => (isset($_REQUEST["crop"]) && is_string($_REQUEST["crop"]) ? $_REQUEST["crop"] : ""),
		"maxwidth" => (isset($_REQUEST["maxwidth"]) && is_numeric($_REQUEST["maxwidth"]) ? (int)$_REQUEST["maxwidth"] : -1),
		"mimeinfomap" => $mimeinfomap
	);

	$cms->DeliverFile($_REQUEST["id"], $_REQUEST["filename"], $options);
