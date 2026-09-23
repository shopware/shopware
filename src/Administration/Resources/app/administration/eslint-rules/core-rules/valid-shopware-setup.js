/**
 * @sw-package framework
 */

const { analyzeShopwareSetupSfc, ShopwareSetupTransformError } = require('../../build/vue-setup-transform');

/**
 * Reports the build's native setup errors in the editor.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Validate Shopware setup SFC blocks',
            category: 'Possible Errors',
            recommended: true,
        },
        schema: [],
    },

    create(context) {
        return {
            Program(node) {
                const filename = context.filename ?? context.getFilename();

                if (!filename.endsWith('.vue')) {
                    return;
                }

                const sourceCode = context.sourceCode ?? context.getSourceCode();

                try {
                    analyzeShopwareSetupSfc(sourceCode.text, filename);
                } catch (error) {
                    if (!(error instanceof ShopwareSetupTransformError)) {
                        throw error;
                    }

                    const clampIndex = (index) => Math.min(index ?? 0, sourceCode.text.length);
                    const start = sourceCode.getLocFromIndex(clampIndex(error.index));
                    const loc =
                        error.endIndex === null
                            ? start
                            : { start, end: sourceCode.getLocFromIndex(clampIndex(error.endIndex)) };

                    context.report({
                        node,
                        loc,
                        message: error.message,
                    });
                }
            },
        };
    },
};
