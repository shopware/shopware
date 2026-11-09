/**
 * @sw-package framework
 *
 * Shopware 6.8 removes the delivery, transaction and billing address helpers of `sw-order-list`.
 * The codemod rewrites each call with one order argument to the association of the order,
 * e.g. `getDeliveryState(item)` to `item.primaryOrderDelivery?.stateMachineState`, in overrides of
 * `sw-order-list` and in templates next to them. A call it cannot rewrite safely gets a TODO comment.
 */

const { createTodoReporter, todoMessages } = require('./utils/todo-comment');
const {
    componentOptions,
    definesMember,
    isInComponents,
    isSuperCallOf,
    propertyName,
} = require('./utils/component-context');
const { mustacheMatches, referencePattern } = require('./utils/twig-mustache');

const COMPONENTS = ['sw-order-list'];

/** Each helper and the expression it returned for an order in 6.8. */
const HELPERS = {
    getDelivery: (order) => `${order}.primaryOrderDelivery`,
    transaction: (order) => `${order}.primaryOrderTransaction`,
    getDeliveryAddress: (order) => `${order}.primaryOrderDelivery?.shippingOrderAddress`,
    getDeliveryState: (order) => `${order}.primaryOrderDelivery?.stateMachineState`,
    getTransactionState: (order) => `${order}.primaryOrderTransaction?.stateMachineState`,
    getBillingAddress: (order) => `${order}.billingAddress`,
};

const REMOVED = [...Object.keys(HELPERS), 'deliveryTooltip'];

/** Argument types that need no parentheses in front of a member access. */
const PLAIN_ARGUMENT_TYPES = new Set(['Identifier', 'MemberExpression', 'CallExpression', 'ThisExpression']);

/**
 * The helpers returned `null` for a missing delivery or transaction, the associations `undefined`.
 *
 * @param {import('eslint').Rule.Node} node
 */
function isComparedStrictly(node) {
    const parent = node.parent;

    if (parent?.type !== 'BinaryExpression' || !['===', '!=='].includes(parent.operator)) {
        return false;
    }

    const other = parent.left === node ? parent.right : parent.left;

    return (other.type === 'Literal' && other.value === null)
        || (other.type === 'Identifier' && other.name === 'undefined');
}

/** @param {string} name */
function todoHint(name) {
    if (name === 'deliveryTooltip') {
        return '"sw-order-list" no longer has "deliveryTooltip()". ' +
            'Build the tooltip from order.primaryOrderDelivery.shippingOrderAddress yourself.';
    }

    return `"sw-order-list" no longer has "${name}()". Use ${HELPERS[name]('order')} instead.`;
}

/** @type {import('eslint').Rule.RuleModule} */
module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'Replace the delivery and transaction helpers that Shopware 6.8 removes from sw-order-list',
            category: 'Shopware 6.8 Migration',
            recommended: false,
        },
        fixable: 'code',
        schema: [],
        messages: {
            ...todoMessages,
            replace: '"sw-order-list" no longer has "{{ name }}()". Use "{{ replacement }}" instead.',
        },
    },
    create(context) {
        const sourceCode = context.sourceCode;
        const reporter = createTodoReporter(context);

        /**
         * @param {import('eslint').Rule.Node} call
         * @param {string} name
         * @param {(hint: string) => void} reportTodo
         */
        const checkCall = (call, name, reportTodo) => {
            const [order, ...rest] = call.arguments;

            if (!HELPERS[name] || !order || rest.length > 0 || order.type === 'SpreadElement' || isComparedStrictly(call)) {
                reportTodo(todoHint(name));

                return;
            }

            const orderText = sourceCode.getText(order);
            const replacement = HELPERS[name](PLAIN_ARGUMENT_TYPES.has(order.type) ? orderText : `(${orderText})`);

            context.report({
                node: call,
                messageId: 'replace',
                data: { name, replacement },
                fix: (fixer) => fixer.replaceText(call, replacement),
            });
        };

        const scriptVisitor = {
            MemberExpression(node) {
                const name = propertyName(node);

                if (node.object.type !== 'ThisExpression' || !REMOVED.includes(name) || !isInComponents(context, node, COMPONENTS)) {
                    return;
                }

                const reportTodo = (hint) => reporter.reportScript(node, hint);
                const call = node.parent.type === 'CallExpression' && node.parent.callee === node ? node.parent : null;

                // An override that defines the helper itself keeps calling its own version.
                if (definesMember(componentOptions(node), ['methods'], name) || !call) {
                    reportTodo(todoHint(name));

                    return;
                }

                checkCall(call, name, reportTodo);
            },

            CallExpression(node) {
                const name = REMOVED.find((candidate) => isSuperCallOf(node, [candidate]));

                if (name && isInComponents(context, node, COMPONENTS)) {
                    reporter.reportScript(node, todoHint(name));
                }
            },
        };

        const parserServices = sourceCode.parserServices;

        if (!parserServices?.defineTemplateBodyVisitor) {
            return scriptVisitor;
        }

        const templateVisitor = {
            // `{{ … }}` in `.html.twig` files, which the Vue parser keeps as text.
            VText(node) {
                if (!isInComponents(context, null, COMPONENTS)) {
                    return;
                }

                for (const { match, offset } of mustacheMatches(sourceCode, node, referencePattern(REMOVED))) {
                    const name = match[1];
                    const callStart = offset + name.length;
                    const call = /^\s*\(\s*([\w$]+(?:\.[\w$]+)*)\s*\)/.exec(sourceCode.text.slice(callStart));
                    const before = sourceCode.text.slice(0, offset);
                    const after = call ? sourceCode.text.slice(callStart + call[0].length) : '';
                    const strict = /(null|undefined)\s*[!=]==\s*$/.test(before) || /^\s*[!=]==\s*(null|undefined)\b/.test(after);

                    if (!HELPERS[name] || !call || strict) {
                        reporter.reportTemplate(node.parent, todoHint(name));
                        continue;
                    }

                    const replacement = HELPERS[name](call[1]);

                    context.report({
                        loc: sourceCode.getLocFromIndex(offset),
                        messageId: 'replace',
                        data: { name, replacement },
                        fix: (fixer) => fixer.replaceTextRange([offset, callStart + call[0].length], replacement),
                    });
                }
            },

            Identifier(node) {
                const parent = node.parent;
                const isMemberName = parent.type === 'MemberExpression' && parent.property === node && !parent.computed;

                if (isMemberName || !REMOVED.includes(node.name) || !isInComponents(context, null, COMPONENTS)) {
                    return;
                }

                let element = parent;

                while (element && element.type !== 'VElement') {
                    element = element.parent;
                }

                const reportTodo = (hint) => {
                    if (element) {
                        reporter.reportTemplate(element, hint);
                    }
                };

                if (parent.type === 'CallExpression' && parent.callee === node) {
                    checkCall(parent, node.name, reportTodo);

                    return;
                }

                reportTodo(todoHint(node.name));
            },
        };

        return parserServices.defineTemplateBodyVisitor(templateVisitor, scriptVisitor);
    },
};
