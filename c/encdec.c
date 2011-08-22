#include <nacl/crypto_box.h>

#define CMD_ENC 0
#define CMD_DEC 1
struct req {
 int cmd;
 char* pubkey;
 char* seckey;
 char* nonce;
 char* data;
 int len;
};

struct res {
 int ret;
 char* data;
 int len;
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
int read_req (struct req* req, int fd) {
 static char buffer[65536*2]={0}; /* contains read after buffer (thx UFO) */
 int len, tlen, wlen, klen, clen;
 char* key;
 if ((len=read(fd,buffer,2))!=2) return -1;
 wlen=frame_len(buffer,1);
 for (tlen=0; tlen<wlen && len>0; len=read(fd,buffer,wlen-tlen),tlen+=len);
 if (len<=0) return -1;
 for (tlen=len=0; len<wlen; tlen^=1) {
  clen=frame_len(buffer+len,tlen);
  if (tlen) {
   if key_is("CMD")
    if val_is("enc") req->cmd=CMD_ENC;
    else if val_is("dec") req->cmd=CMD_DEC;
    else return -1;
   else if key_is("PUBKEY") req->pubkey=val;
   else if key_is("SECKEY") req->seckey=val;
   else if key_is("NONCE") req->nonce=val;
   else if key_is("DATA") { req->data=val; req->len=vlen; }
  } else {
   key=buffer+len+1; klen=clen;
  }
  len+=clen+1+tlen;
 }
 return 0;
}

int do_req (struct req req, struct res* res) {
 static char buffer1[65536*2]={0}; /* contains read after buffer */
 static char buffer2[65536*2]; /* contains potential buffer overflow */
 switch (req.cmd) {
  case CMD_ENC:
   memcpy(buffer1+crypto_box_ZEROBYTES,req.data,req.len);
   bzero(buffer1,crypto_box_ZEROBYTES);
   if (crypto_box(buffer2,buffer1,req.len+crypto_box_ZEROBYTES,req.nonce,req.pubkey,req.seckey)) res->ret=1;
   else {
    res->ret=0;
    res->data=buffer2+crypto_box_BOXZEROBYTES;
    res->len=req.len+crypto_box_ZEROBYTES-crypto_box_BOXZEROBYTES;
   }
   break;
  case CMD_DEC:
   memcpy(buffer1+crypto_box_BOXZEROBYTES,req.data,req.len);
   bzero(buffer1,crypto_box_BOXZEROBYTES);
   if (crypto_box_open(buffer2,buffer1,req.len+crypto_box_BOXZEROBYTES,req.nonce,req.pubkey,req.seckey)) res->ret=1;
   else {
    res->ret=0;
    res->data=buffer2+crypto_box_ZEROBYTES;
    res->len=req.len+crypto_box_BOXZEROBYTES-crypto_box_ZEROBYTES;
   }
   return 0;
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
int write_res (struct res res, int fd) {
 if ((1+3+2+3+1+3+2+1+(res.ret?0:1+4+2+res.len)>65536)) return -1;
 try_write(make_frame_len(1+3+2+3+1+3+2+1+(res.ret?0:1+4+2+res.len),1),2);
 try_write_kvp("CMD",3,"res",3);
 try_write_kvp("ret",3,&res.ret,1);
 if (!res.ret) try_write_kvp("DATA",4,res.data,res.len);
 return 0;
}

int main () {
 struct req req;
 struct res res;
 while (!read_req(&req,0)) if (do_req(req,&res)||write_res(res,1)) return -1;
 return 0;
}
