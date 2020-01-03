module.exports = {
    extends: [
        'plugin:cypress/recommended'
    ],
    plugins: [
        'cypress',
        'chai-friendly'
    ],
    env: {
        node: true,
        "cypress/globals": true
    },
    globals: {
        Promise: true,
        Shopware: true,
        localStorage: true
    },
    parserOptions: {
        ecmaVersion: 6,
        ecmaFeatures: {
            experimentalObjectRestSpread: true
        }
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
        // Cypress requiring custom commands on it's own, therefore we have unused expressions
        'no-unused-expressions': 0,
        'chai-friendly/no-unused-expressions': 2
    }
};
