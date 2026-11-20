/**
 * @sw-package framework
 *
 * Upgrade codemod `sw-popover`: the `resize-width` prop of `sw-popover` is removed in 6.8. The wrapper accepts
 * `match-reference-width` on 6.7 and 6.8, so the rule renames the prop. When both are set, `match-reference-width`
 * already wins and `resize-width` is removed.
 *
 * `sw-popover` itself is deprecated in favor of `mt-floating-ui`. The move can't be automated, because the anchor,
 * `v-if` and `z-index` behave differently, so every `sw-popover` gets a TODO.
 */

const { reportTodo } = require('./upgrade-helpers/todo-comment');
const { elementName, attributeName, attributeRemovalRange } = require('./upgrade-helpers/component-tags');

const ID = 'sw-popover';

const MESSAGES = {
    resizeWidth: 'resize-width of sw-popover is removed in 6.8. Use match-reference-width instead.',
    floatingUi: 'sw-popover is deprecated. Move to mt-floating-ui: v-if → :is-opened, popover-class → class; z-index has no replacement.',
};

const RENAMES = {
    'resize-width': 'match-reference-width',
    resizeWidth: 'matchReferenceWidth',
};

/**
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = {
    meta: {
        type: 'problem',
        fixable: 'code',
        docs: {
            description: 'Rename resize-width of sw-popover to match-reference-width and mark sw-popover for the move to mt-floating-ui',
        },
        schema: [],
    },

    /** @param {import('eslint').Rule.RuleContext} context */
    create(context) {
        const sourceCode = context.sourceCode;

        if (!sourceCode.parserServices?.defineTemplateBodyVisitor) {
            return {};
        }

        return sourceCode.parserServices.defineTemplateBodyVisitor({
            VElement(node) {
                if (elementName(node) !== 'sw-popover') {
                    return;
                }

                reportTodo(context, { id: ID, message: MESSAGES.floatingUi, range: node.range, scope: 'template' });

                const names = node.startTag.attributes.map((attribute) => attributeName(attribute));
                const hasMatchReferenceWidth = names.some((name) => Object.values(RENAMES).includes(name));

                node.startTag.attributes.forEach((attribute, index) => {
                    const newName = RENAMES[names[index]];

                    if (!newName) {
                        return;
                    }

                    const keyNode = attribute.directive ? attribute.key.argument : attribute.key;

                    context.report({
                        node: attribute,
                        message: MESSAGES.resizeWidth,
                        fix: (fixer) => {
                            return hasMatchReferenceWidth
                                ? fixer.removeRange(attributeRemovalRange(sourceCode, attribute))
                                : fixer.replaceText(keyNode, newName);
                        },
                    });
                });
            },
        });
    },
};
