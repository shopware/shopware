/**
 * @sw-package framework
 */

const path = require('path');

module.exports = {
    rules: {
        /* eslint-disable global-require,import/no-dynamic-require */
        'require-position-identifier': require(path.resolve(__dirname, 'require-position-identifier.js')),
        'require-package-annotation': require(path.resolve(__dirname, 'require-package-annotation.js')),
        'require-explicit-emits': require(path.resolve(__dirname, 'require-explicit-emits.js')),
        'move-v-if-conditions-to-blocks': require(path.resolve(__dirname, 'move-v-if-conditions-to-blocks.js')),
        'remove-empty-templates': require(path.resolve(__dirname, 'remove-empty-templates.js')),
        'move-slots-to-wrap-blocks': require(path.resolve(__dirname, 'move-slots-to-wrap-blocks.js')),
        'replace-top-level-blocks-to-extends': require(path.resolve(__dirname, 'replace-top-level-blocks-to-extends.js')),
        'enforce-async-component-registers': require(path.resolve(__dirname, 'enforce-async-component-registers.js')),
        /* eslint-enable global-require,import/no-dynamic-require */
    },
};
