module.exports = {
    presets: [
        ['@babel/preset-env', {
            'useBuiltIns': 'usage',
        }],
    ],
    plugins: [
        '@babel/plugin-proposal-class-properties',
        '@babel/plugin-transform-object-assign',
    ],
};
