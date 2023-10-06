module.exports = {
    env: {
        node: true,
    },
    plugins: [
        'cypress',
    ],
    extends: [
        'plugin:cypress/recommended',
        'eslint:recommended',
    ],
    rules: {
        'indent': ['error', 4],
        'no-console': ['error', { allow: ['warn', 'error'] }],
        'comma-dangle': ['error', 'always-multiline'],
        'semi': ['error', 'always'],
    },
};
