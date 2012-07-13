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
	file_put_contents($fno, implode("\n", $co));
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
	file_put_contents($fno, implode("\n", $co));
} else if ($cmd == "make") {
	$cwd = getcwd();
	$last_dir_pos = strripos($cwd, "/");
	if ($last_dir_pos === FALSE) {
		$last_dir_pos = -1;
	}
	$last_dir = substr($cwd, $last_dir_pos + 1);
	echo "Project: $last_dir\n";
	$instance = "$instance_updir/$last_dir";
	echo "Instance: $instance\n";
	assert(file_exists("make.ini")) or die("File make.ini not found!\n");

	passthru("drush make --tar make.ini platform-$instance.tar.gz");
} else {
	print "ERROR: Unknown command: $cmd\n";
}
