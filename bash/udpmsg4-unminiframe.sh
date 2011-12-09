#!/bin/bash -e
LENGTH="`head -c1 | hexdump -e '1/1 "%3d"' | tr -d ' '`"
if [ "x$LENGTH" = 'x' ]; then exit 111; fi
head -c"$LENGTH"
