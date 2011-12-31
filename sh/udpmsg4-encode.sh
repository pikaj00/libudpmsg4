#!/bin/sh
. sh/lpstring.sh
while [ -n "$1" ]; do
    KEY="${1%%=*}"
    VALUE="${1#*=}"
    echo -n "$KEY" | { encodeint8lpstring -s || { echo "ERROR: frame too large">&2; exit 255; };}
    echo -n "$VALUE" | { encodeint16lpstring -s || { echo "ERROR: frame too large">&2; exit 255; }; }
    shift;
done
