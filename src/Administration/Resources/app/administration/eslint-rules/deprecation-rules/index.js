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
        // The 6.8 codemods of `administration:upgrade-extensions`, each the registry rules for its migration ids.
        // The runner keys its config by rule name, so every codemod is registered under a name of its own.
        'upgrade-mt-components-6-8': require('./registry-codemod'),
        'upgrade-mt-tabs': require('./registry-codemod'),
        'upgrade-sw-text-editor': require('./registry-codemod'),
        'upgrade-removed-form-components': require('./registry-codemod'),
        'upgrade-order-state-select-props': require('./registry-codemod'),
        'upgrade-sw-popover': require('./registry-codemod'),
        'upgrade-twig-block-renames': require('./registry-codemod'),
        'upgrade-twig-block-removals': require('./registry-codemod'),
    },
};
