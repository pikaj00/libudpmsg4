#!/bin/bash -e
LENGTH="`head -c2 | hexdump -v -e '1/1 "%3d"' | (read big little; echo $(($big*256+$little)))`"
head -c"$LENGTH"
