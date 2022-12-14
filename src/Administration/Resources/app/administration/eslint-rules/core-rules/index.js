/**
 * @package admin
 */

const path = require('path');

module.exports = {
    rules: {
        // eslint-disable-next-line global-require,import/no-dynamic-require
        'require-position-identifier': require(path.resolve(__dirname, 'require-position-identifier.js')),
        // eslint-disable-next-line global-require,import/no-dynamic-require,max-len
        'require-criteria-constructor-arguments': require(path.resolve(__dirname, 'require-criteria-constructor-arguments.js')),
    },
};
