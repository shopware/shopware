/**
 * @package admin
 */

const path = require('path');

module.exports = {
    rules: {
        // eslint-disable-next-line global-require,import/no-dynamic-require
        'require-position-identifier': require(path.resolve(__dirname, 'require-position-identifier.js')),
        // eslint-disable-next-line global-require,import/no-dynamic-require
        'require-package-annotation': require(path.resolve(__dirname, 'require-package-annotation.js')),
        // eslint-disable-next-line global-require,import/no-dynamic-require
        'require-explicit-emits': require(path.resolve(__dirname, 'require-explicit-emits.js')),
    },
};
