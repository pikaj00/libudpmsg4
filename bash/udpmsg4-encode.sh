#!/bin/bash -e
while [ -n "$1" ]; do
 KEY="${1%%=*}"
 VALUE="${1#*=}"
 echo -n "$KEY" | ./bash/udpmsg4-miniframe
 echo -n "$VALUE" | ./bash/udpmsg4-frame
 shift;
done
