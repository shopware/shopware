/**
 * @sw-package framework
 *
 * Upgrade codemod `order-shipping-costs`: 6.8 no longer edits shipping costs in `sw-order-detail-details`.
 * The rule writes a marker comment above the removed members in overrides of that component. The texts are in
 * `no-order-detail-shipping-costs.data.js`.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');

module.exports = createTodoRule(require('./no-order-detail-shipping-costs.data'));
