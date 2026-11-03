/**
 * @sw-package framework
 */

const path = require('path');

module.exports = {
    rules: {
        'no-src-imports': require(path.resolve(__dirname, 'no-src-imports.js')),
        'no-private-components': require(path.resolve(__dirname, 'no-private-components.js')),
    },
};
