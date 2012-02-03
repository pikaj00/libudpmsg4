all: encdec genkey

encdec: encdec.o
	cc -o$@.new $< -lnacl
	mv $@.new $@

genkey: genkey.o
	cc -o$@.new $< -lnacl /usr/lib/randombytes.o
	mv $@.new $@
