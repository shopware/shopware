const { loadRegistry } = require('./registry/load-registry');
const { filterMigrations, isMigrationSelected } = require('./registry/filter-migrations');

function formatReferences(migration) {
    if (!migration.references?.length) {
        return '';
    }

    return migration.references.map((reference) => `${reference.type}: ${reference.target}`).join('\n');
}

function appendRegistryContext(message, migration, usage = null) {
    const references = formatReferences(migration);

    return [
        message,
        usage?.message ?? '',
        '',
        migration.description,
        `Removed in Shopware ${migration.removedIn}.`,
        references ? `References:\n${references}` : '',
    ]
        .filter(Boolean)
        .join('\n');
}

function hasObjectVBind(node) {
    return node.startTag.attributes.some((attribute) => {
        return (
            attribute.type === 'VAttribute' &&
            attribute.directive === true &&
            attribute.key?.name?.name === 'bind' &&
            !attribute.key.argument
        );
    });
}

function getCodemodComment(componentName, hasDynamicProps) {
    const vBindWarning = hasDynamicProps
        ? '. Dynamic v-bind props may still contain deprecated API usage and need manual review.'
        : '';

    return `<!-- TODO Codemod: Converted from ${componentName} - please check if everything works correctly${vBindWarning} -->`;
}

function usageFixesAutomatically(usage) {
    return usage?.fix !== 'manual';
}

/**
 * @sw-package framework
 *
 * This rule checks if deprecated components are used and can convert them to the new components.
 * It also adds a comment to the converted component to make it easier to track the changes.
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
    /** @param {RuleContext} context */
    create(context) {
        const registry = loadRegistry();
        const componentMigrations = filterMigrations(registry.componentApiMigrations).filter((migration) => {
            return migration.usage.some((usage) => usage.kind === 'rename-component');
        });
        const defaultActivatedComponents = filterMigrations(registry.componentApiMigrations).map(
            (migration) => migration.component,
        );

        return context.sourceCode.parserServices.defineTemplateBodyVisitor(
            // Event handlers for <template> tags
            {
                VElement(node) {
                    const enableFix = context.options?.[0]?.fix ?? true;
                    const activatedComponents = context.options?.[0]?.activatedComponents ?? defaultActivatedComponents;
                    const migration = componentMigrations.find((candidate) => {
                        return candidate.component === node.name && activatedComponents.includes(candidate.component);
                    });

                    if (migration) {
                        const componentName = migration.component;
                        const newComponentName = migration.replacement;
                        const renameUsage = migration.usage.find((usage) => usage.kind === 'rename-component');

                        // Convert old component to new component
                        context.report({
                            loc: node.loc,
                            message: appendRegistryContext(
                                `"${componentName}" is deprecated. Please use "${newComponentName}" instead.`,
                                migration,
                                renameUsage,
                            ),
                            *fix(fixer) {
                                if (!enableFix || !usageFixesAutomatically(renameUsage)) return;

                                const isSelfClosing = node.startTag.selfClosing;
                                const codemodComment = getCodemodComment(componentName, hasObjectVBind(node));

                                // Handle self-closing tags
                                if (isSelfClosing) {
                                    // Replace the component name
                                    const startTagRange = [
                                        node.startTag.range[0],
                                        componentName.length + node.startTag.range[0] + 1,
                                    ];
                                    yield fixer.replaceTextRange(startTagRange, `<${newComponentName}`);

                                    // Save indentation of the old component
                                    const indentation = node.loc.start.column;

                                    // Add comment to the converted component
                                    yield fixer.insertTextBeforeRange(
                                        startTagRange,
                                        `${codemodComment}\n${' '.repeat(indentation)}`,
                                    );

                                    return;
                                }

                                // Handle non-self-closing tags
                                const startTagRange = [
                                    node.startTag.range[0],
                                    componentName.length + node.startTag.range[0] + 1,
                                ];
                                const endTagRange = node.endTag.range;

                                // Replace the component name
                                yield fixer.replaceTextRange(startTagRange, `<${newComponentName}`);
                                yield fixer.replaceTextRange(endTagRange, `</${newComponentName}>`);

                                // Save indentation of the old component
                                const indentation = node.loc.start.column;

                                // Add comment to the converted component
                                yield fixer.insertTextBeforeRange(
                                    startTagRange,
                                    `${codemodComment}\n${' '.repeat(indentation)}`,
                                );
                            },
                        });
                    }

                    // Handle special sw-data-grid component
                    const swDatagridName = 'sw-data-grid';
                    if (node.name === swDatagridName && activatedComponents.includes(swDatagridName)) {
                        const dataGridMigration = registry.getComponentApiMigration(swDatagridName);

                        if (dataGridMigration && !isMigrationSelected(dataGridMigration)) {
                            return;
                        }

                        // Check if comment a line before the sw-data-grid component exists
                        const commentBeforeNode = context.getSourceCode().getText().split('\n')[node.loc.start.line - 2];

                        // Do not add comment if it already exists
                        if (
                            commentBeforeNode.includes(
                                '<!-- TODO Codemod: This component need to be manually replaced with mt-data-table -->',
                            )
                        ) {
                            return;
                        }
                        const replacementUsage = dataGridMigration?.usage.find((usage) => {
                            return usage.kind === 'manual-component-replacement';
                        });

                        // Add comment a line before the sw-data-grid component
                        context.report({
                            loc: node.loc,
                            message: dataGridMigration
                                ? appendRegistryContext(
                                      `"${swDatagridName}" is deprecated. Please use "mt-data-table" instead.`,
                                      dataGridMigration,
                                      replacementUsage,
                                  )
                                : `"${swDatagridName}" is deprecated. Please use "mt-data-table" instead.`,
                            *fix(fixer) {
                                if (!enableFix || !usageFixesAutomatically(replacementUsage)) return;

                                // Get the range of the start tag
                                const startTagRange = [
                                    node.startTag.range[0],
                                    swDatagridName.length + node.startTag.range[0] + 1,
                                ];

                                // Save indentation of the old component
                                const indentation = node.loc.start.column;

                                // Add comment to the converted component
                                yield fixer.insertTextBeforeRange(
                                    startTagRange,
                                    `<!-- TODO Codemod: This component need to be manually replaced with mt-data-table -->\n${' '.repeat(indentation)}`,
                                );
                            },
                        });
                    }
                },
            },
        );
    },
};
