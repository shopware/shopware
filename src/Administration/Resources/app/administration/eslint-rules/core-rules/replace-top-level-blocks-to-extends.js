/**
 * @package admin
 *
 * Overview
 * This custom ESLint rule enforces that the top-level <sw-block> element should be using the `extends` prop instead of
 * the `name` prop. This is used in the plugins to extend the existing blocks in the Shopware Administration.
 *
 * How It Works
 *
 * Example
 * ```
 * // before
 * <sw-block name="block-1" :data="$dataScope">
 *     <div>
 *         <span>Title</span>
 *         <sw-block name="block-2" :data="$dataScope"></sw-block>
 *     </div>
 * </sw-block>
 * <sw-block name="block-3" :data="$dataScope">
 *     <div>
 *         <span>Content</span>
 *         <sw-block name="block-4" :data="$dataScope"></sw-block>
 *     </div>
 * </sw-block>
 *
 * // after
 * <sw-block extends="block-1">
 *     <div>
 *         <span>Title</span>
 *         <sw-block name="block-2" :data="$dataScope"></sw-block>jm
 *     </div>
 * </sw-block>
 * <sw-block extends="block-3">
 *     <div>
 *         <span>Content</span>
 *         <sw-block name="block-4" :data="$dataScope"></sw-block>
 *     </div>
 * </sw-block>
 * ```
 */
const blockList = require('../../blocks-list.json');

module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Change top-level <sw-block> to extends',
            category: 'Possible Errors',
            recommended: true,
        },
        fixable: 'code',
        schema: [],
    },
    create(context) {
        const sourceCode = context.getSourceCode();

        return sourceCode.parserServices.defineTemplateBodyVisitor({
            VElement(node) {
                if (node.name !== 'sw-block') {
                    return;
                }
                let isTopLevelBlock = true;
                let parent = node.parent;

                while (parent) {
                    if (parent.name === 'sw-block') {
                        isTopLevelBlock = false;
                        break;
                    }
                    parent = parent.parent;
                }

                if (!isTopLevelBlock) {
                    return;
                }

                const nameAttribute = node.startTag.attributes
                    .find((attr) => attr.key.name === 'name');
                const dataAttribute = node.startTag.attributes
                    .find((attr) => attr.key.name.name === 'bind'
                        && attr.key.argument.name === 'data');

                if (!nameAttribute) {
                    return;
                }

                const isOverridingExistingBlock = blockList.includes(nameAttribute.value.value);
                if (!isOverridingExistingBlock) {
                    return;
                }

                context.report({
                    node: node,
                    message: 'Top-level <sw-block> should use the `extends` prop instead of the `name` prop',
                    fix(fixer) {
                        const fixes = [];
                        const range = [nameAttribute.key.range[0], nameAttribute.key.range[1]];
                        fixes.push(fixer.replaceTextRange(range, 'extends'));

                        if (dataAttribute) {
                            fixes.push(fixer.remove(dataAttribute));
                        }
                        return fixes;
                    },
                });
            },
        });
    },
};

