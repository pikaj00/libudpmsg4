#!/usr/bin/php
<?php

include 'libudpmsg4.php';

$p = rtrim(file_get_contents("php://stdin"));

echo udpmsg4_client::hex2key($p);

?>
