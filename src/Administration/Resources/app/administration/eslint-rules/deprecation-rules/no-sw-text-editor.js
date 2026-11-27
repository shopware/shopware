/**
 * @sw-package framework
 *
 * Upgrade codemod `sw-text-editor`: 6.8 removes `sw-text-editor` and its toolbar, button, table and link menu
 * components in favor of `mt-text-editor`. The APIs differ too much for a rewrite, so the rule writes a TODO with the
 * replacement above every tag and every `Component.override/extend` of them. The texts are in
 * `no-sw-text-editor.data.js`.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');
const components = require('./no-sw-text-editor.data');

module.exports = createTodoRule({
    id: 'sw-text-editor',
    description: 'Marks sw-text-editor and its sub-components, which 6.8 replaces with mt-text-editor',
    components,
});
