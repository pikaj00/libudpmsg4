all: encdec genkey tai

encdec: encdec.o
	cc -o$@.new $< -lnacl
	mv $@.new $@

genkey: genkey.o
	cc -o$@.new $< -lnacl /usr/lib/randombytes.o
	mv $@.new $@

tai: tai.o seconds2taia.o
	cc -o$@.new $< seconds2taia.o -ltai
	mv $@.new $@

tai.o: tai.c seconds2taia.h
	cc -c -o$@.new $<
	mv $@.new $@

seconds2taia.o: seconds2taia.c seconds2taia.h
	cc -c -o$@.new $<
	mv $@.new $@
