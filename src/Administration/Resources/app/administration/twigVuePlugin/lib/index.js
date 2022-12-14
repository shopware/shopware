/**
 * @package admin
 *
 * @fileoverview A twig and vue plugin
 * @author twig-vue
 */
"use strict";

//------------------------------------------------------------------------------
// Requirements
//------------------------------------------------------------------------------

var requireIndex = require("requireindex");
var twigVueProcessor = require('./processors/twig-vue-processor');

//------------------------------------------------------------------------------
// Plugin Definition
//------------------------------------------------------------------------------




// import processors
module.exports.processors = twigVueProcessor.processors;

