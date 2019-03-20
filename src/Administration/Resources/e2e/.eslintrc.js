module.exports = {
    extends: '../common/eslint-config-base/index.js',
    // We're dealing with Node.js and browser env
    env: {
        node: true,
        browser: true
    },
    globals: {
        Promise: true,
        Shopware: true,
        localStorage: true
    },

    rules: {
        // Enable dynamic imports
        'import/no-dynamic-require': 0,
        // require statements don't have to be on the root level
        'global-require': 0,
        // Disable import lookup cause we're using babel for look up
        'import/no-unresolved': 0,
        // Disable max line length
        'max-len': 0,
        // Nightwatch requiring custom commands on it's own, therefore we have unused expressions
        'no-unused-expressions': 0,
        // Enforce arrow callback except for named functions
        'prefer-arrow-callback': [ "error", { "allowNamedFunctions": true } ]
    }
};
