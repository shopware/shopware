
Tests
=====

Requirements
------------

 * nodejs
 * [phantomjs](http://phantomjs.org) (at least 1.8.* version)
 * Python

After this we can set up the project
 
```
npm install
```

Running tests locally
---------------------

```
cd test
make
```

Release Testing
---------------

Phantom only tests a specific build of WebKit; releases need to be tested
on:

 * IE 6+
 * Safari 5+
 * FireFox (current)
 * Chrome (current)
 * Opera (current)
