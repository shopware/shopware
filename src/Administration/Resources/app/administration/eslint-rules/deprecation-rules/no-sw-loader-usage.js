/**
 * @sw-package framework
 *
 * Migrates the deprecated `sw-loader` component to `mt-loader`.
 *
 * `mt-loader` renders a backdrop by default, `sw-loader` never did.
 * The fix adds `:backdrop="false"`, unless the usage already sets `backdrop`.
 */

const OLD_COMPONENT = 'sw-loader';
const NEW_COMPONENT = 'mt-loader';
const NO_BACKDROP = ':backdrop="false"';

function setsBackdrop(attribute) {
    if (!attribute.directive) {
        return attribute.key.name === 'backdrop';
    }

    return attribute.key.name.name === 'bind' && attribute.key.argument?.name === 'backdrop';
}

/**
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = {
    meta: {
        type: 'problem',
        fixable: 'code',
        docs: {
            description: `Migrate the deprecated "${OLD_COMPONENT}" to "${NEW_COMPONENT}"`,
            recommended: true,
        },
        messages: {
            migrate: `"${OLD_COMPONENT}" is deprecated and will be removed in 6.8. Please use "${NEW_COMPONENT}" instead.`,
        },
        schema: [],
    },

    /** @param {import('eslint').Rule.RuleContext} context */
    create(context) {
        const sourceCode = context.sourceCode;

        const templateVisitor = {
            VElement(node) {
                if (node.name !== OLD_COMPONENT) {
                    return;
                }

                context.report({
                    node: node.startTag,
                    messageId: 'migrate',
                    *fix(fixer) {
                        const { startTag, endTag } = node;
                        const attributes = startTag.attributes;
                        const lastAttribute = attributes.at(-1);
                        const addBackdrop = !attributes.some(setsBackdrop);
                        const tagNameRange = [startTag.range[0], startTag.range[0] + 1 + OLD_COMPONENT.length];
                        const indentation = ' '.repeat(node.loc.start.column);
                        const todo = `<!-- TODO Codemod: Converted from ${OLD_COMPONENT} - please check if everything works correctly -->`;
                        const backdropAfterName = addBackdrop && !lastAttribute ? ` ${NO_BACKDROP}` : '';

                        yield fixer.replaceTextRange(
                            tagNameRange,
                            `${todo}\n${indentation}<${NEW_COMPONENT}${backdropAfterName}`,
                        );

                        if (addBackdrop && lastAttribute) {
                            // Keep the layout of the tag: one attribute per line gets the backdrop on its own line
                            const separator = lastAttribute.loc.start.line === node.loc.start.line
                                ? ' '
                                : `\n${sourceCode.lines[lastAttribute.loc.start.line - 1].match(/^\s*/)[0]}`;

                            yield fixer.insertTextAfter(lastAttribute, `${separator}${NO_BACKDROP}`);
                        }

                        if (endTag) {
                            yield fixer.replaceText(endTag, `</${NEW_COMPONENT}>`);
                        }
                    },
                });
            },
        };

        return sourceCode.parserServices.defineTemplateBodyVisitor(templateVisitor);
    },
};
