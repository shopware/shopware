/**
 * @sw-package framework
 */

const { validateShopwareSetupSfc, ShopwareSetupTransformError } = require('../../build/vue-setup-transform');

/**
 * Reports shared transform errors through ESLint so editor feedback matches build behavior.
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
                    validateShopwareSetupSfc(sourceCode.text, filename);
                } catch (error) {
                    if (!(error instanceof ShopwareSetupTransformError)) {
                        throw error;
                    }

                    const clampIndex = (index) => Math.min(index ?? 0, sourceCode.text.length);
                    const start = sourceCode.getLocFromIndex(clampIndex(error.index));
                    // When the thrower gave a full node range, report start+end so the editor underlines
                    // the whole offending token; otherwise fall back to a single-point location.
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
