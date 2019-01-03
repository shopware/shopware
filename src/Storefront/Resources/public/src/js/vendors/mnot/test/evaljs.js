var runTests = require('./framework.js').runTests;

var evaljs_tests = [
  ['#js_content1', ""],
  ['#js_content2', "content2"],
];

runTests("evaljs.html", evaljs_tests, {width: 880, height: 800});
