<?php
	// Barebones CMS Instant Start main installation script.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// This script is called by 'install.php', which sets up the correct baseline environment (minus the timezone).
	$rootpath = dirname(__FILE__);

	require_once $rootpath . "/../support/cli.php";
	require_once $rootpath . "/../support/random.php";
	require_once $rootpath . "/../support/dir_helper.php";
	require_once $rootpath . "/functions.php";

	// Set the PHP timezone.
	$tz = trim(getenv("TZ"));
	if ($tz === "")  $tz = "Etc/UTC";
	date_default_timezone_set($tz);

	// Configure the OS.
	define("SYSTEM_FILES", "8192");

	require_once $rootpath . "/init_system.php";
	@system("service fail2ban restart");

	// Configure PHP CLI.
	require_once $rootpath . "/setup_php_cli.php";

	// Install and configure Nginx.
	require_once $rootpath . "/setup_nginx.php";

	// Install and configure PHP FPM.
	require_once $rootpath . "/setup_php_fpm.php";

	// Install and configure Cloud Storage Server.
	if (!file_exists("/var/scripts/cloud-storage-server-internal/README.md"))  @system("git clone https://github.com/cubiclesoft/cloud-storage-server.git /var/scripts/cloud-storage-server-internal");
	else
	{
		chdir("/var/scripts/cloud-storage-server-internal");
		@system("git pull");
	}

	if (!file_exists("/var/scripts/cloud-storage-server-feeds/README.md"))  @system("git clone https://github.com/cubiclesoft/cloud-storage-server-ext-feeds.git /var/scripts/cloud-storage-server-feeds");
	else
	{
		chdir("/var/scripts/cloud-storage-server-feeds");
		@system("git pull");
	}

	chdir($rootpath);

	@copy("/var/scripts/cloud-storage-server-feeds/server_exts/feeds.php", "/var/scripts/cloud-storage-server-internal/server_exts/feeds.php");

	// Run the installer but don't install the system service yet.
	@system(escapeshellarg(PHP_BINARY) . " /var/scripts/cloud-storage-server-internal/install.php serviceuser=root remotedapi= ipv6=N localhostonly=Y publichost=localhost port=9893 basepath= transferlimit= quota= ext_files_uploadlimit= servicename=-");

	// Set up the API user account.
	ob_start();
	@system(escapeshellarg(PHP_BINARY) . " /var/scripts/cloud-storage-server-internal/manage.php -s list");
	$data = json_decode(ob_get_contents(), true);
	ob_end_clean();

	if (!isset($data["users"]["barebones-cms"]))
	{
		@system(escapeshellarg(PHP_BINARY) . " /var/scripts/cloud-storage-server-internal/manage.php -s create username=barebones-cms basepath= quota= transferlimit=");
		@system(escapeshellarg(PHP_BINARY) . " /var/scripts/cloud-storage-server-internal/manage.php -s add-ext username=barebones-cms extension=feeds ext_guests=Y");

		ob_start();
		@system(escapeshellarg(PHP_BINARY) . " /var/scripts/cloud-storage-server-internal/manage.php -s list");
		$data = json_decode(ob_get_contents(), true);
		ob_end_clean();
	}

	$cssinfo = $data["users"]["barebones-cms"];

	// Run the installer again to install the system service.
	@system(escapeshellarg(PHP_BINARY) . " /var/scripts/cloud-storage-server-internal/install.php servicename=cloud-storage-server-internal");
	@system("service cloud-storage-server-internal restart");
	sleep(2);

	// Run a fake API call.
	require_once "/var/scripts/cloud-storage-server-feeds/sdk/support/sdk_cloud_storage_server_feeds.php";

	$css = new CloudStorageServerFeeds();
	$css->SetAccessInfo("http://127.0.0.1:9893", $cssinfo["apikey"], "", "");

	$result = $css->Notify("testing", "insert", "0", array(), time() - 1);
	if (!$result["success"])  CLI::DisplayError("An error occurred while connecting to Cloud Storage Server.", $result);

	// Update 'exectab.txt'.
	$filename = "/var/scripts/cloud-storage-server-internal/users/" . $cssinfo["id"] . "/feeds/exectab.txt";
	if (!file_exists($filename))  CLI::DisplayError("The file '" . $filename . "' does not exist.");

	$datamap = array(
		"# Barebones CMS feed processing." => "",
		"cms_api /usr/bin/php /var/scripts/barebones_cms_feeds.php" => ""
	);

	$lines = explode("\n", trim(file_get_contents($filename)));
	$lines = UpdateConfFile($lines, $datamap, "");
	file_put_contents($filename, implode("\n", $lines) . "\n");


	// Prepare a configuration file in case this script is run again.
	function GetObscureName()
	{
		global $rng, $freqmap;

		$words = array();
		for ($x = 0; $x < 3; $x++)  $words[] = preg_replace('/[^a-z]/', "-", strtolower($rng->GenerateWord($freqmap, $rng->GetInt(4, 8))));

		return implode("-", $words);
	}

	$rng = new CSPRNG();
	$freqmap = json_decode(file_get_contents($rootpath . "/../support/en_us_freq_3.json"), true);

	$installconfig = @json_decode(file_get_contents($rootpath . "/../config.dat"), true);
	if (!is_array($installconfig))  $installconfig = array();
	if (!isset($installconfig["apidir"]))  $installconfig["apidir"] = GetObscureName();
	if (!isset($installconfig["admindir"]))  $installconfig["admindir"] = GetObscureName();
	if (!isset($installconfig["filessecret"]))  $installconfig["filessecret"] = $rng->GenerateString(64);
	if (!isset($installconfig["refreshkey"]))  $installconfig["refreshkey"] = $rng->GenerateString(64);
	file_put_contents($rootpath . "/../config.dat", json_encode($installconfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
//var_dump($installconfig);

	// Deploy the Barebones CMS release distribution files to the correct locations.
	if (!file_exists("/var/scripts/barebones-cms-dist/README.md"))  @system("git clone https://github.com/cubiclesoft/barebones-cms.git /var/scripts/barebones-cms-dist");
	else
	{
		chdir("/var/scripts/barebones-cms-dist");
		@system("git pull");
	}

	chdir($rootpath);

	// Copy files to and prepare the various directories for installation.
	DirHelper::Copy("/var/scripts/barebones-cms-dist/api", "/var/www/api-" . $installconfig["apidir"]);
	DirHelper::Copy("/var/scripts/barebones-cms-dist/admin", "/var/www/admin-" . $installconfig["admindir"]);
	DirHelper::Copy("/var/scripts/barebones-cms-dist/sdks/php/support", "/var/www/support");
	@mkdir("/var/www/files");
	@mkdir("/var/protected_www/content", 0777, true);

	DirHelper::SetPermissions("/var/www/api-" . $installconfig["apidir"], false, "sftp-users", 02775, false, "sftp-users", 0664);
	DirHelper::SetPermissions("/var/www/admin-" . $installconfig["admindir"], false, "sftp-users", 02775, false, "sftp-users", 0664);
	DirHelper::SetPermissions("/var/www/support", false, "sftp-users", 02775, false, "sftp-users", 0664);
	DirHelper::SetPermissions("/var/www/files", "www-data", "sftp-users", 02775, "www-data", "sftp-users", 0664);
	DirHelper::SetPermissions("/var/protected_www", "www-data", "sftp-users", 02775, "www-data", "sftp-users", 0664);

	@chown("/var/www/api-" . $installconfig["apidir"], "www-data");
	@chown("/var/www/admin-" . $installconfig["admindir"], "www-data");


	// Install and configure Barebones CMS API.
	require_once "/var/www/support/tag_filter.php";

	$htmloptions = TagFilter::GetHTMLOptions();

	$web = new WebBrowser(array("extractforms" => true));
	$result = $web->Process("http://127.0.0.1/api-" . $installconfig["apidir"] . "/install.php");
	if (!$result["success"] || $result["response"]["code"] != 200)  CLI::DisplayError("API installation failed.  (installation_failed_0_1)", $result);

	// Go to step 1.
	$url = $result["url"];
	$html = TagFilter::Explode($result["body"], $htmloptions);
	$root = $html->Get();

	$rows = $root->Find('a[href*="action=step1"]');
	if (count($rows))
	{
		$url = HTTP::ConvertRelativeToAbsoluteURL($url, $rows->current()->href);

		$result = $web->Process($url);
		if (!$result["success"] || $result["response"]["code"] != 200)  CLI::DisplayError("API installation failed.  (installation_failed_1_1)", $result);
		if (count($result["forms"]) != 1)  CLI::DisplayError("API installation failed.  (installation_failed_1_2)");
		$form = $result["forms"][0];
		if ($form->GetFormValue("action") !== "step1")  CLI::DisplayError("API installation failed.  (installation_failed_1_3)");

		// Verify that the installer has only one error (SSL).
		$html = TagFilter::Explode($result["body"], $htmloptions);
		$root = $html->Get();

		$rows = $root->Find('div.formitemerror');
		if (count($rows) > 1)  CLI::DisplayError("API installation failed.  (installation_failed_1_4)");

		// Go to step 2.
		$result = $form->GenerateFormRequest();
		$result = $web->Process($result["url"], $result["options"]);
		if (!$result["success"] || $result["response"]["code"] != 200)  CLI::DisplayError("API installation failed.  (installation_failed_2_1)");
		if (count($result["forms"]) != 1)  CLI::DisplayError("API installation failed.  (installation_failed_2_2)");
		$form = $result["forms"][0];
		if ($form->GetFormValue("action") !== "step2")  CLI::DisplayError("API installation failed.  (installation_failed_2_3)");

		// Select SQLite.
		if (!$form->SetFormValue("db_select", "sqlite"))  CLI::DisplayError("API installation failed.  (installation_failed_2_4)");

		// Go to step 3.
		$result = $form->GenerateFormRequest();
		$result = $web->Process($result["url"], $result["options"]);
		if (!$result["success"] || $result["response"]["code"] != 200)  CLI::DisplayError("API installation failed.  (installation_failed_3_1)");
		if (count($result["forms"]) != 1)  CLI::DisplayError("API installation failed.  (installation_failed_3_2)");
		$form = $result["forms"][0];
		if ($form->GetFormValue("action") !== "step3")  CLI::DisplayError("API installation failed.  (installation_failed_3_3)");

		// Go to step 4.
		$result = $form->GenerateFormRequest("next");
		$result = $web->Process($result["url"], $result["options"]);
		if (!$result["success"] || $result["response"]["code"] != 200)  CLI::DisplayError("API installation failed.  (installation_failed_4_1)");
		if (count($result["forms"]) != 1)  CLI::DisplayError("API installation failed.  (installation_failed_4_2)");
		$form = $result["forms"][0];
		if ($form->GetFormValue("action") !== "step4")  CLI::DisplayError("API installation failed.  (installation_failed_4_3)");

		// Set paths for file storage.
		if (!$form->SetFormValue("files_path", "/var/www/files"))  CLI::DisplayError("API installation failed.  (installation_failed_4_4)");
		if (!$form->SetFormValue("files_url", "http://" . (getenv("PUBLIC_IPV4") != "" ? getenv("PUBLIC_IPV4") : "127.0.0.1") . "/files"))  CLI::DisplayError("API installation failed.  (installation_failed_4_5)");

		// Cloud Storage Server /feeds integration.
		if (!$form->SetFormValue("css_feeds_host", "http://127.0.0.1:9893"))  CLI::DisplayError("API installation failed.  (installation_failed_4_6)");
		if (!$form->SetFormValue("css_feeds_apikey", $cssinfo["apikey"]))  CLI::DisplayError("API installation failed.  (installation_failed_4_7)");
		if (!$form->SetFormValue("css_feeds_name", "cms_api"))  CLI::DisplayError("API installation failed.  (installation_failed_4_8)");

		// Install.
		$result = $form->GenerateFormRequest("next");
		$result = $web->Process($result["url"], $result["options"]);
		if (!$result["success"] || $result["response"]["code"] != 200)  CLI::DisplayError("API installation failed.  (installation_failed_5_1)");
		if (count($result["forms"]) != 0)  CLI::DisplayError("API installation failed.  (installation_failed_5_2)");

		if (!file_exists("/var/www/api-" . $installconfig["apidir"] . "/config.php"))  CLI::DisplayError("API installation failed.  (installation_failed_5_3)");
	}

	@chown("/var/www/api-" . $installconfig["apidir"] . "/config.php", "root");
	@chown("/var/www/api-" . $installconfig["apidir"] . "/future_cms_api.php", "root");
	@chown("/var/www/api-" . $installconfig["apidir"], "root");
	DirHelper::SetPermissions("/var/www/api-" . $installconfig["apidir"], false, "sftp-users", 02775, false, "sftp-users", 0664);

	require_once "/var/www/api-" . $installconfig["apidir"] . "/config.php";
	$apiconfig = $config;
//var_dump($apiconfig);


	// Install and configure Barebones CMS admin.
	$web = new WebBrowser(array("extractforms" => true));
	$result = $web->Process("http://127.0.0.1/admin-" . $installconfig["admindir"] . "/install.php");
	if (!$result["success"] || $result["response"]["code"] != 200)  CLI::DisplayError("Admin interface installation failed.  (installation_failed_0_1)");

	// Go to step 1.
	$url = $result["url"];
	$html = TagFilter::Explode($result["body"], $htmloptions);
	$root = $html->Get();

	$rows = $root->Find('a[href*="action=step1"]');
	if (count($rows))
	{
		$url = HTTP::ConvertRelativeToAbsoluteURL($url, $rows->current()->href);

		$result = $web->Process($url);
		if (!$result["success"] || $result["response"]["code"] != 200)  CLI::DisplayError("Admin interface installation failed.  (installation_failed_1_1)");
		if (count($result["forms"]) != 1)  CLI::DisplayError("Admin interface installation failed.  (installation_failed_1_2)");
		$form = $result["forms"][0];
		if ($form->GetFormValue("action") !== "step1")  CLI::DisplayError("Admin interface installation failed.  (installation_failed_1_3)");

		// Verify that the installer has only two errors or fewer (SSL and Imagick).
		$html = TagFilter::Explode($result["body"], $htmloptions);
		$root = $html->Get();

		$rows = $root->Find('div.formitemerror');
		if (count($rows) > 2)  CLI::DisplayError("Admin interface installation failed.  (installation_failed_1_4)");

		// Go to step 2.
		$result = $form->GenerateFormRequest();
		$result = $web->Process($result["url"], $result["options"]);
		if (!$result["success"] || $result["response"]["code"] != 200)  CLI::DisplayError("Admin interface installation failed.  (installation_failed_2_1)");
		if (count($result["forms"]) != 1)  CLI::DisplayError("Admin interface installation failed.  (installation_failed_2_2)");
		$form = $result["forms"][0];
		if ($form->GetFormValue("action") !== "step2")  CLI::DisplayError("Admin interface installation failed.  (installation_failed_2_3)");

		if (!$form->SetFormValue("readwrite_url", $apiconfig["rooturl"]))  CLI::DisplayError("Admin interface installation failed.  (installation_failed_2_4)");
		if (!$form->SetFormValue("readwrite_apikey", $apiconfig["readwrite_apikey"]))  CLI::DisplayError("Admin interface installation failed.  (installation_failed_2_5)");
		if (!$form->SetFormValue("readwrite_secret", $apiconfig["readwrite_secret"]))  CLI::DisplayError("Admin interface installation failed.  (installation_failed_2_6)");

		// Install.
		$result = $form->GenerateFormRequest("next");
		$result = $web->Process($result["url"], $result["options"]);
		if (!$result["success"] || $result["response"]["code"] != 200)  CLI::DisplayError("Admin interface installation failed.", "installation_failed_3_1");
		if (count($result["forms"]) != 0)  CLI::DisplayError("Admin interface installation failed.", "installation_failed_3_2");

		if (!file_exists("/var/www/admin-" . $installconfig["admindir"] . "/config.php"))  CLI::DisplayError("API installation failed.  (installation_failed_3_3)");
	}

	@chown("/var/www/admin-" . $installconfig["admindir"] . "/config.php", "root");
	@chown("/var/www/admin-" . $installconfig["admindir"], "root");
	DirHelper::SetPermissions("/var/www/admin-" . $installconfig["admindir"], false, "sftp-users", 02775, false, "sftp-users", 0664);


	// Set up Barebones CMS frontend file retrieval/storage options.
	@mkdir("/var/www/file_cache");
	DirHelper::SetPermissions("/var/www/file_cache", "www-data", "sftp-users", 02775, "www-data", "sftp-users", 0664);

	@mkdir("/var/www/getfile/cache", 0777, true);
	DirHelper::SetPermissions("/var/www/getfile/cache", "www-data", "sftp-users", 02775, "www-data", "sftp-users", 0664);

	$config = array(
		"rootpath" => "/var/www/getfile",
		"read_url" => $apiconfig["rooturl"],
		"read_apikey" => $apiconfig["read_apikey"],
		"secret" => $installconfig["filessecret"],
		"cache_dir" => "/var/www/getfile/cache",
		"api_dir" => "/var/www/files"
	);

	$data = "<" . "?php\n";
	$data .= "\$config = " . var_export($config, true) . ";\n";

	file_put_contents("/var/www/getfile/config.php", $data);
	file_put_contents("/var/www/getfile/index.php", file_get_contents($rootpath . "/files/frontend_getfile_index.php"));

	DirHelper::SetPermissions("/var/www/getfile", false, "sftp-users", 02775, false, "sftp-users", 0664);

	if (!file_exists("/var/scripts/barebones_cms_clear_cache.php"))
	{
		file_put_contents("/var/scripts/barebones_cms_clear_cache.php", file_get_contents($rootpath . "/files/barebones_cms_clear_cache.php"));
		@chgrp("/var/scripts/barebones_cms_clear_cache.php", "sftp-users");
		@chmod("/var/scripts/barebones_cms_clear_cache.php", 0660);
	}

	$filename = "/root/crontab_" . time() . ".txt";
	@system("crontab -l > " . escapeshellarg($filename));
	$data = trim(file_get_contents($filename));
	if (strpos($data, "/var/scripts/barebones_cms_clear_cache.php") === false)
	{
		$data .= "\n\n";
		$data .= "# Clear the Barebones CMS file cache.\n";
		$data .= "0 * * * * /usr/bin/php /var/scripts/barebones_cms_clear_cache.php >/dev/null 2>&1\n";

		$data = trim($data) . "\n";
		file_put_contents($filename, $data);
		@system("crontab " . escapeshellarg($filename));
	}

	@unlink($filename);


	// Set up Barebones CMS frontend indexes.
	$config = array(
		"rootpath" => "/var/www",
		"rooturl" => "",
		"tag_base_path" => "/",
		"max_assets" => 25,
		"refresh_key" => $installconfig["refreshkey"],
		"read_url" => $apiconfig["rooturl"],
		"read_apikey" => $apiconfig["read_apikey"],
		"file_cache_dir" => "/var/www/file_cache",
		"file_cache_url" => "/file_cache/",
		"api_files_dir" => "/var/www/files",
		"api_files_url" => "/files/",
		"get_file_url" => "/getfile/",
		"get_file_secret" => $installconfig["filessecret"],
		"content_dir" => "/var/protected_www/content",
		"default_lang" => $apiconfig["default_lang"],
		"admin_url" => "/admin-" . $installconfig["admindir"] . "/"
	);

	$data = "<" . "?php\n";
	$data .= "\$config = " . var_export($config, true) . ";\n";

	file_put_contents("/var/www/config.php", $data);
	file_put_contents("/var/www/index.php", file_get_contents($rootpath . "/files/frontend_index.php"));
	file_put_contents("/var/www/layout.php", file_get_contents($rootpath . "/files/frontend_layout.php"));
	file_put_contents("/var/www/main.css", file_get_contents($rootpath . "/files/frontend_main.css"));

	DirHelper::SetPermissions("/var/www", false, "sftp-users", 02775, false, "sftp-users", 0664, false);

	@mkdir("/var/www/news");
	file_put_contents("/var/www/news/index.php", file_get_contents($rootpath . "/files/frontend_news_index.php"));

	DirHelper::SetPermissions("/var/www/news", false, "sftp-users", 02775, false, "sftp-users", 0664);


	// Link Cloud Storage Server with the frontend.
	file_put_contents("/var/scripts/barebones_cms_feeds.php", file_get_contents($rootpath . "/files/barebones_cms_feeds.php"));
	@chgrp("/var/scripts/barebones_cms_feeds.php", "sftp-users");
	@chmod("/var/scripts/barebones_cms_feeds.php", 0660);

	if (file_exists("/var/www/api-" . $installconfig["apidir"] . "/future_cms_api.php"))
	{
		file_put_contents("/var/scripts/cloud-storage-server-internal/user_init/feeds/future_cms_api.php", file_get_contents("/var/www/api-" . $installconfig["apidir"] . "/future_cms_api.php"));
		@chgrp("/var/scripts/cloud-storage-server-internal/user_init/feeds/future_cms_api.php", "sftp-users");
		@chmod("/var/scripts/cloud-storage-server-internal/user_init/feeds/future_cms_api.php", 0660);
	}

	@system("service cloud-storage-server-internal restart");


	// Generate a README file for the root directory.
	$data = "Barebones CMS was installed successfully.  Access the admin interface here:\n\n";
	$data .= "http://" . (getenv("PUBLIC_IPV4") != "" ? getenv("PUBLIC_IPV4") : "127.0.0.1") . "/admin-" . $installconfig["admindir"] . "/\n\n";
	$data .= "Create a new story, add a tag called '/' (without the quotes), and save the content.\n\nThen visit the website:\n\n";
	$data .= "http://" . (getenv("PUBLIC_IPV4") != "" ? getenv("PUBLIC_IPV4") : "127.0.0.1") . "/\n";

	file_put_contents("/root/README-BarebonesCMS", $data);
	@chmod("/root/README-BarebonesCMS", 0600);
?>