module.exports = {
    presets: [
        ['@babel/preset-env', {
            'useBuiltIns': 'entry'
        }],
    ],
    plugins: [
        '@babel/plugin-proposal-class-properties',
        '@babel/plugin-transform-object-assign',
    ],
};
