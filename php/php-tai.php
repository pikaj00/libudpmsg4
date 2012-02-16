<?php

class tai_engine {
 var $p=NULL;
 var $pipes=NULL;
 function __construct () {
  $this->p=proc_open('./tai',array(array('pipe','r'),array('pipe','w')),$this->pipes);
  if (!is_resource($this->p)) die("tai failed to start");
 }
 static function fread_all ($fd,$len) {
  $buffer='';
  while (strlen($buffer)<$len) {
   $tmpbuf=fread($fd,$len-strlen($buffer));
   if ($tmpbuf===FALSE) return $tmpbuf;
   $buffer.=$tmpbuf;
   if (feof($fd)) return $buffer;
  }
  return $buffer;
 }
 function udpmsg4_decode ($frame) {
  $p=array();
  while (strlen($frame)) {
   $klen=ord($frame[0]);
   $key=substr($frame,1,$klen);
   $vlen=ord($frame[$klen+1])*256+ord($frame[$klen+2]);
   $val=substr($frame,$klen+3,$vlen);
   $p[$key]=$val;
   $frame=substr($frame,$klen+$vlen+3);
  }
  return $p;
 }
 function udpmsg4_request ($packet) {
  $frame='';
  foreach ($packet as $key => $value)
   $frame.=chr(strlen($key)).$key.chr(strlen($value)/256).chr(strlen($value)%256).$value;
  if (strlen($frame)>65536) return FALSE;
  fwrite($this->pipes[0],chr(strlen($frame)/256).chr(strlen($frame)%256).$frame);
  flush($this->pipes[0]);
  $len=fread($this->pipes[1],2);
  if (strlen($len)!=2) die("tai read len failed");
  $len=ord($len[0])*256+ord($len[1]);
  $frame=self::fread_all($this->pipes[1],$len);
  if ($len!=strlen($frame)) die("tai read frame failed");
  $p=$this->udpmsg4_decode($frame);
  return $p;
 }
 function taia_now () {
  $packet=array('CMD'=>'taia_now');
  $p=$this->udpmsg4_request($packet);
  if (ord($p['ret'])) return FALSE;
  return $p['t'];
 }
 function taia_less ($a,$b) {
  $packet=array('CMD'=>'taia_less','a'=>$a,'b'=>$b);
  $p=$this->udpmsg4_request($packet);
  return $p['ret'];
 }
 function taia_add ($a,$b) {
  $packet=array('CMD'=>'taia_add','a'=>$a,'b'=>$b);
  $p=$this->udpmsg4_request($packet);
  if (ord($p['ret'])) return FALSE;
  return $p['t'];
 }
 function taia_sub ($a,$b) {
  $packet=array('CMD'=>'taia_sub','a'=>$a,'b'=>$b);
  $p=$this->udpmsg4_request($packet);
  if (ord($p['ret'])) return FALSE;
  return $p['t'];
 }
 function taia_half ($a) {
  $packet=array('CMD'=>'taia_half','a'=>$a);
  $p=$this->udpmsg4_request($packet);
  if (ord($p['ret'])) return FALSE;
  return $p['t'];
 }
 function seconds2taia ($seconds) {
  $packet=array('CMD'=>'seconds2taia','seconds'=>pack('N',$seconds));
  $p=$this->udpmsg4_request($packet);
  if (ord($p['ret'])) return FALSE;
  return $p['t'];
 }
 function isoldtaia ($seconds,$a) {
  $packet=array('CMD'=>'taia_less','seconds'=>pack('N',$seconds),'a'=>$a);
  $p=$this->udpmsg4_request($packet);
  return $p['ret'];
 }
 static function tai_engine () {
  static $engine=NULL;
  if ($engine===NULL) $engine = new tai_engine;
  return $engine;
 }
}

if (!function_exists('libtai_taia_now')) {
 function libtai_taia_now () { return tai_engine::tai_engine()->taia_now(); }
 function libtai_taia_less ($a,$b) { return tai_engine::tai_engine()->taia_less($a,$b); }
 function libtai_taia_add ($a,$b) { return tai_engine::tai_engine()->taia_add($a,$b); }
 function libtai_taia_sub ($a,$b) { return tai_engine::tai_engine()->taia_sub($a,$b); }
 function libtai_taia_half ($a) { return tai_engine::tai_engine()->taia_half($a); }
 function libtai_seconds2taia ($seconds) { return tai_engine::tai_engine()->seconds2taia($seconds); }
 function libtai_isoldtaia ($seconds,$a) { return tai_engine::tai_engine()->isoldtaia($seconds,$a); }
}

if (!function_exists('taia_now')) {
 function taia_now () { return libtai_taia_now(); }
 function taia_less ($a,$b) { return libtai_taia_less($a,$b); }
 function taia_add ($a,$b) { return libtai_taia_add($a,$b); }
 function taia_sub ($a,$b) { return libtai_taia_sub($a,$b); }
 function taia_half ($a) { return libtai_taia_half($a); }
 function seconds2taia ($seconds) { return libtai_seconds2taia($seconds); }
 function isoldtaia ($seconds,$a) { return libtai_isoldtaia($seconds,$a); }
}

?>
