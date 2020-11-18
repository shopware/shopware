const path = require('path');

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
        cy: true
    },

    plugins: ['jest'],

    settings: {
        'import/resolver': {
            node: {},
            webpack: {
                config: {
                    // Sync with webpack.config.js
                    resolve: {
                        extensions: ['.js', '.vue', '.json', '.less', '.twig'],
                        alias: {
                            vue$: 'vue/dist/vue.esm.js',
                            src: path.join(__dirname, 'src'),
                            module: path.join(__dirname, 'src/module'),
                            scss: path.join(__dirname, 'src/app/assets/scss'),
                            assets: path.join(__dirname, 'static')
                        }
                    }
                }
            }
        }
    },

    rules: {
        // Match the max line length with the phpstorm default settings
        'max-len': ['warn', 125, { ignoreRegExpLiterals: true }],
        // Warn about useless path segment in import statements
        'import/no-useless-path-segments': 0,
        // don't require .vue and .js extensions
        'import/extensions': ['error', 'always', {
            js: 'never',
            vue: 'never'
        }]
    }
};
