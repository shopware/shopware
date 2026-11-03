/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { filterMigrations } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');
const { canAutoFix } = require('./registry/fix-policy');
const { convertedFromText, templateCodemodComment } = require('./registry/codemod-comment');
const { findObjectVBindAttribute, findObjectVOnAttribute } = require('./registry/template-ast');

/**
 * Builds the comment above a converted element. Object `v-bind` / `v-on` can still pass deprecated
 * props or listeners, so they get an extra review sentence.
 *
 * @example
 * getConversionComment('sw-icon', node); // the review comment carrying convertedFromText('sw-icon')
 */
function getConversionComment(componentName, node) {
    const vBindWarning = findObjectVBindAttribute(node)
        ? '. Dynamic v-bind props may still contain deprecated API usage and need manual review.'
        : '';
    const vOnWarning = findObjectVOnAttribute(node)
        ? '. Dynamic v-on listeners may still contain deprecated event usage and need manual review.'
        : '';

    return templateCodemodComment(`${convertedFromText(componentName)}${vBindWarning}${vOnWarning}`);
}

/**
 * This rule reports deprecated components and renames them to their replacement. It puts a review
 * comment above every converted element, which `no-deprecated-component-usage` uses to find the
 * conversions its follow-up steps apply to.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = {
    meta: {
        type: 'problem',
        fixable: 'code',
        docs: {
            description: 'No usage of deprecated components',
            recommended: true,
        },
        schema: [
            {
                type: 'object',
                properties: {
                    fix: {
                        type: 'boolean',
                    },
                    activatedComponents: {
                        type: 'array',
                        items: {
                            type: 'string',
                        },
                    },
                },
            },
        ],
    },

    create(context) {
        const migrations = filterMigrations(loadRegistry().componentApiMigrations);
        const activatedComponents =
            context.options[0]?.activatedComponents ?? migrations.map((migration) => migration.component);
        const renameMigrations = migrations
            .filter((migration) => activatedComponents.includes(migration.component))
            .map((migration) => ({
                migration,
                renameUsage: migration.usage.find((usage) => usage.kind === 'rename-component'),
            }))
            .filter(({ renameUsage }) => renameUsage);

        return context.sourceCode.parserServices.defineTemplateBodyVisitor({
            VElement(node) {
                const match = renameMigrations.find(({ migration }) => migration.component === node.name);

                if (!match) {
                    return;
                }

                const { migration, renameUsage } = match;
                const oldName = migration.component;
                const newName = migration.replacement;

                context.report({
                    loc: node.loc,
                    message: appendRegistryContext(
                        [
                            `"${oldName}" is deprecated. Please use "${newName}" instead.`,
                            renameUsage.message ?? '',
                        ],
                        migration,
                    ),
                    *fix(fixer) {
                        if (!canAutoFix(context, renameUsage)) {
                            return;
                        }

                        const nameRange = [
                            node.startTag.range[0],
                            node.startTag.range[0] + 1 + oldName.length,
                        ];

                        yield fixer.insertTextBeforeRange(
                            nameRange,
                            `${getConversionComment(oldName, node)}\n${' '.repeat(node.loc.start.column)}`,
                        );
                        yield fixer.replaceTextRange(nameRange, `<${newName}`);

                        if (node.endTag) {
                            yield fixer.replaceTextRange(node.endTag.range, `</${newName}>`);
                        }
                    },
                });
            },
        });
    },
};
