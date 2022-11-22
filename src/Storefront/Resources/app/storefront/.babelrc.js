/**
 * @package storefront
 */
module.exports = {
    presets: [
        ['@babel/preset-env', {
            "targets": "> 1%, IE 11, not dead",
            'useBuiltIns': 'entry',
            'corejs': 2,
        }],
        '@babel/preset-typescript',
    ],
    plugins: [
        '@babel/plugin-proposal-class-properties',
        '@babel/plugin-transform-object-assign',
    ],
};
