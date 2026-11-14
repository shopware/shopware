/**
 * @sw-package framework
 */

module.exports = {
    rules: {
        'private-feature-declarations': require('./private-feature-declarations'),
        'no-deprecated-components': require('./no-deprecated-components'),
        'no-deprecated-component-usage': require('./no-deprecated-component-usage'),
        'no-sw-tabs-usage': require('./no-sw-tabs-usage'),
        'no-sw-empty-state-usage': require('./no-sw-empty-state-usage'),
        'no-compat-conditions': require('./no-compat-conditions'),
        'no-empty-listeners': require('./no-empty-listeners'),
        'no-vue-options-api': require('./no-vue-options-api'),
        'no-import-meta-glob-extglob': require('./no-import-meta-glob-extglob'),
        'valid-api-markers': require('./valid-api-markers'),
        // The 6.8 codemods of `administration:upgrade-extensions`. The runner keys its config by rule name,
        // so a rule that serves a second codemod with other options is registered under a second name.
        'no-deprecated-components-6-8': require('./no-deprecated-components'),
        'migrate-sw-tabs': require('./migrate-sw-tabs'),
        'no-sw-text-editor': require('./no-sw-text-editor'),
        'no-removed-form-components': require('./no-removed-form-components'),
        'no-order-state-select-v2-styles': require('./no-order-state-select-v2-styles'),
        'no-sw-popover-resize-width': require('./no-sw-popover-resize-width'),
        'no-renamed-twig-blocks': require('./no-renamed-twig-blocks'),
        'no-removed-twig-blocks': require('./no-removed-twig-blocks'),
        'no-removed-filter-computeds': require('./no-removed-filter-computeds'),
        'no-order-list-delivery-helpers': require('./no-order-list-delivery-helpers'),
        'no-module-snippets-property': require('./no-module-snippets-property'),
        'no-removed-image-assets': require('./no-removed-image-assets'),
        'no-removed-snippet-keys': require('./no-removed-snippet-keys'),
    },
};
