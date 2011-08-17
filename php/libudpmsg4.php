<?php

if (defined('libudpmsg4_loaded')) return;
define('libudpmsg4_loaded', TRUE);

include 'php-nacl.php';

class udpmsg4_packet implements ArrayAccess {
 var $kvps;
 static function miniframe_msg ($msg) {
  $len = strlen($msg);
  if ($len > 255) return FALSE;
  $lens = chr($len);
  return $lens.$msg;
 }
 static function frame_msg ($msg) {
  $len = strlen($msg);
  if ($len > 1024) return FALSE;
  $lens = chr(floor($len / 256)).chr($len % 256);
  return $lens.$msg;
 }
 static function unminiframe_msg (&$b) {
  $len = ord($b[0]);
  if (strlen($b)-1 < $len) return NULL;
  $msg=substr($b,1,$len);
  $b=substr($b,$len+1);
  return $msg;
 }
 static function unframe_msg (&$b) {
  $len = ord($b[0]) * 256 + ord($b[1]);
  if (strlen($b)-2 < $len) return NULL;
  $msg=substr($b,2,$len);
  $b=substr($b,$len+2);
  return $msg;
 }
 static function parse (&$data) {
  $p = new udpmsg4_packet;
  $ret = array();
  while (strlen($data)) {
   $key=self::unminiframe_msg($data);
   if (($key===FALSE)||($key===NULL)) return $key;
   $value=self::unframe_msg($data);
   if (($value===FALSE)||($value===NULL)) return $value;
   $ret[$key]=$value;
  }
  $p->kvps=$ret;
  return $p;
 }
 function __construct ($data=NULL, $type=NULL) {
  if ($data===NULL) { $this->kvps=array(); return; }
  if ($type===NULL) {
   if (is_array($data)) $type='array';
   else if (is_string($data)) $type='unframed';
   else die("Error: can not find type");
  }
  switch ($type) {
   case 'array': $this->kvps=$data; break;
   case 'framed': $data=self::unframe_msg($data);
   case 'unframed':
    $p=self::parse($data);
    if (($p===NULL)||($p===FALSE)) die("Error: protocol error");
    $this->kvps=$p->kvps; break;
   default: die("Error: bad type $type");
  }
 }
 function unframed () {
  $msg='';
  foreach ($this->kvps as $key => $value)
   if ((($fkey=self::miniframe_msg($key))!==FALSE) && (($fvalue=self::frame_msg($value))!==FALSE)) $msg.=$fkey.$fvalue;
   else {
    fprintf(STDERR,"Error: packet too long\n");
    return FALSE;
   }
  return $msg;
 }
 function framed () {
  $unframed=$this->unframed();
  return self::frame_msg($unframed);
 }
 function kvp ($key,$max=1) {
  if ($max===1)
   if (isset($this->kvps[$key])) return $this->kvps[$key];
   else return NULL;
  if (isset($this->kvps[$key])) return array($this->kvps[$key]);
  else return array();
 }
 function offsetExists ($key) { return $this->kvp($key)!==NULL?TRUE:FALSE; }
 function offsetGet ($key) { return $this->kvp($key); }
 function offsetSet ($key,$value) { $this->kvps[$key]=$value; }
 function offsetUnset ($key) { unset($this->kvps[$key]); }
}

