var runTests = require('./framework.js').runTests;

var large_tests = [
  ['#a', "Large viewport"],
];

runTests("media.html", large_tests, {width: 880, height: 800});
