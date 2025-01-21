/**
 * @package admin
 *
 * Overview
 * This custom ESLint rule enforces that the template tags with no attributes are removed.
 * Those template tags are the leftovers from applying the rules that move the v-if conditions to the block.
 *
 * Example
 *
 * // before
 *     <sw-block>
 *         <template>
 *             <h1>Title</h1>
 *             <p>{{ message }}</p>
 *         </template>
 *     </sw-block>
 *     <sw-block>
 *         <template v-if="!!message">
 *                 <h1>Title</h1>
 *                 <p>{{ message }}</p>
 *         </template>
 *     </sw-block>
 *
 * // after
 *     <sw-block>
 *         <h1>Title</h1>
 *         <p>{{ message }}</p>
 *     </sw-block>
 *     <sw-block>
 *         <template v-if="!!message">
 *                 <h1>Title</h1>
 *                 <p>{{ message }}</p>
 *         </template>
 *     </sw-block>
 *
 */
module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'remove templates tags with no attributes',
            category: 'Possible Errors',
            recommended: true,
        },
        fixable: 'code',
        schema: [], // No options needed
    },
    create(context) {
        const sourceCode = context.getSourceCode();

        return sourceCode.parserServices.defineTemplateBodyVisitor({
            VElement(node) {
                if (node.rawName !== 'template') {
                    return;
                }
                // if it is a root template tag
                if (node.parent.type === 'VDocumentFragment') {
                    return;
                }
                const attributes = node.startTag.attributes;
                if (attributes.length > 0) {
                    return;
                }
                context.report({
                    node,
                    message: 'remove templates tags with no attributes',
                    fix(fixer) {
                        const startingPosition = node.startTag.range[0];
                        const endingPosition = node.endTag.range[1];
                        const innerCode = sourceCode.text.slice(node.startTag.range[1], node.endTag.range[0]).trim();

                        return [
                            fixer.removeRange([startingPosition, endingPosition]),
                            fixer.insertTextAfter(node, innerCode),
                        ];
                    },
                });
            },
        });
    },
};

