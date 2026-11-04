/**
 * @sw-package framework
 */

const { defineTemplateBodyVisitor } = require('./registry/template-ast');
const { loadRegistry } = require('./registry/load-registry');
const { filterMigrations } = require('./registry/filter-migrations');
const { FIX_OPTION_SCHEMA } = require('./registry/fix-policy');
const { isConvertedFrom } = require('./registry/codemod-comment');
const { createComponentUsageRuleApi } = require('./registry/component-usage-api');

/**
 * This rule runs the follow-up steps of registry component migrations on the replacement element:
 * renamed or removed props, events, slots and v-model arguments the conversion leaves behind.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = {
    meta: {
        type: 'problem',
        fixable: 'code',
        docs: {
            description: 'No usage of deprecated component props, events and slots',
            recommended: true,
        },
        schema: FIX_OPTION_SCHEMA,
    },

    create(context) {
        const migrations = filterMigrations(loadRegistry().componentApiMigrations);

        return defineTemplateBodyVisitor(context, {
            VElement(node) {
                migrations
                    .filter((migration) => (migration.replacement ?? migration.component) === node.name)
                    .forEach((migration) => {
                        migration.usage.forEach((usage) => {
                            if (!usage.eslint?.report) {
                                return;
                            }

                            if (usage.onlyAfterConversion && !isConvertedFrom(context.sourceCode, node, migration.component)) {
                                return;
                            }

                            usage.eslint.report(createComponentUsageRuleApi(context, node, migration, usage));
                        });
                    });
            },
        });
    },
};
