const { join } = require('path');

function resolve(directory) {
    return join(__dirname, directory);
}

module.exports = {
    extends: '@shopware-ag/eslint-config-base',
    env: {
        browser: true,
        'jest/globals': true
    },

    globals: {
        Shopware: true,
        VueJS: true,
        Cypress: true,
        cy: true,
    },

    plugins: ['jest'],

    settings: {
        'import/resolver': {
            webpack: {
                config: resolve('./build/webpack.base.conf.js')
            }
        }
    },

    rules: {
        // Match the max line length with the phpstorm default settings
        'max-len': [ 'warn', 125, { 'ignoreRegExpLiterals': true } ],
        // Warn about useless path segment in import statements
        'import/no-useless-path-segments': 0,
        // don't require .vue and .js extensions
        'import/extensions': [ 'error', 'always', {
            js: 'never',
            vue: 'never'
        } ],
    }
};
