#!/bin/bash -e
TMPFILE1=`mktemp`
cat >> $TMPFILE1
TMPFILE2=`mktemp`
printf "\x`wc -c $TMPFILE1 | cut -d' ' -f1 | tr -dc '0-9' | echo -n $(($(cat)/256)) | printf '%02x' $(cat)`\x`wc -c $TMPFILE1 | cut -d' ' -f1 | tr -dc '0-9' | echo $(($(cat)%256)) | printf '%02x' $(cat)`" >> $TMPFILE2
if [ "x`wc -c $TMPFILE2 | cut -d' ' -f1 | tr -dc '0-9'`" != x2 ]; then
 echo "ERROR: frame too large">&2
 rm $TMPFILE1 $TMPFILE2
 exit 255
fi
cat $TMPFILE2
cat $TMPFILE1
rm $TMPFILE1 $TMPFILE2
