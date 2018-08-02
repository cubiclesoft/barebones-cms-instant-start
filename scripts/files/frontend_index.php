<?php
	// Static page pattern.

	// Load the configuration.
	require_once "config.php";

	// Load the SDK.
	require_once $config["rootpath"] . "/support/sdk_barebones_cms_lite.php";

	// Calculate the tag to retrieve based on the current URL.
	$tag = Request::GetURLBase();
	if (strncasecmp($tag, $config["tag_base_path"], strlen($config["tag_base_path"])) == 0)  $tag = substr($tag, strlen($config["tag_base_path"]));
	if ($tag === "" || $tag{0} !== "/")  $tag = "/" . $tag;

	// Redirect when there is no trailing slash for SEO purposes.
	if (substr($tag, -1) !== "/")
	{
		header("Location: " . Request::GetFullURLBase() . "/");

		exit();
	}

	// Create the options array.
	$aoptions = array(
		"tag" => $tag,
		"type" => "story"
	);

	// Load the content from the API if the refresh key is used.
	$refresh = BarebonesCMSLite::CanRefreshContent($config["refresh_key"]);
//	if ($refresh || @filemtime(BarebonesCMSLite::GetCachedAssetsFilename($config["content_dir"], "static_one", $aoptions)) < time() - 5 * 60)
	if ($refresh)
	{
		// Redirect if it looks like a refresh key was submitted.
		if (isset($_GET["refresh"]) || isset($_POST["refresh"]))
		{
			header("Location: " . Request::GetFullURLBase());

			exit();
		}

		require_once $config["rootpath"] . "/support/sdk_barebones_cms_api.php";

		// Initialize the SDK.
		$cms = new BarebonesCMS();
		$cms->SetAccessInfo($config["read_url"], $config["read_apikey"]);

		// Retrieve the content.
		$result = $cms->GetAssets($aoptions, 1);
		if (!$result["success"])
		{
			echo "Failed to load assets.  Error:  " . htmlspecialchars($result["error"] . " (" . $result["errorcode"] . ")") . "<br>";

			exit();
		}

		$assets = $result["assets"];

		// Set up content defaults.
		$toptions = array(
			"maxwidth" => 920,
			"mimeinfomap" => $cms->GetDefaultMimeInfoMap(),
			"cachedir" => $config["file_cache_dir"],
			"cacheurl" => $config["file_cache_url"],
			"apidir" => $config["api_files_dir"],
			"apiurl" => $config["api_files_url"],
			"getfileurl" => $config["get_file_url"],
			"getfilesecret" => $config["get_file_secret"],
			"siteurl" => $config["rooturl"],
		);

		// Transform the content for viewing.
		foreach ($assets as $num => $asset)
		{
			$assets[$num] = $cms->TransformStoryAssetBody($asset, $toptions);
		}

		// Store the content for later.
		if (count($assets))  $cms->CacheAssets($config["content_dir"], "static_one", $aoptions, $assets);
		else  @unlink($cms->GetCachedAssetsFilename($config["content_dir"], "static_one", $aoptions));
	}

	// Load the layout.
	require_once $config["rootpath"] . "/layout.php";

	// Load the content.
	$assets = BarebonesCMSLite::LoadCachedAssets($config["content_dir"], "static_one", $aoptions);
	if (!count($assets))  Output404();

	$asset = $assets[0];

	// Select language.
	$lang = BarebonesCMSLite::GetPreferredAssetLanguage($asset, (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? $_SERVER["HTTP_ACCEPT_LANGUAGE"] : ""), $config["default_lang"]);
	$title = $asset["langinfo"][$lang]["title"];

	// Handle redirect.
	if (strncasecmp($title, "redirect:", 9) == 0)
	{
		header("Location: " . trim(substr($title, 9)));

		exit();
	}

	// Output site header.
	OutputHeader($title);

	// Display the page content.
	if ($refresh)
	{
		BarebonesCMS::OutputHeartbeat();
		if ($config["admin_url"] !== false)  BarebonesCMS::OutputPageAdminEditButton($config["admin_url"], $asset, $lang);
	}

	if (isset($asset["langinfo"][$lang . "-hero-top"]))
	{
		echo $asset["langinfo"][$lang . "-hero-top"]["body"];

?>
<div class="contentwrap fancycontent">
<div class="contentwrapinner">
<?php
	}
	else
	{
?>
<div class="contentwrap">
<div class="contentwrapinner">
<h1><?=htmlspecialchars($title)?></h1>
<?php
	}
?>

<?=$asset["langinfo"][$lang]["body"]?>
</div>
</div>
<?php

	// Output site footer.
	OutputFooter();
?>