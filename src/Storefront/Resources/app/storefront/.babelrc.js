/**
 * @package storefront
 */
module.exports = {
    presets: [
        ['@babel/preset-env', {
            useBuiltIns: 'entry',
            corejs: '3.27',
            bugfixes: true,
        }],
        '@babel/preset-typescript',
    ],
    plugins: [
        ['@babel/plugin-proposal-decorators', { version: '2023-01' }],
    ],
};
