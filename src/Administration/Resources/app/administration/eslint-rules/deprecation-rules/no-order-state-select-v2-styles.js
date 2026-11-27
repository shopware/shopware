/**
 * @sw-package framework
 *
 * Upgrade codemod `order-state-select-props`: 6.8 removes the `rounded-style` and `background-style` props of
 * `sw-order-state-select-v2`. Both have no effect anymore; the select takes its color from `state-name`, which exists
 * since 6.7.16.0.
 *
 * - `rounded-style` is removed.
 * - `background-style` from a core helper becomes `state-name` with the technical name of the state:
 *   `backgroundStyle('order_transaction'|'order_delivery'|'order')` in an override of `sw-order-general-info` and
 *   `stateSelectBackgroundStyle` in an override of `sw-order-details-state-card`.
 * - `background-style` is removed when `state-name` is already set.
 * - Any other `background-style` stays and gets a TODO.
 */

const { reportTodo } = require('./upgrade-helpers/todo-comment');
const { elementName, attributeKey, attributeRemovalRange } = require('./upgrade-helpers/component-tags');
const { targetsOfTemplate } = require('./upgrade-helpers/twig-target');

const ID = 'order-state-select-props';

const MESSAGES = {
    roundedStyle: 'rounded-style of sw-order-state-select-v2 has no effect and is removed in 6.8.',
    stateName: 'background-style of sw-order-state-select-v2 is removed in 6.8. Use state-name with the technical name of the state instead.',
    backgroundStyle: 'background-style of sw-order-state-select-v2 has no effect and is removed in 6.8. Pass the technical name '
        + 'of the current state as state-name instead, e.g. :state-name="order.stateMachineState.technicalName".',
};

// `backgroundStyle(stateType)` of sw-order-general-info reads the state of these computed entities
const GENERAL_INFO_ENTITIES = {
    order_transaction: 'transaction',
    order_delivery: 'delivery',
    order: 'order',
};

/**
 * The entity whose state a core helper call in `background-style` styles, or null.
 *
 * @param {object|null} expression
 * @param {() => string[]} targets
 * @returns {string|null}
 */
function stateEntity(expression, targets) {
    if (!expression) {
        return null;
    }

    if (
        expression.type === 'CallExpression'
        && expression.callee.type === 'Identifier'
        && expression.callee.name === 'backgroundStyle'
        && expression.arguments.length === 1
        && expression.arguments[0].type === 'Literal'
        && Object.hasOwn(GENERAL_INFO_ENTITIES, expression.arguments[0].value)
        && targets().includes('sw-order-general-info')
    ) {
        return GENERAL_INFO_ENTITIES[expression.arguments[0].value];
    }

    if (
        expression.type === 'Identifier'
        && expression.name === 'stateSelectBackgroundStyle'
        && targets().includes('sw-order-details-state-card')
    ) {
        return 'entity';
    }

    return null;
}

/**
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = {
    meta: {
        type: 'problem',
        fixable: 'code',
        docs: {
            description: 'Remove rounded-style and background-style of sw-order-state-select-v2, which 6.8 removes',
        },
        schema: [],
    },

    /** @param {import('eslint').Rule.RuleContext} context */
    create(context) {
        const sourceCode = context.sourceCode;

        if (!sourceCode.parserServices?.defineTemplateBodyVisitor) {
            return {};
        }

        let templateTargets = null;
        const targets = () => {
            templateTargets ??= targetsOfTemplate(context);

            return templateTargets;
        };

        return sourceCode.parserServices.defineTemplateBodyVisitor({
            VElement(node) {
                if (elementName(node) !== 'sw-order-state-select-v2') {
                    return;
                }

                const attributes = node.startTag.attributes;
                const find = (name) => attributes.find((attribute) => attributeKey(attribute) === name);
                const roundedStyle = find('roundedstyle');
                const backgroundStyle = find('backgroundstyle');
                const hasStateName = Boolean(find('statename'));

                const messages = [];
                const edits = [];

                if (roundedStyle) {
                    messages.push(MESSAGES.roundedStyle);
                    edits.push((fixer) => fixer.removeRange(attributeRemovalRange(sourceCode, roundedStyle)));
                }

                if (backgroundStyle) {
                    const entity = backgroundStyle.directive ? stateEntity(backgroundStyle.value?.expression, targets) : null;

                    if (hasStateName) {
                        messages.push(MESSAGES.stateName);
                        edits.push((fixer) => fixer.removeRange(attributeRemovalRange(sourceCode, backgroundStyle)));
                    } else if (entity) {
                        messages.push(MESSAGES.stateName);
                        edits.push((fixer) => {
                            return fixer.replaceText(backgroundStyle, `:state-name="${entity}.stateMachineState.technicalName"`);
                        });
                    } else {
                        reportTodo(context, { id: ID, message: MESSAGES.backgroundStyle, range: node.range, scope: 'template' });
                    }
                }

                // One report per element, so that the fixes of neighboring attributes don't collide
                if (edits.length > 0) {
                    context.report({
                        node: node.startTag,
                        message: messages.join(' '),
                        fix: (fixer) => edits.map((edit) => edit(fixer)),
                    });
                }
            },
        });
    },
};
