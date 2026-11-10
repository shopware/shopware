/**
 * @sw-package framework
 *
 * Upgrade codemod `product-advanced-mode`: 6.8 removes the advanced mode of the product detail page. The factory
 * marks the component, the members and the user config key. The visitors mark the store members, which the factory
 * cannot see because they are not read through `this`: `Shopware.Store.get('swProductDetail').showModeSetting`,
 * `setLoading(['advancedMode', …])` and `loading.advancedMode`.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');
const { stringValue } = require('./upgrade-helpers/component-tags');
const data = require('./no-product-advanced-mode.data');

const MESSAGES = new Map(data.members.map((entry) => [entry.name, entry.message]));
const UNIQUE = new Set(data.members.filter((entry) => entry.unique).map((entry) => entry.name));

/** Whether `node` is `<…>.get('swProductDetail')`, e.g. `Shopware.Store.get('swProductDetail')`. */
function isStoreCall(node) {
    return (
        node.type === 'CallExpression' &&
        node.callee.type === 'MemberExpression' &&
        !node.callee.computed &&
        node.callee.property.name === 'get' &&
        stringValue(node.arguments[0]) === data.store.id
    );
}

function propertyKey(node) {
    return node.computed ? stringValue(node.property) : node.property.name;
}

module.exports = createTodoRule({
    ...data,
    visitors: (context, api) => ({
        MemberExpression(node) {
            const name = propertyKey(node);

            if (!name || node.object.type === 'ThisExpression') {
                return;
            }

            if (data.store.members.includes(name) && (isStoreCall(node.object) || UNIQUE.has(name))) {
                api.todo(node, MESSAGES.get(name));
            }

            if (name === data.store.loadingKey && node.object.type === 'MemberExpression' && propertyKey(node.object) === 'loading') {
                api.todo(node, data.store.loadingMessage);
            }
        },
        CallExpression(node) {
            const [argument] = node.arguments;

            if (
                node.callee.type === 'MemberExpression' &&
                propertyKey(node.callee) === 'setLoading' &&
                argument?.type === 'ArrayExpression' &&
                stringValue(argument.elements[0]) === data.store.loadingKey
            ) {
                api.todo(node, data.store.loadingMessage);
            }
        },
    }),
});
