/**
 * @sw-package framework
 *
 * Upgrade codemod `custom-field-set-loaders`: 6.8 removes the custom-field set loader computeds of eleven components
 * in favor of `customFieldDataProviderService.getCustomFieldSets(entityName)`. The rule writes a marker comment with the entity
 * name of the component. The texts are in `no-custom-field-set-loaders.data.js`.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');

module.exports = createTodoRule(require('./no-custom-field-set-loaders.data'));
