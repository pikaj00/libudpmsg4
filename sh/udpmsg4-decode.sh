#!/bin/sh
set -a
while KEY="`sh/udpmsg4-unminiframe`"; do
 VALUE_BASE64=`sh/udpmsg4-unframe | base64 -w 0`
 export p_$(echo $KEY | sed 's/-/_/g; s/[^a-zA-Z0-9_]//g')_base64="$VALUE_BASE64"
 export p_$(echo $KEY | sed 's/-/_/g; s/[^a-zA-Z0-9_]//g')="`echo $VALUE_BASE64 | base64 -d`"
done
if [ "$#" -gt '0' ]; then
 exec "$@"
else
 set | sed '/^p_/ ! d; s/=.*$//' | { while read varname; do declare -p $varname; done; }
fi
