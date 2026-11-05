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
    },
};
