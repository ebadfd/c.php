hello:
	as -o hello.o hello.s
link:
	ld -macosx_version_min 13.0.0 -o hello hello.o -lSystem -syslibroot `xcrun -sdk macosx --show-sdk-path` -e _start -arch arm64
clean:
	rm hello hello.o
out:
	as -o out.o out.s
blink:
	ld -macosx_version_min 13.0.0 -o out out.o -lSystem -syslibroot `xcrun -sdk macosx --show-sdk-path` -e _start -arch arm64
bclean:
	rm out out.o out.s
