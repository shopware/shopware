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
        // eslint-disable-next-line global-require,import/no-dynamic-require
        'move-v-if-conditions-to-blocks': require(path.resolve(__dirname, 'move-v-if-conditions-to-blocks.js')),
        // eslint-disable-next-line global-require,import/no-dynamic-require
        'remove-empty-templates': require(path.resolve(__dirname, 'remove-empty-templates.js')),
        // eslint-disable-next-line global-require,import/no-dynamic-require
        'move-slots-to-wrap-blocks': require(path.resolve(__dirname, 'move-slots-to-wrap-blocks.js')),
        // eslint-disable-next-line global-require,import/no-dynamic-require
        'replace-top-level-blocks-to-extends': require(path.resolve(__dirname, 'replace-top-level-blocks-to-extends.js')),
    },
};
