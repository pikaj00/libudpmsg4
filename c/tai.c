#include <arpa/inet.h>
#include <taia.h>
#include "seconds2taia.h"

#define CMD_taia_now 0
#define CMD_taia_less 1
#define CMD_taia_add 2
#define CMD_taia_sub 3
#define CMD_taia_half 4
#define CMD_seconds2taia 5
#define CMD_isoldtaia 6
struct req {
 int cmd;
 struct taia t;
 struct taia a;
 struct taia b;
 uint32_t seconds;
};

struct res {
 int cmd;
 int ret;
 struct taia t;
};

int frame_len (unsigned char* buffer, int realframe) {
 return realframe?*buffer*256+buffer[1]:*buffer;
}

char* make_frame_len (int len, int realframe) {
 static char buffer[2];
 buffer[1]=(char)len;
 if (realframe) buffer[0]=(char)(len/256);
 return buffer+(realframe^1);
}

#define val (buffer+len+1+tlen)
#define vlen clen
#define key_is(k) (klen==strlen(k)&&!memcmp(key,(k),strlen(k)))
#define val_is(v) (vlen==strlen(v)&&!memcmp(val,(v),strlen(v)))
#define val2taia(t) do { if (vlen!=TAIA_PACK) return -1; taia_unpack(val,&(t)); } while (0)
#define val2uint32(t) do { if (vlen!=4) return -1; (t)=ntohl(*(int*)val); } while (0)
int read_req (struct req* req, int fd) {
 static char buffer[65536*2]={0}; /* contains read after buffer (thx UFO) */
 int len, tlen, wlen, klen, clen;
 char* key;
 if ((len=read(fd,buffer,2))!=2) return -1;
 wlen=frame_len(buffer,1);
 for (tlen=0; tlen<wlen && len>0; len=read(fd,buffer+tlen,wlen-tlen),tlen+=len);
 if (len<=0) return -1;
 for (tlen=len=0; len<wlen; tlen^=1) {
  clen=frame_len(buffer+len,tlen);
  if (tlen) {
   if key_is("CMD")
    if val_is("taia_now") req->cmd=CMD_taia_now;
    else if val_is("taia_less") req->cmd=CMD_taia_less;
    else if val_is("taia_add") req->cmd=CMD_taia_add;
    else if val_is("taia_sub") req->cmd=CMD_taia_sub;
    else if val_is("taia_half") req->cmd=CMD_taia_half;
    else if val_is("seconds2taia") req->cmd=CMD_seconds2taia;
    else if val_is("isoldtaia") req->cmd=CMD_isoldtaia;
    else return -1;
   else if key_is("t") val2taia(req->t);
   else if key_is("a") val2taia(req->a);
   else if key_is("b") val2taia(req->b);
   else if key_is("seconds") val2uint32(req->seconds);
//   else if key_is("SECKEY") req->seckey=val;
//   else if key_is("NONCE") req->nonce=val;
//   else if key_is("DATA") { req->data=val; req->len=vlen; }
  } else {
   key=buffer+len+1; klen=clen;
  }
  len+=clen+1+tlen;
 }
 return 0;
}

int do_req (const struct req* req, struct res* res) {
 static char buffer1[65536*2]={0}; /* contains read after buffer */
 static char buffer2[65536*2]; /* contains potential buffer overflow */
 struct taia now;
 res->cmd=req->cmd;
 switch (req->cmd) {
  case CMD_taia_now:
   taia_now(&res->t);
   res->ret=0;
   break;
  case CMD_taia_less:
   res->ret=taia_less(&req->a,&req->b);
   break;
  case CMD_taia_add:
   taia_add(&res->t,&req->a,&req->b);
   res->ret=0;
   break;
  case CMD_taia_sub:
   taia_sub(&res->t,&req->a,&req->b);
   res->ret=0;
   break;
  case CMD_taia_half:
   taia_half(&res->t,&req->a);
   res->ret=0;
   break;
  case CMD_seconds2taia:
   seconds2taia(req->seconds,&res->t);
   res->ret=0;
   break;
  case CMD_isoldtaia:
   seconds2taia(req->seconds,&res->t);
   taia_now(&now);
   taia_add(&res->t,&req->a,&res->t);
   res->ret=taia_less(&res->t,&now);
   break;
  default:
   return -1;
 }
 return 0;
}

