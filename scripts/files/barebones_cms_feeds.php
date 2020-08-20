<?php
	// Handle processing incoming feed data for Instant Start installations.
	// (C) 2020 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	// Verify input.
	if (count($argv) != 2)  exit();

	$data = json_decode($argv[1], true);
	if (!is_array($data) || !isset($data["data"]) || !is_array($data["data"]))  exit();

	require_once "/var/www/config.php";
	require_once "/var/www/support/web_browser.php";

//file_put_contents($rootpath . "/last_asset.json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

	$tags = array();
	$web = new WebBrowser();
	foreach ($data["data"] as $type => $asset)
	{
		if (is_array($asset) && isset($asset["tags"]))
		{
			foreach ($asset["tags"] as $tag)
			{
				if (substr($tag, 0, 2) === "*/")  $tag = substr($tag, 1);

				if ($tag[0] === "/")
				{
					while (!isset($tags[$tag]))
					{
						$web->Process("http://127.0.0.1" . $tag . "?refresh=" . urlencode($config["refresh_key"]));

						$tags[$tag] = true;

						if (strlen($tag) > 6 && substr($tag, 0, 6) === "/news/" && substr($tag, -1) === "/")
						{
							$tag = explode("/", $tag);
							$tag = implode("/", array_slice(0, count($tag) - 2));
						}
					}
				}
			}
		}
	}
?>