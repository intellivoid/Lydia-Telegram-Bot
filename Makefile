clean:
	rm -rf build

build:
	mkdir build
	ppm --compile="botsrc" --directory="build"

install:
	ppm --fix-conflict --no-prompt --install="build/net.intellivoid.lydia_chat_bot.ppm"

run:
	ppm --main="net.intellivoid.lydia_chat_bot" --version="latest"