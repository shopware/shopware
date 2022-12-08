/**
 * @package admin
 */

const path = require('path');

module.exports = {
    rules: {
        // eslint-disable-next-line global-require,import/no-dynamic-require
        'await-async-functions': require(path.resolve(__dirname, 'await-async-functions.js')),
    },
};
