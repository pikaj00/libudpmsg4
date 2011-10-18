#!/usr/bin/php
<?php

include 'libudpmsg4.php';

$p = new udpmsg4_packet;

$p = file_get_contents("php://stdin");

echo udpmsg4_packet::frame_msg($p);

?>