class udpmsg4_client {
 var $seckey;
 var $pubkey;
 var $netname;
 var $keyring;
 var $user;
 static function hex2key ($key) {
  $ret='';
  foreach (str_split($key,2) as $part) $ret.=pack('H*',$part);
  return $ret;
 }
 function __construct ($config=array()) {
  $this->seckey=self::hex2key($config['seckey']);
  $this->pubkey=self::hex2key($config['pubkey']);
  $this->netname=$config['netname'];
  if (isset($config['keyring'])) $this->keyring=$config['keyring'];
  else $this->keyring=array(
   '6db0a98838b798c1c857d13f1db486d40f36c0706e2ba4d7c507567bd2045d35'=>'/NNN',
   'd8f01ea5f6e7a3060572fa876fd64c3ce90a433c44fffafe19a73031b141f71c'=>'/KN',
   '9a36c5cbed4342e2a8af34355d47eb7a38c06afa0eda73c31a8a345572778362'=>'/FNX',
   '09fde77fa5d7b92c50b3a2abe0a304d760ee8ddd19ec31d5b4b6bcbc817a820a'=>'/sI2',
   '3f224a6f7924398f454d6bdd52ed46ad632c2f11413ef255c1811993522a632f'=>'/obeenet',
   '94aa84f033ddb4a019a4a3a583e7644f8dd8cd8dae67fb67c14db9e87f0be75f'=>'/atomic',
   '12ae24c362880cd6c0e2f186311ce95581c09164c99ae990e31227b0cb86ae19'=>'/A2',
   '54806f876f87c4b790b6c6e47e031f303a86ce48d2d0abbcf1f4c8fffcf88321'=>'/ca',
  );
  $this->user=$config['user'];
 }
 function create_frame_nocrypt ($p) {
  if (!isset($p['DUMMY'])) $p['DUMMY'] = rand(0, 999999);
  if (!isset($p['NET'])) $p['NET'] = $this->netname;
  $p = new udpmsg4_packet ($p);
  return $p->unframed();
 }
 function crypto_for ($p) {
  foreach ($this->keyring as $pubkey => $prefix)
   if (($p['DST']===$prefix) || (substr($p['DST'],0,strlen($prefix)+1)==="$prefix/"))
    return array('DSTKEY'=>self::hex2key($pubkey),'NONCE'=>'123456781234567812345678');
  return NULL;
 }
 function encrypt_frame ($frame,$crypto_for=NULL) {
  if ($frame===FALSE) return $frame;
  if ($crypto_for===NULL) return $frame;
  $newframe=array_merge(array('CMD'=>'ENC','SRCKEY'=>$this->pubkey),$crypto_for);
  $c=nacl_crypto_box($frame,$newframe['NONCE'],$newframe['DSTKEY'],$this->seckey);
  if ($c===FALSE) return FALSE;
  return new udpmsg4_packet (array_merge($newframe,array('DATA'=>$c)));
 }
 function create_frame ($p) {
  $frame=$this->create_frame_nocrypt($p);
  $frame=$this->encrypt_frame($frame,$this->crypto_for($p));
  return $frame;
 }
 function parse ($f) {
  if (($f===NULL)||($f===FALSE)) return $f;
  if ($f['CMD']!=='ENC') return $f;
  if ($f['DSTKEY']!==$this->pubkey) return $f;
  if (!isset($f['NONCE']))
   if (isset($f['TS'])) $f['NONCE']=$f['TS']; else return FALSE;
  $msg=nacl_crypto_box_open($f['DATA'],$f['NONCE'],$f['SRCKEY'],$this->seckey);
  if ($msg===FALSE) return FALSE;
  return $this->parse(udpmsg4_packet::parse($msg));
 }
 function parse_unframed_nocrypt (&$b) { return udpmsg4_packet::parse($b); }
 function parse_unframed (&$b) {
  $p=$this->parse_unframed_nocrypt($b);
  return $this->parse($p);
 }
 function parse_framed (&$b) {
  $unframed=udpmsg4_packet::unframe_msg($b);
  if (($unframed===NULL)||($unframed===FALSE)) return $unframed;
  return $this->parse_unframed($unframed);
 }
 function read_compat ($p) {
  if (!isset($p['CMD'])) return $p;
  if ($p['CMD']==='ENC') return $p;
  if (!isset($p['SRC'])&&isset($p['NET'])&&isset($p['USR'])) $p['SRC']='/'.$p['NET'].'/'.$p['USR'];
  if (!isset($p['DST'])&&isset($p['CHN'])) $p['DST']=$p['CHN'];
  return $p;
 }
 function write_compat ($p) {
  if (!isset($p['CMD'])) return $p;
  if ($p['CMD']==='ENC') return $p;
  if (!isset($p['NET'])) $p['NET']=$this->netname;
  if (!isset($p['USR'])) $p['USR']=$this->user;
  if (!isset($p['CHN'])&&isset($p['DST'])) $p['CHN']=$p['DST'];
  return $p;
 }
 function create_message ($to,$data) {
  $p=array('CMD'=>'MSG','SRC'=>'/'.$this->netname.'/'.$this->user,'DST'=>$to,'MSG'=>$data);
  return $p;
 }
 function send_message ($to,$data,$from=NULL) {
  $p=$this->create_message($to,$data);
  $p=$this->write_compat($p);
  $f=$this->create_frame($p);
  return $f;
 }
 function recv_message ($f) {
  $p=$this->parse($f);
  $p=$this->read_compat($p);
  return $p;
 }
}
