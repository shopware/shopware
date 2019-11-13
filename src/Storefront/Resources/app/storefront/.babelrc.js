module.exports = {
    presets: [
        ['@babel/preset-env', {
            'useBuiltIns': 'entry',
            'corejs': 2,
        }],
    ],
    plugins: [
        '@babel/plugin-proposal-class-properties',
        '@babel/plugin-transform-object-assign',
    ],
};
