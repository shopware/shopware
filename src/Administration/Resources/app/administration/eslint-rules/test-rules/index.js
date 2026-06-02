/**
 * @sw-package framework
 */

const path = require('path');

module.exports = {
    rules: {
        'await-async-functions': require(path.resolve(__dirname, 'await-async-functions.js')),
        'test-file-max-lines-warning': require(path.resolve(__dirname, 'test-file-max-lines/warning.js')),
        'test-file-max-lines-error': require(path.resolve(__dirname, 'test-file-max-lines/error.js')),
    },
};
