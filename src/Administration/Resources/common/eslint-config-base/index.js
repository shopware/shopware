module.exports = {
    root: true,
    // See: https://github.com/airbnb/javascript
    extends: 'airbnb-base',
    parser: 'babel-eslint',
    parserOptions: {
        sourceType: 'module'
    },
    rules: {
        'no-multiple-empty-lines': ['error', { max: 2, maxEOF: 1 }],
        // Allow parens less arrow functions
        'arrow-parens': 0,
        // Don't enforce a particular style of arrow functions
        'arrow-body-style': 0,
        // Allow space before or after start
        'generator-star-spacing': 0,
        // allow debugger during development
        'no-debugger': process.env.NODE_ENV === 'production' ? 2 : 0,
        // allow console
        'no-console': 0,
        // 4 spaces for indention
        indent: ['error', 4, { 'SwitchCase': 1 }],
        // Remove forced trailing comma
        'comma-dangle': ['error', 'never'],
        // Allow functions to be used before definition, useful for exporting a object literal at the beginning of the file
        'no-use-before-define': ['error', {
            functions: false
        }],
        // Allow reassigning function parameters
        'no-param-reassign': 0,
        // Match the max line length with the phpstorm default settings
        'max-len': ['warn', 125, { ignoreRegExpLiterals: true }],
        // Allow unix line breaks
        'linebreak-style': ['error', 'unix'],
        // Allow any kind of object definition standard
        'object-shorthand': 0,
        // Allow double escaping, it doesn't hurt anybody
        'no-useless-escape': 0,
        // Allow usage direct call of builtin methods
        'no-prototype-builtins': 0,
        // Enforce consistent curly parens style
        'object-curly-newline': ['error', { consistent: true }],
        // Allow underscore dangling
        'no-underscore-dangle': 0,
        // Prefer object and array destructuring
        'prefer-destructuring': ['off', { object: true, array: false }],
        // Allow both styles of operator line breaks
        'operator-linebreak': 0,
        // Disable import cycle reference check to speed up linting time
        'import/no-cycle': 0,
        // Don't enforce usage of this in class
        'class-methods-use-this': 0,
        // don't require .vue and .js extensions
        'import/extensions': ['error', 'always', {
            js: 'never'
        }]
    }
};
