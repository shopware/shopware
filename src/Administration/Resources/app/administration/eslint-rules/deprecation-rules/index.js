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
        'no-module-snippets': require('./no-module-snippets'),
        'no-date-filter-computed': require('./no-filter-computeds').dateFilter,
        'no-asset-filter-computed': require('./no-filter-computeds').assetFilter,
        'no-order-list-delivery-helpers': require('./no-order-list-delivery-helpers'),
        'no-removed-service-methods': require('./no-removed-service-methods'),
        'no-removed-select-components': require('./no-removed-select-components'),
        'no-order-state-select-styles': require('./no-order-state-select-styles'),
        'no-removed-snippet-keys-and-images': require('./no-removed-snippet-keys-and-images'),
        'no-renamed-twig-blocks': require('./twig-blocks-6-8').renamed,
        'no-removed-twig-blocks': require('./twig-blocks-6-8').removed,
        ...require('./todo-rules-6-8'),
    },
};
