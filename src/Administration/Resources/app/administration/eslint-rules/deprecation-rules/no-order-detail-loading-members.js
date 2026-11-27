/**
 * @sw-package framework
 *
 * Upgrade codemod `order-detail-store`: 6.8 moves the loading and editing state of the order detail page into the
 * `swOrderDetail` store and removes the provides, members and events that synced it. The members and events come
 * from the data table. The template hook marks a `<router-view>` with the removed attributes in templates that
 * override `sw-order-detail`, because `loading-change` and the other names are too generic to mark elsewhere.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');
const { elementName } = require('./upgrade-helpers/component-tags');
const { targetsOfTemplate } = require('./upgrade-helpers/twig-target');
const data = require('./no-order-detail-loading-members.data');

const toKebab = (name) => name.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();

/**
 * The attribute as written in the data table: `is-editing` for `:is-editing`, `@loading-change` for listeners.
 */
function attributeKey(attribute) {
    if (!attribute.directive) {
        return toKebab(attribute.key.rawName ?? attribute.key.name);
    }

    const argument = attribute.key.argument;

    if (!argument || argument.type !== 'VIdentifier') {
        return null;
    }

    const name = toKebab(argument.rawName ?? argument.name);

    if (attribute.key.name.name === 'on') {
        return `@${name}`;
    }

    return attribute.key.name.name === 'bind' ? name : null;
}

module.exports = createTodoRule({
    ...data,
    templateVisitors: (context, api) => ({
        VElement(node) {
            if (elementName(node) !== 'router-view') {
                return;
            }

            const targets = targetsOfTemplate(context);

            if (!targets.includes('sw-order-detail')) {
                return;
            }

            if (node.startTag.attributes.some((attribute) => data.routerView.attributes.includes(attributeKey(attribute)))) {
                api.todo(node, data.routerView.message, 'template');
            }
        },
    }),
});
