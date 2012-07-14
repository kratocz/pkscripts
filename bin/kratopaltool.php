#!/usr/bin/php
<?php
$fni = "make.ini";
$fno = "make.ini";
$ci = file($fni);
$co = array();
$argi = 1;
$instance_updir = "~/pkplatforms";

if (!isset($argv[$argi])) {
	print "Kratopaltool by Petr Kratochvil (c) 2012 krato@krato.cz, comes to you with no guaratees.\n";
	print "Usage: $argv[0] {add|del|find} module <module_name>\n";
	exit;
}

function expect($expected, $str) {
	if ($expected != $str) {
		print "ERROR:\nExpected: $expected\nFound: $str\n";
	}
}

function parse_module_name($line) {
	$pattern = "/^\\s*projects\\[\\]\\s*=\\s*(.+)\\s*\$/i";
	if (preg_match($pattern, $line, $matches)) {
		return $matches[1];
	}
	return NULL;
}

foreach ($ci as $rowid => $row) {
	$ci[$rowid] = rtrim($row);
}

$cmd = $argv[$argi++];

if ($cmd == "del") {
	expect("module", $argv[$argi++]);
	$module = $argv[$argi++];
	foreach ($ci as $rowid => $row) {
		$mod = parse_module_name($row);
		if ($mod == $module) {
			print "Deleting: $row\n";
		} else {
			$co[] = $row;
		}
	}
	file_put_contents($fno, implode("\n", $co)."\n");
} else if ($cmd == "find") {
	expect("module", $argv[$argi++]);
	$module = $argv[$argi++];
	$found = FALSE;
	foreach ($ci as $rowid => $row) {
		$mod = parse_module_name($row);
		if ($mod == $module) {
			$found = TRUE;
			break;
		}
	}
	if ($found) {
		print "YES, module $module is on the list!\n";
		exit(0);
	} else {
		print "NO, module $module is not on the list.\n";
		exit(1);
	}
} else if ($cmd == "add") {
	expect("module", $argv[$argi++]);
	$module = $argv[$argi++];
	$first_modules_rowid = -1;
	$last_was_empty = FALSE;
	foreach ($ci as $rowid => $row) {
		$mod = parse_module_name($row);
		if ($row == "") {
			$last_was_empty = TRUE;
			continue;
		}
		if ($last_was_empty) {
			$first_modules_rowid = $rowid;
		}
		$last_was_empty = FALSE;
		if ($mod == "pathauto" || $mod == "views") {
			print "Found: $row\n";
			break;
		}
	}
	assert($first_modules_rowid <> -1) or die();
	print "First row: $first_modules_rowid: $ci[$first_modules_rowid]\n";
	$in_modules_section = FALSE;
	$inserted = FALSE;
	$new_row = "projects[] = $module";
	foreach ($ci as $rowid => $row) {
		$mod = parse_module_name($row);
		if (!$inserted) {
			if ($rowid == $first_modules_rowid) {
				$in_modules_section = TRUE;
			}
			if ($in_modules_section) {
				if ($row == "") {
					print "Inserting module: $module\n";
					print "Inserting before empty line.\n";
					$co[] = $new_row;
					$inserted = TRUE;
				} else if ($mod == $module) {
					print "Module $module already found.\n";
					exit;
				} else if ($mod !== NULL && $mod > $module) {
					print "Inserting module: $module\n";
					print "Inserting before module: $mod\n";
					$co[] = $new_row;
					$inserted = TRUE;
				}
			}
		}
		if ($row == "") {
			$in_modules_section = FALSE;
		}
//		if ($in_modules_section) {
//			print "Module: $mod\n";
//		}
		$co[] = $row;
	}
	file_put_contents($fno, implode("\n", $co)."\n");
} else if ($cmd == "make") {
	isset($argv[$argi]) or die("Missing parameter: <output_file_name>\n");
	$fno = $argv[$argi++];
	(substr($fno, -7) == ".tar.gz") or die("Name of output file must end with .tar.gz (drupal make's request).\n");
	$cwd = getcwd();
	$last_dir_pos = strripos($cwd, "/");
	if ($last_dir_pos === FALSE) {
		$last_dir_pos = -1;
	}
	$last_dir = substr($cwd, $last_dir_pos + 1);
	//echo "Project: $last_dir\n";
	//$instance = "$instance_updir/$last_dir";
	//echo "Instance: $instance\n";
	assert(file_exists("make.ini")) or die("File make.ini not found!\n");
	//$fno = "platform-$last_dir.tar.gz";
	//$fno_temp = "platform-$last_dir.tmp.tar.gz";
	//$fno_temp = tempnam("/tmp", "kratopaltool")."/".$fno;
	echo "Making drupal instance to the archive: $fno\n";
	if (file_exists($fno)) {
		unlink($fno);
	}
	passthru("drush make --tar make.ini $fno", $result);
	$ok = ($result == 0);
	if ($ok) {
		file_exists($fno) or die("Error: Huh! No error result from drush make, but output file not found!");
		$file_size = filesize($fno);
		$file_size_MB = ceil($file_size / 1024 / 1024);
		print "Everything is OK! Output file created ($file_size_MB MB): $fno\n";
		exit(0);
	} else {
		print "Error(s) found!\n";
		exit($result);
	}
} else if ($cmd == "deploy") {
	$fin = $argv[$argi++];
	$don = $argv[$argi++];
	(substr($fin, -7) == ".tar.gz") or die("Error: Name of input file must end with .tar.gz (drupal make's request).\n");
	file_exists($fin) or die("Error: Input file $fin not found!\n");
	$basename = substr($fin, 0, strlen($fin) - 7);
	$don_last_slash_pos = strripos($don, "/");
	($don_last_slash_pos !== FALSE) or die("Error: Bad output dir path!");
	$updir = substr($don, 0, $don_last_slash_pos);
	print "Uncompress platform $basename from $fin to $updir and rename it from $updir/$basename to $don ... ";
	$cwd = getcwd();
	chdir($updir) or die("Error: Unable to change directory to: $updir");
	passthru("tar -zxf $cwd/$fin", $result);
	chdir($cwd) or die("Error: Unable to change directory back to: $cwd");
	if ($result == 0) {
		rename("$updir/$basename", $don);
		print "ok\nDeployed to: $don\n";
		exit(0);
	}
	print "Error(s) found!\n";
	exit($result);
} else {
	print "ERROR: Unknown command: $cmd\n";
}
