/**
 * @sw-package framework
 *
 * Upgrade codemod `system-config-schema`: 6.8 removes the flat `config` data of `sw-system-config` and
 * `systemConfigApiService.getConfig()`. The factory marks the members and the service call. The visitors mark
 * - code that replaces `getConfig` of the service, in `addServiceProviderDecorator('systemConfigApiService', …)` or
 *   by assigning to `this.systemConfigApiService.getConfig`, because `sw-system-config` no longer merges its cards,
 * - the Admin API route, also inside template literals.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');
const { stringValue } = require('./upgrade-helpers/component-tags');
const data = require('./no-system-config-config.data');

const FUNCTION_TYPES = ['FunctionExpression', 'ArrowFunctionExpression'];

function propertyKey(node) {
    return node.computed ? stringValue(node.property) : node.property.name;
}

module.exports = createTodoRule({
    ...data,
    visitors: (context, api) => {
        const { service, method } = data.decorator;
        const decoratedServices = [];
        const receivers = [`this.${service}`, `Service(${service})`, service];

        const checkRoute = (node, text) => {
            const route = data.routes.find((entry) => entry.pattern.test(text));

            if (route) {
                api.todo(node, route.message);
            }
        };

        const isDecoratedService = (node) =>
            node.type === 'Identifier' &&
            decoratedServices.some(({ name, fn }) => name === node.name && fn.range[0] <= node.range[0] && node.range[1] <= fn.range[1]);

        return {
            CallExpression(node) {
                const callee = api.memberPath(node.callee);
                const decorator = node.arguments[1];

                if (
                    !callee ||
                    !/(^|\.)addServiceProviderDecorator$/.test(callee) ||
                    stringValue(node.arguments[0]) !== service ||
                    !decorator ||
                    !FUNCTION_TYPES.includes(decorator.type) ||
                    decorator.params[0]?.type !== 'Identifier'
                ) {
                    return;
                }

                decoratedServices.push({ name: decorator.params[0].name, fn: decorator });
            },
            AssignmentExpression(node) {
                const target = node.left;

                if (target.type !== 'MemberExpression' || propertyKey(target) !== method) {
                    return;
                }

                if (isDecoratedService(target.object) || receivers.includes(api.memberPath(target.object))) {
                    api.todo(node, data.decorator.message);
                }
            },
            Literal(node) {
                if (typeof node.value === 'string' && node.parent.type !== 'ImportDeclaration') {
                    checkRoute(node, node.value);
                }
            },
            TemplateLiteral(node) {
                checkRoute(node, node.quasis.map((quasi) => quasi.value.cooked).join('*'));
            },
        };
    },
});
