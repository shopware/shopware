/**
 * @sw-package framework
 *
 * Upgrade codemod `service-calls`: marks calls of `integrationService.updateAdmin()`, `validationService.email()`
 * and `numberRangeService.previewPattern()`, which 6.8 removes. The texts are in `no-removed-service-methods.data.js`.
 */

const { createTodoRule } = require('./upgrade-helpers/create-todo-rule');
const data = require('./no-removed-service-methods.data');

module.exports = createTodoRule({
    ...data,
    visitors: (context, api) => ({
        ImportSpecifier(node) {
            const imported = node.imported.name ?? node.imported.value;
            const entry = data.namedImports.find(
                (candidate) => candidate.source === node.parent.source.value && candidate.name === imported,
            );

            if (entry) {
                api.todo(node.parent, entry.message);
            }
        },
    }),
});
