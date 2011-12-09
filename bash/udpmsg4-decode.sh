#!/bin/bash -e
set -a
declare -Ax p
while KEY="`bash/udpmsg4-unminiframe`"; do
 VALUE="`bash/udpmsg4-unframe`"
 p["$KEY"]="$VALUE"
done
if ((${#1})); then
 for command in "$@"; do
  exec <<<$command
  source /dev/stdin
 done
else
 declare -p p
fi
