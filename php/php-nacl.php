<?php

class nacl_engine {
 var $p=NULL;
 var $pipes=NULL;
 var $keygen=NULL;
 var $keygenpipes=NULL;
 function __construct () {
  $this->p=proc_open('./encdec',array(array('pipe','r'),array('pipe','w')),$this->pipes);
  if (!is_resource($this->p)) die("encdec failed to start");
  $this->keygen=proc_open('./genkey',array(array('pipe','r'),array('pipe','w')),$this->keygenpipes);
  if (!is_resource($this->keygen)) die("keygen failed to start");
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
 function enc ($data,$pubkey,$seckey,$nonce) {
  $packet=array('CMD'=>'enc','DATA'=>$data,'PUBKEY'=>$pubkey,'SECKEY'=>$seckey,'NONCE'=>$nonce);
  $frame='';
  foreach ($packet as $key => $value)
   $frame.=chr(strlen($key)).$key.chr(strlen($value)/256).chr(strlen($value)%256).$value;
  if (strlen($frame)>65536) return FALSE;
  fwrite($this->pipes[0],chr(strlen($frame)/256).chr(strlen($frame)%256).$frame);
  flush($this->pipes[0]);
  $len=fread($this->pipes[1],2);
  if (strlen($len)!=2) die("encdec read len failed");
  $len=ord($len[0])*256+ord($len[1]);
  $frame=self::fread_all($this->pipes[1],$len);
  if ($len!=strlen($frame)) die("encdec read frame failed");
  $p=array();
  while (strlen($frame)) {
   $klen=ord($frame[0]);
   $key=substr($frame,1,$klen);
   $vlen=ord($frame[$klen+1])*256+ord($frame[$klen+2]);
   $val=substr($frame,$klen+3,$vlen);
   $p[$key]=$val;
   $frame=substr($frame,$klen+$vlen+3);
  }
  if (ord($p['ret'])) return FALSE;
  return $p['DATA'];
 }
 function dec ($data,$pubkey,$seckey,$nonce) {
  $packet=array('CMD'=>'dec','DATA'=>$data,'PUBKEY'=>$pubkey,'SECKEY'=>$seckey,'NONCE'=>$nonce);
  $frame='';
  foreach ($packet as $key => $value)
   $frame.=chr(strlen($key)).$key.chr(strlen($value)/256).chr(strlen($value)%256).$value;
  if (strlen($frame)>65536) return FALSE;
  fwrite($this->pipes[0],chr(strlen($frame)/256).chr(strlen($frame)%256).$frame);
  flush($this->pipes[0]);
  $len=fread($this->pipes[1],2);
  if (strlen($len)!=2) die("encdec read len failed");
  $len=ord($len[0])*256+ord($len[1]);
  $frame=self::fread_all($this->pipes[1],$len);
  if ($len!=strlen($frame)) die("encdec read frame failed");
  $p=array();
  while (strlen($frame)) {
   $klen=ord($frame[0]);
   $key=substr($frame,1,$klen);
   $vlen=ord($frame[$klen+1])*256+ord($frame[$klen+2]);
   $val=substr($frame,$klen+3,$vlen);
   $p[$key]=$val;
   $frame=substr($frame,$klen+$vlen+3);
  }
  if (ord($p['ret'])) return FALSE;
  return $p['DATA'];
 }
 function genkey () {
  $packet=array('CMD'=>'genkey');
  $frame='';
  foreach ($packet as $key => $value)
   $frame.=chr(strlen($key)).$key.chr(strlen($value)/256).chr(strlen($value)%256).$value;
  if (strlen($frame)>65536) return FALSE;
  fwrite($this->genkeypipes[0],chr(strlen($frame)/256).chr(strlen($frame)%256).$frame);
  flush($this->genkeypipes[0]);
  $len=fread($this->genkeypipes[1],2);
  if (strlen($len)!=2) die("genkey read len failed");
  $len=ord($len[0])*256+ord($len[1]);
  $frame=self::fread_all($this->genkeypipes[1],$len);
  if ($len!=strlen($frame)) die("genkey read frame failed");
  $p=array();
  while (strlen($frame)) {
   $klen=ord($frame[0]);
   $key=substr($frame,1,$klen);
   $vlen=ord($frame[$klen+1])*256+ord($frame[$klen+2]);
   $val=substr($frame,$klen+3,$vlen);
   $p[$key]=$val;
   $frame=substr($frame,$klen+$vlen+3);
  }
  if (ord($p['ret'])) return FALSE;
  return array('pubkey'=>$p['PUBKEY'],'seckey'=>$p['SECKEY']);
 }
 static function nacl_engine () {
  static $engine=NULL;
  if ($engine===NULL) $engine = new nacl_engine;
  return $engine;
 }
 static function crypto_box ($data,$nonce,$pubkey,$seckey) {
  return self::nacl_engine()->enc($data,$pubkey,$seckey,$nonce);
 }
 static function crypto_box_open ($data,$nonce,$pubkey,$seckey) {
  return self::nacl_engine()->dec($data,$pubkey,$seckey,$nonce);
 }
 static function crypto_box_keypair () {
  return self::nacl_engine()->genkey();
 }
}

if (!function_exists('nacl_crypto_box_curve25519xsalsa20poly1305')) {
 function nacl_crypto_box_curve25519xsalsa20poly1305 ($data,$nonce,$pubkey,$seckey) { return nacl_engine::crypto_box($data,$nonce,$pubkey,$seckey); }
 function nacl_crypto_box_curve25519xsalsa20poly1305_open ($data,$nonce,$pubkey,$seckey) { return nacl_engine::crypto_box_open($data,$nonce,$pubkey,$seckey); }
}

if (isset($nacl_force_encdec)) {
 function nacl_crypto_box ($data,$nonce,$pubkey,$seckey) { return nacl_engine::crypto_box($data,$nonce,$pubkey,$seckey); }
 function nacl_crypto_box_open ($data,$nonce,$pubkey,$seckey) { return nacl_engine::crypto_box_open($data,$nonce,$pubkey,$seckey); }
} else {
 function nacl_crypto_box ($data,$nonce,$pubkey,$seckey) { return nacl_crypto_box_curve25519xsalsa20poly1305($data,$nonce,$pubkey,$seckey); }
 function nacl_crypto_box_open ($data,$nonce,$pubkey,$seckey) { return nacl_crypto_box_curve25519xsalsa20poly1305_open($data,$nonce,$pubkey,$seckey); }
}

if (!function_exists('nacl_crypto_box_curve25519xsalsa20poly1305_keypair')) {
 function nacl_crypto_box_curve25519xsalsa20poly1305_keypair () { return nacl_engine::crypto_box_keypair(); }
}

if (isset($nacl_force_genkey)) {
 function nacl_crypto_box_keypair () { return nacl_engine::crypto_box_keypair(); }
} else {
 function nacl_crypto_box_keypair () { return nacl_crypto_box_curve25519xsalsa20poly1305_keypair(); }
}

?>
