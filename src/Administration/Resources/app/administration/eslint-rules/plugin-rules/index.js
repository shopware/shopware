/**
 * @package admin
 */

const path = require('path');

module.exports = {
    rules: {
        // eslint-disable-next-line global-require
        'no-src-imports': require(path.resolve(__dirname, 'no-src-imports.js')),
    },
};
