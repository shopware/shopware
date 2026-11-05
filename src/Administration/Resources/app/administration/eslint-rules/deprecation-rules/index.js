/**
 * @sw-package framework
 */

module.exports = {
    rules: {
        'private-feature-declarations': require('./private-feature-declarations'),
        'no-deprecated-components': require('./no-deprecated-components'),
        'no-deprecated-component-usage': require('./no-deprecated-component-usage'),
        'no-deprecated-template-events': require('./no-deprecated-template-events'),
        'no-deprecated-template-blocks': require('./no-deprecated-template-blocks'),
        'no-deprecated-snippet-keys': require('./no-deprecated-snippet-keys'),
        'no-deprecated-js-api': require('./no-deprecated-js-api'),
        'no-deprecated-component-option-usage': require('./no-deprecated-component-option-usage'),
        'require-deprecation-codemod': require('./require-deprecation-codemod'),
        'no-sw-tabs-usage': require('./no-sw-tabs-usage'),
        'no-sw-empty-state-usage': require('./no-sw-empty-state-usage'),
        'no-compat-conditions': require('./no-compat-conditions'),
        'no-empty-listeners': require('./no-empty-listeners'),
        'no-vue-options-api': require('./no-vue-options-api'),
        'no-import-meta-glob-extglob': require('./no-import-meta-glob-extglob'),
    },
};
