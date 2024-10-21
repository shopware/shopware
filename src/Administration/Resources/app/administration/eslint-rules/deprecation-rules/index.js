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
        // eslint-disable-next-line global-require,import/no-dynamic-require
        'no-deprecated-components': require(path.resolve(__dirname, 'no-deprecated-components.js')),
        // eslint-disable-next-line global-require,import/no-dynamic-require
        'no-deprecated-component-usage': require(path.resolve(__dirname, 'no-deprecated-component-usage.js')),
        'no-compat-conditions': require(path.resolve(__dirname, 'no-compat-conditions.js')),
        'no-empty-listeners': require(path.resolve(__dirname, 'no-empty-listeners.js')),
        'no-vue-options-api': require(path.resolve(__dirname, 'no-vue-options-api.js')),
    },
};
