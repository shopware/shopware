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
                const filename = context.getFilename();

                if (!filename.endsWith('.vue')) {
                    return;
                }

                const sourceCode = context.getSourceCode();

                try {
                    validateShopwareSetupSfc(sourceCode.text, filename);
                } catch (error) {
                    if (!(error instanceof ShopwareSetupTransformError)) {
                        throw error;
                    }

                    context.report({
                        node,
                        loc: sourceCode.getLocFromIndex(Math.min(error.index, sourceCode.text.length)),
                        message: error.message,
                    });
                }

            },
        };
    },
};
