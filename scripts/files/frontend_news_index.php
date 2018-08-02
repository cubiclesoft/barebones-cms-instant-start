<?php
	// News pattern.

	// Load the configuration.
	require_once "../config.php";

	// Load the SDK.
	require_once $config["rootpath"] . "/support/sdk_barebones_cms_lite.php";

	// Calculate the tag to retrieve based on the current URL.
	$tag = Request::GetURLBase();
	if (strncasecmp($tag, $config["tag_base_path"], strlen($config["tag_base_path"])) == 0)  $tag = substr($tag, strlen($config["tag_base_path"]));
	if ($tag === "" || $tag{0} !== "/")  $tag = "/" . $tag;

	// Create the options array.
	if (substr($tag, -1) === "/")
	{
		// Display a list.
		$mode = "list";
		$aoptions = array(
			"tag" => "~" . $tag,
			"type" => "story"
		);
	}
	else
	{
		// Extract the file extension (if any).
		$pos = strrpos($tag, "/");
		$ext = substr($tag, $pos + 1);
		$pos = strpos($ext, ".");
		if ($pos === false)  $ext = "";
		else
		{
			$ext = substr($ext, $pos + 1);
			$tag = substr($tag, 0, -(strlen($ext) + 1));
		}

		// Redirect if the request does not have a file extension.
		if ($ext === "")
		{
			header("Location: " . Request::GetFullURLBase() . ".html");

			exit();
		}

		// Display a single asset by UUID.
		$mode = "asset";
		$pos = strrpos($tag, "/");
		$uuid = substr($tag, $pos + 1);
		$tag = substr($tag, 0, $pos + 1);

		$aoptions = array(
			"uuid" => $uuid,
			"type" => "story"
		);
	}

	// Load the content from the API if the refresh key is used.
	$refresh = BarebonesCMSLite::CanRefreshContent($config["refresh_key"]);
