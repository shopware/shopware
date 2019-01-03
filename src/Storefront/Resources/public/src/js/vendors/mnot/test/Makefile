test: basic none small_media large_media evaljs lint

basic: server
	phantomjs basic.js $(test_port)

none: server
	phantomjs none.js $(test_port)

small_media: server
	phantomjs small_media.js $(test_port)

large_media: server
	phantomjs large_media.js $(test_port)

evaljs: server
	phantomjs evaljs.js $(test_port)	

lint:
	node ../node_modules/jslint/bin/jslint.js ../hinclude.js

server:
	npm run server &
