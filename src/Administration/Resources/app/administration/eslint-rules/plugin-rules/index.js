/**
 * @sw-package framework
 */

const path = require('path');

module.exports = {
    rules: {
        ...require(path.resolve(__dirname, 'loading-performance')),
        'no-src-imports': require(path.resolve(__dirname, 'no-src-imports.js')),
    },
};
