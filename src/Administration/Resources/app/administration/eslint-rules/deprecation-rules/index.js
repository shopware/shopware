/**
 * @package admin
 */

const path = require('path');

module.exports = {
    rules: {
        // eslint-disable-next-line global-require,import/no-dynamic-require
        'private-feature-declarations': require(path.resolve(__dirname, 'private-feature-declarations.js')),
        // eslint-disable-next-line global-require,import/no-dynamic-require
        'no-twigjs-blocks': require(path.resolve(__dirname, 'no-twigjs-blocks.js')),
    },
};
