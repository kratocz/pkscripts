#!/usr/bin/php
<?php
$fni = "make.ini";
$fno = "make2.ini";
$ci = file($fni);
$argi = 1;

if (!isset($argv[$argi])) {
  print "Kratopaltool by Petr Kratochvil (c) 2012 krato@krato.cz, comes to you with no guaratees.\n";
  print "Usage: $argv[0] {add|del} module <module_name>\n";
  exit;
}

$cmd = $argv[$argi++];



foreach ($ci as $rowid => $row) {
  $row = trim($row);

}
