/**
 * @sw-package framework
 *
 * Upgrade codemod `order-promotion-toggle`: 6.8 removes the automatic promotion toggle of the order detail page.
 * The rule writes a marker comment above the members, the service call and the route of the data table. The texts are
 * in `no-order-promotion-toggle.data.js`.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');

module.exports = createTodoRule(require('./no-order-promotion-toggle.data'));
