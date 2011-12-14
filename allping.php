<?php
require ('config.php');

function tbl_to_id($tbl=null) {
	if ( $tbl === null )
	{
		return false;
	}
	preg_match('/wp_(.*)_posts/', $tbl, $b);
	return $b[1];
}

function get_blog_name($id=null) {
	if ( $id === null )
	{
		return false;
	}
	$info = array();
	$sql = sprintf("SELECT option_name, option_value FROM wp_%d_options WHERE option_name='siteurl' OR option_name='blogname';", $id);
	$query = mysql_query($sql);
	if (mysql_num_rows($query) > 0) {
		while ($r = mysql_fetch_array($query)) {
			if ($r[0] == "blogname") {
				$info['blogname'] = $r[1];			
			}
			if ($r[0] == "siteurl") {
				$info['siteurl'] = $r[1];
			}
		}
	}
	return $info;
}

function ping_it($data=null, $site=null) {
	if ( $data === null  || $site === null )
	{
		return false;
	}
	printf("Pinging: %s\n", $site);
	$s = parse_url($site);
	$path = rtrim(@$s['path'], '/');
	if (empty($path)) $path = "/";
	$output = "POST ".$path." HTTP/1.0".PHP_EOL;
	$output .= "Host: ".$s['host'].PHP_EOL;
	$output .= "Content-type: text/xml".PHP_EOL;
	$output .= "User-agent: ".USER_AGENT.PHP_EOL;
	$output .= "Content-length: ".strlen($data).PHP_EOL.PHP_EOL;
	$output .= $data;

	$fp = @fsockopen($s['host'], 80, $errno, $errstr);
	fputs($fp, $output);
	$contents = "";
	while (!feof($fp)) {
		$line = fgets($fp, 4096);
		$contents .= $line;
	}
	fclose($fp);
	//print $contents.PHP_EOL;
}

function get_ping_xml($tbl=null) {
	if ( $tbl === null )
	{
		return false;
	}
	$id = tbl_to_id($tbl);
	$blog = get_blog_name($id);
	$blogtitle = $blog['blogname'];
	$blogfeed = $blog['siteurl'];
	$blogfeed = rtrim($blogfeed, '/')."/feed";

	$xml = "<?xml version='1.0'?>".PHP_EOL;
	$xml .= "<methodCall>".PHP_EOL;
	$xml .= "<methodName>weblogUpdates.ping</methodName>".PHP_EOL;
	$xml .= "<params>".PHP_EOL;
	$xml .= "<param><value>".$blogtitle."</value></param>".PHP_EOL;
	$xml .= "<param><value>".$blogfeed."</value></param>".PHP_EOL;
	$xml .= "</params>".PHP_EOL;
	$xml .= "</methodCall>".PHP_EOL;

	return $xml;

}
function get_new_posts($tbl=null, $ping_services=null) {
	if ( $tbl === null || $ping_services === null )
	{
		return false;
	}
	// mysql> SELECT ID FROM wp_98_posts WHERE post_date > DATE_SUB(now(), INTERVAL 1 HOUR) AND post_status='publish';
	$posts = array();
	//print "==> table: ".$tbl.PHP_EOL;
	$sql = sprintf("SELECT ID FROM %s WHERE post_date > DATE_SUB(now(), INTERVAL 1 HOUR) AND post_status='publish'", $tbl);
	$query = mysql_query($sql);
	if (mysql_num_rows($query) > 0) {
		//print "INFO > Found new posts".PHP_EOL;
		$data = get_ping_xml($tbl);
		foreach ($ping_services as $ping) {
			ping_it($data, $ping);
		}
	} else {
		//print "INFO> No new posts found".PHP_EOL;
	}
	return $posts;
}

foreach ($wp_instances as $wp_instance) {

	print "==> ".$wp_instance['db_name'].PHP_EOL;
	print $wp_instance['db_name'].":".$wp_instance['db_user'].":".$wp_instance['db_pass'].":".$wp_instance['db_host'].PHP_EOL;
	//printf("%s-%s-%s-%s\n", $wp_instance['db_name'], $wp_instance['db_user'], $wp_instance['db_pass'], $wp_instance['db_host']);
	$db = mysql_connect($wp_instance['db_host'], $wp_instance['db_user'], $wp_instance['db_pass']);
	mysql_select_db($wp_instance['db_name'], $db);
	$tbl = mysql_query("SHOW TABLES LIKE 'wp%_posts'", $db);
	if (mysql_num_rows($tbl) > 0) {
		while ($r = mysql_fetch_array($tbl)) {
			if (!preg_match('/.*similar.*/', $r[0])) {
				get_new_posts($r[0], $ping_services);
			}
		}
	} else {
		print "ERR: No tables like posts found!".PHP_EOL;
	}
	mysql_close($db);
}