int try_write (int fd, char* buffer, int length) {
 int len, tlen;
 for (tlen=0; tlen<length; tlen+=len)
  if ((len=write(fd,buffer+tlen,length-tlen))==-1) return tlen;
 return tlen;
}
#define try_write(b,l) do { if (try_write(fd,b,l)!=l) return -1; } while (0)
#define try_write_key(k,kl) do { try_write(make_frame_len(kl,0),1); try_write(k,kl); } while (0)
#define try_write_val(v,vl) do { try_write(make_frame_len(vl,1),2); try_write(v,vl); } while (0)
#define try_write_kvp(k,kl,v,vl) do { try_write_key(k,kl); try_write_val(v,vl); } while (0)
#define try_write_kvp_taia(k,kl,v) do { taia_pack(buf,(v)); try_write_kvp((k),(kl),buf,TAIA_PACK); } while (0)
int write_res (const struct res* res, int fd) {
 char buf[TAIA_PACK];
 switch (res->cmd) {
  case CMD_taia_now:
   if (1+3+2+3 + 1+3+2+1 + 1+1+2+TAIA_PACK>65536) return -1;
   try_write(make_frame_len(1+3+2+3+1+3+2+1+1+1+2+TAIA_PACK,1),2);
   try_write_kvp("CMD",3,"res",3);
   try_write_kvp("ret",3,&res->ret,1);
   try_write_kvp_taia("t",1,&res->t);
   break;
  case CMD_taia_less:
   if (1+3+2+3 + 1+3+2+1>65536) return -1;
   try_write(make_frame_len(1+3+2+3+1+3+2+1,1),2);
   try_write_kvp("CMD",3,"res",3);
   try_write_kvp("ret",3,&res->ret,1);
   break;
  case CMD_taia_add:
  case CMD_taia_sub:
  case CMD_taia_half:
   if (1+3+2+3 + 1+3+2+1 + 1+1+2+TAIA_PACK>65536) return -1;
   try_write(make_frame_len(1+3+2+3+1+3+2+1+1+1+2+TAIA_PACK,1),2);
   try_write_kvp("CMD",3,"res",3);
   try_write_kvp("ret",3,&res->ret,1);
   try_write_kvp_taia("t",1,&res->t);
   break;
  case CMD_seconds2taia:
   if (1+3+2+3 + 1+3+2+1 + 1+1+2+TAIA_PACK>65536) return -1;
   try_write(make_frame_len(1+3+2+3+1+3+2+1+1+1+2+TAIA_PACK,1),2);
   try_write_kvp("CMD",3,"res",3);
   try_write_kvp("ret",3,&res->ret,1);
   try_write_kvp_taia("t",1,&res->t);
   break;
  case CMD_isoldtaia:
   if (1+3+2+3 + 1+3+2+1>65536) return -1;
   try_write(make_frame_len(1+3+2+3+1+3+2+1,1),2);
   try_write_kvp("CMD",3,"res",3);
   try_write_kvp("ret",3,&res->ret,1);
   break;
  default:
   return -1;
 }
// if ((1+3+2+3+1+3+2+1+(res.ret?0:1+4+2+res.len)>65536)) return -1;
// try_write(make_frame_len(1+3+2+3+1+3+2+1+(res.ret?0:1+4+2+res.len),1),2);
// try_write_kvp("CMD",3,"res",3);
// try_write_kvp("ret",3,&res.ret,1);
// if (!res.ret) try_write_kvp("DATA",4,res.data,res.len);
 return 0;
}

int main () {
 struct req req;
 struct res res;
 while (!read_req(&req,0)) if (do_req(&req,&res)||write_res(&res,1)) return -1;
 return 0;
}
