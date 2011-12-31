#!/bin/sh
. sh/lpstring.sh
encodeint8lpstring -s || { echo "ERROR: miniframe too large">&2; exit 255; }
