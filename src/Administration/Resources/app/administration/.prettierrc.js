module.exports = {
    singleQuote: true,
    tabWidth: 4,
    printWidth: 125,
    trailingComma: 'all',
    multilineArraysWrapThreshold: 1,
    plugins: [
        require.resolve('prettier-plugin-multiline-arrays', {
            paths: [process.cwd(), __dirname],
        }),
    ],
}
