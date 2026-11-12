/**
 * @sw-package framework
 *
 * Builds an upgrade codemod that only writes TODO comments, from a data table:
 *
 * ```js
 * module.exports = createTodoRule({
 *     id: 'order-unused-components',                      // registry id, part of the TODO tag
 *     description: 'Removed order components',
 *     components: { 'sw-order-user-card': 'sw-order-user-card is removed without replacement.' },
 * });
 * ```
 *
 * `components` match template tags and `Component.override/extend/build` calls with that name or base.
 */

const { reportTodo } = require('./todo-comment');
const { readComponentCall, elementName } = require('./component-tags');

function createTodoRule(spec) {
    const components = new Map(Object.entries(spec.components ?? {}));

    return {
        meta: {
            type: 'problem',
            fixable: 'code',
            docs: { description: spec.description ?? `Marks spots of the ${spec.id} upgrade with a TODO comment.` },
            schema: [],
        },
        create(context) {
            const seen = new Set();
            const todo = (node, message, scope) =>
                reportTodo(context, { id: spec.id, message, range: node.range, scope, seen });

            const scriptVisitors = {
                CallExpression(node) {
                    const call = readComponentCall(node);
                    const name = call?.method === 'extend' ? call.target : call?.name;

                    if (name && components.has(name)) {
                        todo(node, components.get(name), 'script');
                    }
                },
            };

            const parserServices = context.sourceCode.parserServices;

            if (!parserServices?.defineTemplateBodyVisitor) {
                return scriptVisitors;
            }

            return parserServices.defineTemplateBodyVisitor(
                {
                    VElement(node) {
                        const message = components.get(elementName(node));

                        if (message) {
                            todo(node, message, 'template');
                        }
                    },
                },
                scriptVisitors,
            );
        },
    };
}

module.exports = {
    createTodoRule,
};
