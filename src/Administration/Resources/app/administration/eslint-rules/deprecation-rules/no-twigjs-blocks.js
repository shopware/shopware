/* eslint-disable max-len */

/**
 * @package admin
 *
 * This rule validates that no TwigJs blocks are added.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = {
    meta: {
        type: 'problem',

        docs: {
            description: 'No new TwigJs blocks',
            recommended: true,
            url: 'https://handbook.shopware.com/Product/Product/Components/Admin/NewFeatures',
        },
    },
    create(context) {
        return context.parserServices.defineTemplateBodyVisitor(
            // Event handlers for <template>.
            {
                VElement(node) {
                    // Template got no comments
                    if (!node.comments || node.comments.length <= 0) {
                        return;
                    }

                    const blockComments = node.comments.filter(c => c.type === 'HTMLComment' && c.value.startsWith('blck'));
                    blockComments.forEach((block => {
                        context.report({
                            loc: block.loc,
                            message: 'No new TwigJs blocks should be added.',
                        });
                    }));
                },
            },
        );
    },
};
