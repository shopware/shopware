const isDevMode = process.env.NODE_ENV !== 'production';

module.exports = {
    root: true,
    'extends': 'eslint:recommended',
    'parser': 'babel-eslint',
    'env': {
        'browser': true,
        'jquery': true,
        'node': true,
        'es6': true,
    },
    'globals': {
        'gtag': true
    },
    'parserOptions': {
        'ecmaVersion': 6,
        'sourceType': 'module',
    },
    'rules': {
        'comma-dangle': ['error', 'never'],
        'one-var': ['error', 'never'],
        'no-console': 0,
        'no-debugger': (isDevMode ? 0 : 2),
        'prefer-const': 'warn',
        'quotes': ['warn', 'single'],
        'indent': ['warn', 4, {
            'SwitchCase': 1,
        }],
    },
};
