/**
 * @sw-package framework
 *
 * Upgrade codemod `settings-product-promotion-members`: 6.8 removes members of the settings, product and promotion
 * modules, most of them without replacement. The rule writes a marker comment above them in overrides of their component, and
 * anywhere for distinctive names. The list is in `no-removed-module-members.data.js`.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');

module.exports = createTodoRule(require('./no-removed-module-members.data'));
