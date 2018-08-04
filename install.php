<?php
	// Barebones CMS Instant Start installer.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	$rootpath = dirname(__FILE__);

	require_once $rootpath . "/support/cli.php";

	$prevpath = getenv("PATH");
	$path = ($prevpath === false ? "" : $prevpath . ":") . "/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin";
	putenv("PATH=" . $path);

	putenv("DEBIAN_FRONTEND=noninteractive");

	// Note that Nginx is not installed here.
	// The release found in the official Ubuntu/Debian repositories is mostly unsupported software by the Nginx community.
	if (!file_exists("/etc/apt/sources.list"))  CLI::DisplayError("The file '/etc/apt/sources.list' does not exist.  Not actually Debian-based?");
	system("/usr/bin/apt-get update");
	system("/usr/bin/apt-get -y install iptables-persistent fail2ban openssl git php-gd php-json php-sqlite3 php-curl");

	// Now that the environment is normalized, run the main script.
	system(escapeshellarg(PHP_BINARY) . " " . escapeshellarg($rootpath . "/scripts/main.php"));

	echo "\nInstallation complete.\n";

	// Reboot automatically as needed.
	if (file_exists("/var/run/reboot-required"))  system("reboot");

	putenv("PATH=" . $prevpath);
?>