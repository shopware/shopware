/**
 * @sw-package framework
 *
 * Upgrade codemod `order-unused-components`: 6.8 removes order create and state history components that the core
 * no longer uses. The rule writes a marker comment above their tags and every `Component.override/extend/build` of them.
 * The texts are in `no-unused-order-components.data.js`.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');

module.exports = createTodoRule(require('./no-unused-order-components.data'));
