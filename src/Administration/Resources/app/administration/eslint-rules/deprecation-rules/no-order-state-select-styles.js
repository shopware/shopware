/**
 * @sw-package framework
 *
 * Shopware 6.8 removes the `background-style` and `rounded-style` props of `sw-order-state-select-v2`.
 * The select takes the technical name of the current state as `state-name` instead. The codemod
 * removes the old props and adds `state-name` when it can derive it from the placeholder or from the
 * order helpers core used, otherwise it writes a TODO comment.
 */

const { createTodoReporter, todoMessages } = require('./utils/todo-comment');
const { attributeRemovalRange, propAttributes } = require('./utils/template-elements');

const STYLE_PROPS = ['background-style', 'rounded-style'];

/** The `background-style` bindings core used, and the state each one stood for. */
const BACKGROUND_STYLE_STATES = {
    'backgroundStyle(\'order\')': 'order.stateMachineState.technicalName',
    'backgroundStyle(\'order_transaction\')': 'transaction.stateMachineState.technicalName',
    'backgroundStyle(\'order_delivery\')': 'delivery.stateMachineState.technicalName',
    stateSelectBackgroundStyle: 'entity.stateMachineState.technicalName',
};

const HINT = 'sw-order-state-select-v2 no longer has background-style and rounded-style in 6.8. ' +
    'Pass the technical name of the current state as state-name, e.g. :state-name="order.stateMachineState.technicalName".';

/** @type {import('eslint').Rule.RuleModule} */
module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Replace the style props of sw-order-state-select-v2 that Shopware 6.8 removes with state-name',
            category: 'Shopware 6.8 Migration',
            recommended: false,
        },
        fixable: 'code',
        schema: [],
        messages: {
            ...todoMessages,
            replace: 'sw-order-state-select-v2 no longer has background-style and rounded-style in 6.8. Use state-name instead.',
        },
    },
    create(context) {
        const sourceCode = context.sourceCode;
        const text = sourceCode.text;
        const reporter = createTodoReporter(context);
        const parserServices = sourceCode.parserServices;

        if (!parserServices?.defineTemplateBodyVisitor) {
            return {};
        }

        /**
         * The state name expression for the select, or null.
         *
         * @param {import('vue-eslint-parser').AST.VElement} node
         * @param {object[]} styles
         */
        const stateNameOf = (node, styles) => {
            const [placeholder] = propAttributes(node, ['placeholder']).filter((attribute) => attribute.directive);
            const placeholderText = placeholder?.value?.expression ? sourceCode.getText(placeholder.value.expression) : '';
            const state = /^([\w$.?]+)\.stateMachineState\.translated\.name$/.exec(placeholderText);

            if (state) {
                return `${state[1]}.stateMachineState.technicalName`;
            }

            const background = styles.find((attribute) => attribute.directive && attribute.value?.expression);
            const backgroundText = background ? sourceCode.getText(background.value.expression).replace(/"/g, '\'') : '';

            return BACKGROUND_STYLE_STATES[backgroundText] ?? null;
        };

        return parserServices.defineTemplateBodyVisitor({
            VElement(node) {
                if (node.rawName !== 'sw-order-state-select-v2') {
                    return;
                }

                const styles = propAttributes(node, STYLE_PROPS);

                if (styles.length === 0) {
                    reporter.reportTemplateIfWritten(node, HINT);

                    return;
                }

                const hasStateName = propAttributes(node, ['state-name']).length > 0;
                const stateName = hasStateName ? null : stateNameOf(node, styles);

                if (!hasStateName && !stateName) {
                    reporter.reportTemplate(node, HINT);
                }

                context.report({
                    loc: styles[0].loc,
                    messageId: 'replace',
                    fix: (fixer) => styles.map((attribute, index) => {
                        if (index === 0 && stateName) {
                            return fixer.replaceText(attribute, `:state-name="${stateName}"`);
                        }

                        return fixer.removeRange(attributeRemovalRange(text, attribute));
                    }),
                });
            },
        });
    },
};
