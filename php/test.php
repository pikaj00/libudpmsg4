<?php

//$nacl_force_encdec=1;
include 'php/libudpmsg4.php';

/*
c1:
pubkey: 0a9503e3a319bad44edc223a3fbd6dd9ee4ab59ba26fa1c870411e4532e93c03
seckey: a2df13b1d4ac1a8cc738082b30952de2314aca43f5be0ea2fb5e0bdfb1baa8d4

c2:
pubkey: 3758e79dffc28e0ab87444c721b5d277fd12f46e5bb0c0e215db0d763270101b
seckey: 341b6b7d7e7681db737015910b34cc8184be6c7b8986beaad16131f482b62d29
*/

$commonconfig=array('keyring'=>array(
 '0a9503e3a319bad44edc223a3fbd6dd9ee4ab59ba26fa1c870411e4532e93c03'=>'/c1',
 '3758e79dffc28e0ab87444c721b5d277fd12f46e5bb0c0e215db0d763270101b'=>'/c2',
));

$c1 = new udpmsg4_client (array_merge($commonconfig,array(
 'seckey'=>'a2df13b1d4ac1a8cc738082b30952de2314aca43f5be0ea2fb5e0bdfb1baa8d4',
 'pubkey'=>'0a9503e3a319bad44edc223a3fbd6dd9ee4ab59ba26fa1c870411e4532e93c03',
 'netname'=>'c1',
 'user'=>'u1',
)));

$c2 = new udpmsg4_client (array_merge($commonconfig,array(
 'seckey'=>'341b6b7d7e7681db737015910b34cc8184be6c7b8986beaad16131f482b62d29',
 'pubkey'=>'3758e79dffc28e0ab87444c721b5d277fd12f46e5bb0c0e215db0d763270101b',
 'netname'=>'c1',
 'user'=>'u1',
)));

$f=$c1->send_message('/c2/u2','test');
//var_dump(array_keys($f->kvps));
$m=$c2->recv_packet($f);
var_dump($m);
$f=$c1->send_join('chat/anonet');
$m=$c2->recv_packet($f);
var_dump($m);
$f=$c1->send_quit('bye');
$m=$c2->recv_packet($f);
var_dump($m);

?>
