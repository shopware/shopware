/**
 * @sw-package framework
 *
 * Upgrade codemod `media-grid-deselect`: 6.8 removes the outside-click deselection of `sw-media-grid`. The rule writes
 * a marker comment above listeners and emits of `media-grid-selection-clear` and above the removed members in overrides of
 * `sw-media-grid`. The texts are in `no-media-grid-outside-click.data.js`.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');

module.exports = createTodoRule(require('./no-media-grid-outside-click.data'));
