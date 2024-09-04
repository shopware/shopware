/**
 * @package admin
 */
const ATTRIBUTE_VALUE_REGEXP = /v-(?:if|else-if)="([^"]+)"/;
/**
 * Overview
 * This custom ESLint rule enforces that the v-if, v-else or v-else-if attributes are moved to the <sw-block> element
 * when it has a single child.
 *
 * How It Works
 * The rule identifies <sw-block> elements with a single child and checks if the child element has v-if, v-else or
 * v-else-if attributes. If it finds any of these attributes, it reports an error and suggests moving the attribute to
 * the <sw-block> element.
 *
 * Example
 * ```
 * // before
 * <sw-block>
 *     <div v-if="condition"></div>
 * </sw-block>
 * <sw-block>
 *     <div v-else></div>
 * </sw-block>
 *
 * // after
 * <sw-block v-if="condition">
 *      <div></div>
 * </sw-block>
 * <sw-block v-else>
 *      <div></div>
 * </sw-block>
 * ```
 */
module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: '<sw-block> with single child should move v-if, v-else or v-else-if attributes to the <sw-block> element',
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
                if (node.rawName !== 'sw-block') {
                    return;
                }
                const childrenElements = node.children.filter(child => child.type === 'VElement');

                if (childrenElements.length !== 1) {
                    return;
                }

                if (node.startTag.attributes.find(attr => attr.directive &&
                    ['if', 'else', 'else-if'].includes(attr.key.name.name))) {
                    return;
                }

                const childAttributes = childrenElements[0].startTag.attributes;
                const vIfAttribute = childAttributes.find(attr => attr.directive && attr.key.name.name === 'if');
                const vElseAttribute = childAttributes.find(attr => attr.directive && attr.key.name.name === 'else');
                const vElseIfAttribute = childAttributes.find(attr => attr.directive && attr.key.name.name === 'else-if');

                if (vIfAttribute || vElseAttribute || vElseIfAttribute) {
                    context.report({
                        node,
                        message: '<sw-block> with single child should move v-if, v-else or v-else-if attributes to the <sw-block> element',
                        fix(fixer) {
                            const fixes = [];
                            const insertPos = node.startTag.range[1] - 1;
                            const attributeNode = vIfAttribute ?? vElseAttribute ?? vElseIfAttribute;
                            const attributeCode = sourceCode.text.slice(attributeNode.range[0], attributeNode.range[1]);
                            const attributeValue = ATTRIBUTE_VALUE_REGEXP.exec(attributeCode)?.[1];

                            if (vIfAttribute) {
                                fixes.push(fixer.remove(vIfAttribute));
                                fixes.push(fixer.insertTextBeforeRange(
                                    [insertPos, insertPos],
                                    ` v-if="${attributeValue}" `,
                                ));
                            }
                            if (vElseAttribute) {
                                fixes.push(fixer.remove(vElseAttribute));
                                fixes.push(fixer.insertTextBeforeRange([insertPos, insertPos], ' v-else '));
                            }
                            if (vElseIfAttribute) {
                                fixes.push(fixer.remove(vElseIfAttribute));
                                fixes.push(fixer.insertTextBeforeRange(
                                    [insertPos, insertPos],
                                    ` v-else-if="${attributeValue}" `,
                                ));
                            }
                            return fixes;
                        },
                    });
                }
            },
        });
    },
};