//	if ($refresh || @filemtime(BarebonesCMSLite::GetCachedAssetsFilename($config["content_dir"], "news", $aoptions)) < time() - 5 * 60)
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
		$result = $cms->GetAssets($aoptions, ($mode === "list" ? (int)$config["max_assets"] : 1));
		if (!$result["success"])
		{
			echo "Failed to load assets.  Error:  " . htmlspecialchars($result["error"] . " (" . $result["errorcode"] . ")") . "<br>";

			exit();
		}

		$assets = $result["assets"];

		// Set up content defaults.
		$toptions = array(
			"maxwidth" => ($mode === "list" ? 250 : 920),
			"mimeinfomap" => $cms->GetDefaultMimeInfoMap(),
			"cachedir" => $config["file_cache_dir"],
			"cacheurl" => $config["file_cache_url"],
			"apidir" => $config["api_files_dir"],
			"apiurl" => $config["api_files_url"],
			"getfileurl" => $config["get_file_url"],
			"getfilesecret" => $config["get_file_secret"],
			"siteurl" => $config["rooturl"],
		);

		$toptions2 = $toptions;
		$toptions2["maxwidth"] = 250;

		$soptions = array(
		);

		// Transform the content for viewing.
		$processed = array(
			"/" => true
		);
		foreach ($assets as $num => $asset)
		{
			$asset = $cms->TransformStoryAssetBody($asset, $toptions);

			if ($mode === "list")
			{
				$asset = $cms->GenerateStoryAssetSummary($asset, $soptions);
				$asset["preftag"] = $cms->GetPreferredTag($asset["tags"], "/news/", "*/news/");
			}
			else if ($mode === "asset")
			{
				// Rebuild all associated sections.
				foreach ($asset["tags"] as $tag2)
				{
					if ($tag2{0} !== "/")  continue;

					while (!isset($processed[$tag2]))
					{
						$aoptions2 = array(
							"tag" => "~" . $tag2,
							"type" => "story"
						);

						$result = $cms->GetAssets($aoptions2, (int)$config["max_assets"]);
						if ($result["success"])
						{
							$assets2 = $result["assets"];

							foreach ($assets2 as $num2 => $asset2)
							{
								$asset2 = $cms->TransformStoryAssetBody($asset2, $toptions);
								$asset2 = $cms->GenerateStoryAssetSummary($asset2, $soptions);
								$asset2["preftag"] = $cms->GetPreferredTag($asset2["tags"], "/news/", "*/news/");

								$assets2[$num2] = $asset2;
							}

							// Store the content for later.
							$cms->CacheAssets($config["content_dir"], "news", $aoptions2, $assets2);

							$processed[$tag2] = true;

							// Go up one level.
							$tag2 = rtrim($tag2, "/");
							$pos = strrpos($tag2, "/");
							if ($pos === false)  break;
							$tag2 = substr($tag2, 0, $pos + 1);
						}
					}
				}
			}

			$assets[$num] = $asset;
		}

		// Store the content for later.
		if (count($assets) || $mode === "list")  $cms->CacheAssets($config["content_dir"], "news", $aoptions, $assets);
		else  @unlink($cms->GetCachedAssetsFilename($config["content_dir"], "news", $aoptions));
	}

	// Load the layout.
	require_once $config["rootpath"] . "/layout.php";

	// Load the content.
	$assets = BarebonesCMSLite::LoadCachedAssets($config["content_dir"], "news", $aoptions);

	$dispmap = array(
		"" => array(
			"_title" => "News",
			"_read_more" => "Read story",
			"_no_assets" => "No stories found.",
			"_publish_format" => function($ts) { return date("n/j/Y @ g:i a", $ts); }
		)
	);

	if ($mode === "list")
	{
		// Select page language.
		$pagelang = BarebonesCMSLite::GetPreferredLanguage((isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? $_SERVER["HTTP_ACCEPT_LANGUAGE"] : ""), $config["default_lang"]);

		// Select a language mapping to use for the page based on language code.
		if ($pagelang !== "" && isset($dispmap[$pagelang]))
		{
			foreach ($dispmap[""] as $key => $val)
			{
				if (!isset($dispmap[$pagelang][$key]))  $dispmap[$pagelang][$key] = $val;
			}

			$dispmap = $dispmap[$pagelang];
		}
		else
		{
			$dispmap = $dispmap[""];
		}

		// Process format options here (e.g. "rss" could return the assets as a RSS feed, "json" for JSON, etc).
//		if (isset($_REQUEST["format"]) && $_REQUEST["format"] === "rss")  {  }

		$parts = explode("/", $tag);
		$breadcrumbs = array();
		$parts2 = array();
		$title = $dispmap["_title"];
		foreach ($parts as $part)
		{
			$part = trim($part);
			if ($part !== "")
			{
				$parts2[] = $part;
				$part = (isset($dispmap[$part]) ? $dispmap[$part] : ucfirst($part));
				$breadcrumbs[] = "<a href=\"" . htmlspecialchars($config["rooturl"] . "/" . implode("/", $parts2)) . "/\">" . htmlspecialchars($part) . "</a>";
				$title = $part;
			}
		}

		if (count($breadcrumbs))  array_pop($breadcrumbs);

		// Output site header.
		OutputHeader($title);

		// Display the page content.
		if ($refresh)  BarebonesCMS::OutputHeartbeat();

?>
<div class="contentwrap">
<div class="contentwrapinner">
<?php if (count($breadcrumbs))  echo "<div class=\"breadcrumbs\">" . implode(" &raquo; ", $breadcrumbs) . "</div>\n"; ?>
<h1><?=htmlspecialchars($title)?></h1>

<?php
		if (count($assets))
		{
?>
<div class="assetswrap">
<?php
			foreach ($assets as $asset)
			{
				$lang = BarebonesCMSLite::GetPreferredAssetLanguage($asset, (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? $_SERVER["HTTP_ACCEPT_LANGUAGE"] : ""), $config["default_lang"]);

				$url = $config["rooturl"] . $asset["preftag"] . $asset["uuid"] . ".html";

?>
	<div class="assetwrap">
		<?php if ($asset["langinfo"][$lang]["img"] !== false)  echo "<div class=\"assetimage\">" . $asset["langinfo"][$lang]["img"] . "</div>\n"; ?>
		<div class="assettitle"><a href="<?=htmlspecialchars($url)?>"><?=htmlspecialchars($asset["langinfo"][$lang]["title"])?></a></div>
		<div class="assetpublished"><?=htmlspecialchars($dispmap["_publish_format"]($asset["publish"]))?></div>

		<?=implode("\n", $asset["langinfo"][$lang]["summary"])?>

		<div class="assetreadlink"><a href="<?=htmlspecialchars($url)?>"><?=htmlspecialchars($dispmap["_read_more"])?></a></div>
	</div>
<?php
			}
?>
</div>
<?php
		}
		else
		{
			echo "<p>" . $dispmap["_no_assets"] . "</p>\n";
		}
?>
</div>
</div>
<?php

		// Output site footer.
		OutputFooter();
	}
	else if ($mode === "asset")
	{
		if (!count($assets))
		{
			// If this wasn't actually an asset but a section that already exists, then redirect.
			$aoptions = array(
				"tag" => "~" . $tag . $aoptions["uuid"] . "/",
				"type" => "story"
			);

			if (file_exists(BarebonesCMSLite::GetCachedAssetsFilename($config["content_dir"], "news", $aoptions)))
			{
				header("Location: " . Request::GetFullURLBase() . "/");

				exit();
			}

			Output404();
		}

		$asset = $assets[0];

		// Handle redirect.
		if (isset($asset["langinfo"]["redirect"]))
		{
			header("Location: " . trim($asset["langinfo"]["redirect"]["title"]));

			exit();
		}

		// Handle permalink resolution.
		$preftag = BarebonesCMSLite::GetPreferredTag($asset["tags"], "/news/", "*/news/");
		if ($preftag === false)  Output404();
		if ($tag !== $preftag)
		{
			header("Location: " . Request::GetHost() . $config["rooturl"] . $preftag . $asset["uuid"] . ".html");

			exit();
		}

		// Select language.
		$lang = BarebonesCMSLite::GetPreferredAssetLanguage($asset, (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? $_SERVER["HTTP_ACCEPT_LANGUAGE"] : ""), $config["default_lang"]);
		$title = $asset["langinfo"][$lang]["title"];

		if ($lang !== "" && isset($dispmap[$lang]))
		{
			foreach ($dispmap[""] as $key => $val)
			{
				if (!isset($dispmap[$lang][$key]))  $dispmap[$lang][$key] = $val;
			}

			$dispmap = $dispmap[$lang];
		}
		else
		{
			$dispmap = $dispmap[""];
		}

		// Process file extension options here (e.g. "json" could return the asset as JSON).
//		if ($ext === "json")  {  }

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
<div class="assetpublished"><?=htmlspecialchars($dispmap["_publish_format"]($asset["publish"]))?></div>
<?php
		}
?>

<?=$asset["langinfo"][$lang]["body"]?>
</div>
</div>
<?php

		// Output site footer.
		OutputFooter();
	}
?>