/**
 * @package admin
 */

const path = require('path');

module.exports = {
    rules: {
        // eslint-disable-next-line global-require,import/no-dynamic-require
        'require-position-identifier': require(path.resolve(__dirname, 'require-position-identifier.js')),
    },
};
