#!/bin/sh

readint8() {
    head -c 1 | hexdump -e '1/1 "%u\n"'
}

writeint8() (
    { [ "$1" -ge 0 ] && [ "$1" -le 255 ]; } || exit 1 
    printf $(printf '\%.3o' "$1")
)

readint16() {
    eval $(head -c 2 | hexdump -e '1/1 "echo $(((256*%u)" 1/1 "+%u))" "\n"')
}

writeint16() (
    { [ "$1" -ge 0 ] && [ "$1" -le 65535 ]; } || exit 1 
    printf $(printf '\%.3o\%.3o' $(($1 / 256)) $(($1 % 256)))
)

# encode read from command line, STDIN, arbitrary fd, or file
# decode read from STDIN, arbitrary fd, or file
# source flags:
# -s read from STDIN (default when no args given)
# -fd read from argitrary fd
# -f read from file (default when args given with no source flag)
# -c read from command line

decodeint8lpstring() (
    if [ "$#" -eq 0 ]; then
        # read from STDIN (default when no args given)
        STRING_LENGTH=$(readint8)
        [ -z "$STRING_LENGTH" ] && exit 2
        head -c $STRING_LENGTH
    else
        case "$1" in
            '-s')
                # -s read from STDIN
                STRING_LENGTH=$(readint8)
                [ -z "$STRING_LENGTH" ] && exit 2
                head -c $STRING_LENGTH
                ;;
            '-fd')
                # -fd read from argitrary fd
                INPUT_FD="$2"
                {
                    STRING_LENGTH=$(readint8)
                    [ -z "$STRING_LENGTH" ] && exit 2
                    head -c $STRING_LENGTH
                } <&"$INPUT_FD"
                ;;
            '-f')
                # -f read from file
                INPUT_FILE="$2"
                {
                    STRING_LENGTH=$(readint8)
                    [ -z "$STRING_LENGTH" ] && exit 2
                    head -c $STRING_LENGTH
                } <&"$INPUT_FILE"
                ;;
            *)
                # read from file (default when args given with no source flag)
                INPUT_FILE="$1"
                {
                    STRING_LENGTH=$(readint8)
                    [ -z "$STRING_LENGTH" ] && exit 2
                    head -c $STRING_LENGTH
                } <&"$INPUT_FILE"
                ;;
        esac
    fi
)

encodeint8lpstring() (
    if [ "$#" -eq 0 ]; then
        # read from STDIN (default when no args given)
        STRING=$(base64 -w 0)
        STRING_LENGTH=$(echo "$STRING" | base64 -d | wc -c)
        writeint8 $STRING_LENGTH || exit 1
        echo "$STRING" | base64 -d
    else
        case "$1" in
            '-s')
                # -s read from STDIN
                STRING=$(base64 -w 0)
                STRING_LENGTH=$(echo "$STRING" | base64 -d | wc -c)
                writeint8 $STRING_LENGTH || exit 1
                echo "$STRING" | base64 -d
                ;;
            '-fd')
                # -fd read from argitrary fd
                INPUT_FD="$2"
                STRING=$(base64 -w 0 <&"$INPUT_FD")
                STRING_LENGTH=$(echo "$STRING" | base64 -d | wc -c)
                writeint8 $STRING_LENGTH || exit 1
                echo "$STRING" | base64 -d
                ;;
            '-f')
                # -f read from file
                INPUT_FILE="$2"
                FILE_LENGTH=$(ls -l "$INPUT_FILE" | awk '{ print $5; }')
                writeint8 $FILE_LENGTH || exit 1
                cat "$INPUT_FILE"
                ;;
            '-c')
                # -c read from command line
                shift
                STRING="$*"
                STRING_LENGTH=$(echo -n "$STRING" | wc -c)
                writeint8 $STRING_LENGTH || exit 1
                echo -n "$STRING"
                ;;
            *)
                # read from file (default when args given with no source flag)
                INPUT_FILE="$1"
                FILE_LENGTH=$(ls -l "$INPUT_FILE" | awk '{ print $5; }')
                writeint8 $FILE_LENGTH || exit 1
                cat "$INPUT_FILE"
                ;;
        esac
    fi
)

decodeint16lpstring() (
    if [ "$#" -eq 0 ]; then
        # read from STDIN (default when no args given)
        STRING_LENGTH=$(readint16)
        [ -z "$STRING_LENGTH" ] && exit 2
        head -c $STRING_LENGTH
    else
        case "$1" in
            '-s')
                # -s read from STDIN
                STRING_LENGTH=$(readint16)
                [ -z "$STRING_LENGTH" ] && exit 2
                head -c $STRING_LENGTH
                ;;
            '-fd')
                # -fd read from argitrary fd
                INPUT_FD="$2"
                {
                    STRING_LENGTH=$(readint16)
                    [ -z "$STRING_LENGTH" ] && exit 2
                    head -c $STRING_LENGTH
                } <&"$INPUT_FD"
                ;;
            '-f')
                # -f read from file
                INPUT_FILE="$2"
                {
                    STRING_LENGTH=$(readint16)
                    [ -z "$STRING_LENGTH" ] && exit 2
                    head -c $STRING_LENGTH
                } <&"$INPUT_FILE"
                ;;
            *)
                # read from file (default when args given with no source flag)
                INPUT_FILE="$1"
                {
                    STRING_LENGTH=$(readint16)
                    [ -z "$STRING_LENGTH" ] && exit 2
                    head -c $STRING_LENGTH
                } <&"$INPUT_FILE"
                ;;
        esac
    fi
)

encodeint16lpstring() (
    if [ "$#" -eq 0 ]; then
        # read from STDIN (default when no args given)
        STRING=$(base64 -w 0)
        STRING_LENGTH=$(echo "$STRING" | base64 -d | wc -c)
        writeint16 $STRING_LENGTH || exit 1
        echo "$STRING" | base64 -d
    else
        case "$1" in
            '-s')
                # -s read from STDIN
                STRING=$(base64 -w 0)
                STRING_LENGTH=$(echo "$STRING" | base64 -d | wc -c)
                writeint16 $STRING_LENGTH || exit 1
                echo "$STRING" | base64 -d
                ;;
            '-fd')
                # -fd read from argitrary fd
                INPUT_FD="$2"
                STRING=$(base64 -w 0 <&"$INPUT_FD")
                STRING_LENGTH=$(echo "$STRING" | base64 -d | wc -c)
                writeint16 $STRING_LENGTH || exit 1
                echo "$STRING" | base64 -d
                ;;
            '-f')
                # -f read from file
                INPUT_FILE="$2"
                FILE_LENGTH=$(ls -l "$INPUT_FILE" | awk '{ print $5; }')
                writeint16 $FILE_LENGTH || exit 1
                cat "$INPUT_FILE"
                ;;
            '-c')
                # -c read from command line
                shift
                STRING="$*"
                STRING_LENGTH=$(echo -n "$STRING" | wc -c)
                writeint16 $STRING_LENGTH || exit 1
                echo -n "$STRING"
                ;;
            *)
                # read from file (default when args given with no source flag)
                INPUT_FILE="$1"
                FILE_LENGTH=$(ls -l "$INPUT_FILE" | awk '{ print $5; }')
                writeint16 $FILE_LENGTH || exit 1
                cat "$INPUT_FILE"
                ;;
        esac
    fi
)
