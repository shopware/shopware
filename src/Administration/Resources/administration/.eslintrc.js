// http://eslint.org/docs/user-guide/configuring

module.exports = {
    root: true,
    parser: 'babel-eslint',
    parserOptions: {
        sourceType: 'module'
    },
    env: {
        browser: true,
    },

    globals: {
        Shopware: true
    },

    // https://github.com/airbnb/javascript
    extends: 'airbnb-base',
    // required to lint *.vue files
    plugins: [
        'html'
    ],

    'settings': {
        'import/resolver': {
            'webpack': {
                'config': 'build/webpack.base.conf.js'
            }
        }
    },

    // add your custom rules here
    'rules': {
        // allow paren-less arrow functions
        'arrow-parens': 0,
        // allow async-await
        'generator-star-spacing': 0,
        // allow debugger during development
        'no-debugger': process.env.NODE_ENV === 'production' ? 2 : 0,
        // allow console during development
        'no-console': 0,
        // 4 spaces for indention
        'indent': ['error', 4],
        // Remove forced trailing comma
        'comma-dangle': ['error', 'never'],
        // Allow functions to be used before definition, useful for exporting a object literal at the beginning of the file
        'no-use-before-define': ['error', { 'functions': false }],
        // don't require .vue extension when importing
        'import/extensions': ['error', 'always', {
            'js': 'never',
            'vue': 'never'
        }],
        // Allow reassigning function parameters
        'no-param-reassign': 0,

        // Match the max line length with the phpstorm default settings
        'max-len': [ 'warn', 125 ],

        'arrow-body-style': [ 'off' ],

        // Allow both types of linebreak because of multiple contributors with different systems.
        'linebreak-style': 0,

        'no-prototype-builtins': 0,

        'object-curly-newline': [ 'error', { 'consistent': true } ],

        'no-underscore-dangle': [ 'error', {
            'allowAfterThis': true,
            'allowAfterSuper': true
        }],

        "prefer-destructuring": [ 'off', { 'object': true, 'array': false } ],

        // allow optionalDependencies
        'import/no-extraneous-dependencies': ['error', {
            'optionalDependencies': ['test/unit/index.js']
        }]
    }
};
