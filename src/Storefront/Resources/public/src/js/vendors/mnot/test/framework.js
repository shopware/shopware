var args = require('system').args;
var webpage = require('webpage');



function runTests(page_loc, tests, viewport) {
  var port = args[1];
  var errors = [];
  var console_msg = [];
  var page = webpage.create();
  if(viewport){
      page.viewportSize = viewport;
  }

  function checkContent(selector, expected) {
      var a = page.evaluate(function(selector) {
        return document.querySelector(selector).textContent;
      }, selector);
      if (a.trim() != expected) {
        errors.push(selector + ': "' + a + "\" is not \"" + expected + '"');
      }
  }

  phantom.onError = function(msg, trace) {
      errors.push('PHANTOM ERROR: ' + msg);
  };

  page.onConsoleMessage = function (msg) {
      console_msg.push(msg);
      console.log('BROWSER CONSOLE: ' + msg);
  };

  setTimeout(function(){
    page.open('http://localhost:' + port + '/' + page_loc, function (status) {
      if (status === "success") {
        console.log("testing " + port + "...");
      } else {
        console.error("Open problem; bailing\n");
        phantom.exit(2);
      }

      var i = 0;
      top:
      while (i < tests.length) {
        checkContent(tests[i][0], tests[i][1]);

        if (typeof tests[i][2] == 'string') {
          var j = 0;
          while (j < console_msg.length) {
            if (tests[i][2] == console_msg[j]) {
              i++;
              continue top;
            }
            j++;
          }
          errors.push("Event 'hinclude' was not triggered\n");
        }
        i++;
      }

      if (errors.length > 0) {
        console.log(errors.join("\n"));
        page.render("error.png");
        phantom.exit(1);
      } else {
        console.log("Ok.");
        phantom.exit(0);
      }
    });
  }, 1000);
}

exports.runTests = runTests;
