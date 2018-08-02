<?php
	// Automatic updater.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	$prevpath = getenv("PATH");
	$path = ($prevpath === false ? "" : $prevpath . ":") . "/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin";
	putenv("PATH=" . $path);

	putenv("DEBIAN_FRONTEND=noninteractive");

	system("/usr/bin/apt update");
	system("/usr/bin/apt -y dist-upgrade >/tmp/apt_log.txt 2>&1");
	system("/usr/bin/apt -y autoremove");
	system("/usr/bin/apt autoclean");

	// Reboot automatically as needed.
	if (file_exists("/var/run/reboot-required"))  system("reboot");

	putenv("PATH=" . $prevpath);
?>