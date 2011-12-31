#!/bin/sh
. sh/lpstring.sh
encodeint16lpstring -s || { echo "ERROR: frame too large">&2; exit 255; }
