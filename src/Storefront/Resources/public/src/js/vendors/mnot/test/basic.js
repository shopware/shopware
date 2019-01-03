var runTests = require('./framework.js').runTests;

var tests = [
  ["#a", "this text is included", "hinclude is ended"],
  ["#b", "this text overwrote what was just there."]
];

runTests("basic.html", tests);