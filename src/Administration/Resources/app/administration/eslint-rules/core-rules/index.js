/**
 * @sw-package framework
 */

const path = require('path');

module.exports = {
    rules: {
        'require-position-identifier': require(path.resolve(__dirname, 'require-position-identifier.js')),
        'require-package-annotation': require(path.resolve(__dirname, 'require-package-annotation.js')),
        'require-explicit-emits': require(path.resolve(__dirname, 'require-explicit-emits.js')),
        'move-v-if-conditions-to-blocks': require(path.resolve(__dirname, 'move-v-if-conditions-to-blocks.js')),
        'remove-empty-templates': require(path.resolve(__dirname, 'remove-empty-templates.js')),
        'move-slots-to-wrap-blocks': require(path.resolve(__dirname, 'move-slots-to-wrap-blocks.js')),
        'replace-top-level-blocks-to-extends': require(path.resolve(__dirname, 'replace-top-level-blocks-to-extends.js')),
        'enforce-async-component-registers': require(path.resolve(__dirname, 'enforce-async-component-registers.js')),
        'no-tc-translation': require(path.resolve(__dirname, 'no-tc-translation.js')),
        'require-global-default-use': require(path.resolve(__dirname, 'require-global-default-use.js')),
        'valid-shopware-setup': require(path.resolve(__dirname, 'valid-shopware-setup.js')),
        'native-setup-filename': require(path.resolve(__dirname, 'native-setup-filename.js')),
    },
};
