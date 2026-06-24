/**
 * @sw-package framework
 */

const path = require('path');

module.exports = {
    rules: {
        'private-feature-declarations': require(path.resolve(__dirname, 'private-feature-declarations.js')),
        'no-deprecated-components': require(path.resolve(__dirname, 'no-deprecated-components.js')),
        'no-deprecated-component-usage': require(path.resolve(__dirname, 'no-deprecated-component-usage.js')),
        'no-deprecated-template-events': require(path.resolve(__dirname, 'no-deprecated-template-events.js')),
        'no-deprecated-template-blocks': require(path.resolve(__dirname, 'no-deprecated-template-blocks.js')),
        'no-deprecated-snippet-keys': require(path.resolve(__dirname, 'no-deprecated-snippet-keys.js')),
        'no-deprecated-js-api': require(path.resolve(__dirname, 'no-deprecated-js-api.js')),
        'no-compat-conditions': require(path.resolve(__dirname, 'no-compat-conditions.js')),
        'no-empty-listeners': require(path.resolve(__dirname, 'no-empty-listeners.js')),
        'no-vue-options-api': require(path.resolve(__dirname, 'no-vue-options-api.js')),
    },
};
