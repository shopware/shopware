/**
 * @sw-package framework
 *
 * Upgrade codemod `agentic-commerce-ui`: 6.8 removes the Agentic Commerce Administration UI from the core in favor of
 * the SwagAgenticCommerce extension. The rule writes a marker comment above the removed components, members, the route and
 * `Shopware.Defaults.agenticCommerceTypeId`. The texts are in `no-agentic-commerce-ui.data.js`.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');

module.exports = createTodoRule(require('./no-agentic-commerce-ui.data'));
