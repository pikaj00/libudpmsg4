#!/usr/bin/php
<?php

include 'libudpmsg4.php';

$p = new udpmsg4_packet;

$argv0 = array_shift($argv);

foreach ($argv as $arg) {
 list ($key,$value) = explode('=',$arg,2);
 $p[$key]=$value;
}

echo $p;

?>
