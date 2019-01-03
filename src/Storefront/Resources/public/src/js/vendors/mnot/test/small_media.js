var runTests = require('./framework.js').runTests;

var small_tests = [
  ['#a', "Small viewport"],
];

runTests("media.html", small_tests, {width: 480, height: 800});
