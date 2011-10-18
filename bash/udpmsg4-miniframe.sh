#!/bin/bash -e
TMPFILE1=`mktemp`
cat >> $TMPFILE1
TMPFILE2=`mktemp`
printf "\x`wc -c $TMPFILE1 | cut -d' ' -f1 | tr -dc '0-9' | printf '%02x' $(cat)`" >> $TMPFILE2
if [ "x`wc -c $TMPFILE2 | cut -d' ' -f1 | tr -dc '0-9'`" != x1 ]; then
 echo "ERROR: miniframe too large">&2
 rm $TMPFILE1 $TMPFILE2
 exit 255
fi
cat $TMPFILE2
cat $TMPFILE1
rm $TMPFILE1 $TMPFILE2
