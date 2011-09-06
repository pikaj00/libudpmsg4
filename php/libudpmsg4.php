<?php

if (defined('libudpmsg4_loaded')) return;
define('libudpmsg4_loaded', TRUE);

include 'php-nacl.php';

class udpmsg4_packet implements ArrayAccess {
 var $kvps;
 static function miniframe_msg ($msg) {
  $len = strlen($msg);
  if ($len > 255) return FALSE;
  return chr($len).$msg;
 }
 static function frame_msg ($msg) {
  $len = strlen($msg);
  if ($len > 1024) return FALSE;
  return chr(floor($len / 256)).chr($len % 256).$msg;
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
 static function parse_framed (&$data) {
  $unframed = udpmsg4_packet::unframe_msg($data);
  if (($unframed===NULL)||($unframed===FALSE)) return $unframed;
  return udpmsg4_packet::parse($unframed);
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
 function __toString () { return $this->unframed(); }
 static function from_compat ($p) {
  if (!isset($p['CMD'])) return $p;
  if ($p['CMD']==='ENC') return $p;
  if (!isset($p['SRC'])&&isset($p['NET'])&&isset($p['USR'])) $p['SRC']='/'.$p['NET'].'/'.$p['USR'];
  if (!isset($p['DST'])&&isset($p['CHN'])) $p['DST']=$p['CHN'];
  return $p;
 }
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
   '3f224a6f7924398f454d6bdd52ed46ad632c2f11413ef255c1811993522a632f'=>'/oNet',
   '94aa84f033ddb4a019a4a3a583e7644f8dd8cd8dae67fb67c14db9e87f0be75f'=>'/atomic',
   '12ae24c362880cd6c0e2f186311ce95581c09164c99ae990e31227b0cb86ae19'=>'/A2',
   'cbc616fdd673e02637b86324401d06a0c597994cb96e517865485d6893585d5f'=>'/CA',
   '7938ecd1cd3ac04990bc250c2d7a2fe77f99f08c26da532de34548b5f3a82430'=>'/test',
   'ce1c4cc4ee1cedf0bcd00cff36d08dfbb9bad1f4df69c3225eb33e9301b1b144'=>'/JCS',
   '0fd27cd108d70a75c4e412d16d0fb33cdce0e254fc1fdfd70cc3f528b52ea72a'=>'/relayhell',
   '7b7e0494b0ff046f3224b912cd1dd81b4bc2170917da6c23dcc8210b033ac101'=>'/sI4',
   '74f9a9fa1e5b7fe22c9475e2e3910779701275253bf4759fb37064749c51105b'=>'/NNNC',
  );
  $this->set_user(isset($config['user'])?$config['user']:NULL);
 }
 function set_user ($user) {
  $this->user=NULL; if (isset($user)) $this->user=$user;
 }
 function create_frame_nocrypt ($p) {
  if (!isset($p['DUMMY'])) $p['DUMMY'] = rand(0, 999999);
  if (!isset($p['NET'])) $p['NET'] = $this->netname;
  return new udpmsg4_packet ($p);
 }
 function crypto_for ($p) {
  if (!isset($p['DST'])) return NULL;
  foreach ($this->keyring as $pubkey => $prefix)
   if (($p['DST']===$prefix) || (substr($p['DST'],0,strlen($prefix)+1)==="$prefix/"))
    return array('DSTKEY'=>self::hex2key($pubkey),'NONCE'=>'123456781234567812345678');
  if ($p['DST'][0]==='/') return FALSE;
  return NULL;
 }
 function encrypt_frame ($frame,$crypto_for=NULL) {
  if ($frame===FALSE) return $frame;
  if ($crypto_for===NULL) return $frame;
  if ($crypto_for===FALSE) return FALSE;
  $newframe=array_merge(array('CMD'=>'ENC','SRCKEY'=>$this->pubkey),$crypto_for);
  $c=nacl_crypto_box($frame,$newframe['NONCE'],$newframe['DSTKEY'],$this->seckey);
  if ($c===FALSE) return FALSE;
  return new udpmsg4_packet (array_merge($newframe,array('DATA'=>$c)));
 }
 function create_frame ($p) {
  $frame=$this->create_frame_nocrypt($p);
  return $this->encrypt_frame($frame,$this->crypto_for($p));
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
 function extract_USR ($path) {
  $c='/'.$this->netname.'/';
  if (substr($path,0,strlen($c))===$c) return substr($path,strlen($c));
  return preg_replace(',^.*/,','',$path);
 }
 function write_compat ($p) {
  if (!isset($p['CMD'])) return $p;
  if ($p['CMD']==='ENC') return $p;
  if (!isset($p['NET'])) $p['NET']=$this->netname;
  if (!isset($p['USR']))
   if ($this->user!==NULL) $p['USR']=$this->user;
   else $p['USR']=$this->extract_USR($p['SRC']);
  if (!isset($p['USR'])) return FALSE;
  if (!isset($p['CHN'])&&isset($p['DST'])) $p['CHN']=$p['DST'];
  return $p;
 }
 function fill_from ($p,$from=NULL) {
  if ($from!==NULL)
   if ($from[0]==='/') $p['SRC']=$from;
   else $p['SRC']='/'.$this->netname.'/'.$from;
  else if (isset($this->user)) $p['SRC']='/'.$this->netname.'/'.$this->user;
  else return FALSE;
  return $p;
 }
 function create_message ($to,$data,$from=NULL) {
  $p=array('CMD'=>'MSG','SRC'=>NULL,'DST'=>$to,'MSG'=>$data);
  return $this->fill_from($p,$from);
 }
 function create_join ($to,$from=NULL) {
  $p=array('CMD'=>'JOIN','SRC'=>NULL,'DST'=>$to);
  return $this->fill_from($p,$from);
 }
 function create_part ($to,$reason=NULL,$from=NULL) {
  $p=array('CMD'=>'PART','SRC'=>NULL,'DST'=>$to,'REASON'=>$reason);
  return $this->fill_from($p,$from);
 }
 function create_quit ($reason=NULL,$from=NULL) {
  $p=array('CMD'=>'QUIT','SRC'=>NULL,'REASON'=>$reason);
  return $this->fill_from($p,$from);
 }
 function send_message ($to,$data,$from=NULL) {
  $p=$this->create_message($to,$data,$from);
  $p=$this->write_compat($p);
  return $this->create_frame($p);
 }
 function send_join ($to,$from=NULL) {
  $p=$this->create_join($to,$from);
  $p=$this->write_compat($p);
  return $this->create_frame($p);
 }
 function send_part ($to,$reason=NULL,$from=NULL) {
  $p=$this->create_part($to,$reason,$from);
  $p=$this->write_compat($p);
  return $this->create_frame($p);
 }
 function send_quit ($reason=NULL,$from=NULL) {
  $p=$this->create_quit($reason,$from);
  $p=$this->write_compat($p);
  return $this->create_frame($p);
 }
 function recv_packet ($f) {
  $p=$this->parse($f);
  return $this->read_compat($p);
 }
}

// /test seckey 1019f04471cbfe3e8035ae3c8af09a22bd35f321f18adce700b30e1873a91e22
