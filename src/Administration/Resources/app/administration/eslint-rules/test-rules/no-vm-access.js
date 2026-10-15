/**
 * @sw-package framework
 */

const path = require('path');

const createSelectorRule = require(path.resolve(__dirname, '../core-rules/create-selector-rule.js'));

module.exports = createSelectorRule({
    description: 'Disallow reaching into component instances through `.vm` in tests',
    selector: "MemberExpression[computed=false][property.name='vm']",
    message: 'Test what the user sees. `.vm` reaches into component internals.',
});
