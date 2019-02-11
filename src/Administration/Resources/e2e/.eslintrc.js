module.exports = {
    extends: 'eslint:recommended',
    parser: "babel-eslint",
    root: true,
    env: {
        node: true
    },
    parserOptions: {
        ecmaVersion: 2017
    },
    rules: {
        'no-console': 0,
        'no-unused-vars': 'warn',
        'comma-dangle': [ 'error', 'never' ],
        'semi': [ 'error', 'always' ],
        'indent': [ 'error', 4 ]
    },
    globals: {
        'Promise': true,
        'Shopware': true,
        'localStorage': true
    }
};