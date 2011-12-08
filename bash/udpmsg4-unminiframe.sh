#!/bin/bash -e
LENGTH="`head -c1 | hexdump -e '1/1 "%3d"' | tr -d ' '`"
head -c"$LENGTH"
