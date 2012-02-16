#include <arpa/inet.h>
#include <string.h>
#include <taia.h>

void seconds2taia (seconds,t) uint32_t seconds; struct taia *t; {
 char buf[TAIA_PACK]={0};
 seconds=htonl(seconds);
 memcpy(buf+4,&seconds,4);
 taia_unpack(buf,t);
}
