<?php
	function OutputHeader($title = "YourWebsiteHere", $desc = "", $img = false)
	{
		global $config;

		header("Content-Type: text/html; UTF-8");

?>
<!DOCTYPE html>
<html>
<head>
<title><?=htmlspecialchars($title)?></title>
<meta charset="utf-8">
<meta http-equiv="x-ua-compatible" content="ie=edge">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<link rel="stylesheet" href="<?=$config["rooturl"]?>/main.css" type="text/css" media="all">
<link rel="icon" type="image/png" sizes="256x256" href="<?=$config["rooturl"]?>/icon_256x256.png">
<link rel="shortcut icon" type="image/x-icon" href="/favicon.ico">
<?php
		if ($desc !== "")
		{
			if ($img === false)  $img = Request::PrependHost($config["rooturl"]) . "/icon_256x256.png";

?>
<meta name="description" content="<?=htmlspecialchars($desc)?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="@YourTwitterHandleHere">
<meta name="twitter:title" content="<?=htmlspecialchars($title)?>">
<meta name="og:title" content="<?=htmlspecialchars($title)?>">
<meta name="og:type" content="website">
<meta name="twitter:description" content="<?=htmlspecialchars($desc)?>">
<meta name="og:description" content="<?=htmlspecialchars($desc)?>">
<meta name="twitter:image" content="<?=htmlspecialchars($img)?>">
<meta name="og:image" content="<?=htmlspecialchars($img)?>">
<?php
		}
?>
</head>
<body>

<!-- Put your header/menu here. -->

<?php
	}

	function OutputFooter()
	{
		global $config;

?>

<!-- Put your footer/copyright here. -->

</body>
</html>
<?php
	}

	function Output404()
	{
		// Output site header.
		http_response_code(404);

		OutputHeader("Invalid Resource | YourWebsiteHere");

?>
<div class="contentwrap">
<div class="contentwrapinner">
<h1>Invalid Resource</h1>

The requested resource does not exist, was unpublished, or has moved.  Unfortunately, this is a 404 so there's nothing to do.
</div>
</div>
<?php

		// Output site footer.
		OutputFooter();

		exit();
	}
?>