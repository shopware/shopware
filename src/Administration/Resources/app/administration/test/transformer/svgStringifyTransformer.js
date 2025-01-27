/**
 * @sw-package framework
 */

module.exports = {
    process(sourceText) {
        return {
            code: `module.exports = ${JSON.stringify(sourceText)};`,
        };
    },
};
