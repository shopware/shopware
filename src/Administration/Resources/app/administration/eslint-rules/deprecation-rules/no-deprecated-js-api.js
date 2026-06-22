/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { filterMigrations } = require('./registry/filter-migrations');

function formatReferences(migration) {
    if (!migration.references?.length) {
        return '';
    }

    return migration.references.map((reference) => `${reference.type}: ${reference.target}`).join('\n');
}

function buildMessage(migration, usage) {
    const references = formatReferences(migration);
    const replacement = usage.to ? ` Use ${usage.to} instead.` : '';

    return [
        `The "${usage.from}" API is deprecated.${replacement}`,
        usage.message ?? '',
        '',
        migration.description,
        `Removed in Shopware ${migration.removedIn}.`,
        references ? `References:\n${references}` : '',
    ]
        .filter(Boolean)
        .join('\n');
}

function getPropertyName(node) {
    if (!node) {
        return null;
    }

    if (node.type === 'Identifier') {
        return node.name;
    }

    if (node.type === 'Literal' && typeof node.value === 'string') {
        return node.value;
    }

    return null;
}

function getMemberPropertyName(node) {
    if (node?.type !== 'MemberExpression') {
        return null;
    }

    return getPropertyName(node.property);
}

function getMemberName(node) {
    if (!node) {
        return null;
    }

    if (node.type === 'Identifier') {
        return node.name;
    }

    if (node.type === 'ThisExpression') {
        return 'this';
    }

    if (node.type !== 'MemberExpression') {
        return null;
    }

    const objectName = getMemberName(node.object);
    const propertyName = getPropertyName(node.property);

    if (!objectName || !propertyName) {
        return null;
    }

    return `${objectName}.${propertyName}`;
}

function getObjectOptionReplacement(sourceCode, node) {
    const valueText = sourceCode.getText(node.value);

    if (node.method || node.value.type !== 'ObjectExpression') {
        return null;
    }

    return `metaInfo() { return ${valueText}; }`;
}

function normalizeSource(text) {
    return text.replace(/\s+/g, ' ').trim();
}

function normalizeComparableSource(text) {
    return normalizeSource(text).replace(/"/g, "'");
}

function usageFixesAutomatically(usage) {
    return usage.fix !== 'manual';
}

function matchesMemberCall(usage, propertyName, calleeName) {
    if (usage.from.includes('.')) {
        return usage.from === calleeName;
    }

    return usage.from === propertyName;
}

module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'No usage of deprecated Administration JavaScript APIs',
            recommended: true,
        },
        fixable: 'code',
        schema: [
            {
                enum: [
                    'enableFix',
                    'disableFix',
                ],
            },
        ],
    },

    create(context) {
        const registry = loadRegistry();
        const usages = filterMigrations(registry.jsApiMigrations).flatMap((migration) => {
            return migration.usage.map((usage) => {
                return {
                    migration,
                    usage,
                };
            });
        });
        const packageUsages = filterMigrations(registry.packageMigrations).flatMap((migration) => {
            return migration.usage.map((usage) => {
                return {
                    migration,
                    usage,
                };
            });
        });

        const objectOptionUsages = usages.filter(({ usage }) => usage.kind === 'replace-object-option');
        const memberCallUsages = usages.filter(({ usage }) => usage.kind === 'member-call');
        const replaceApiUsages = usages.filter(({ usage }) => usage.kind === 'replace-api');
        const packageImportUsages = packageUsages.filter(({ usage }) => usage.kind === 'rename-package');

        return {
            ImportDeclaration(node) {
                const match = packageImportUsages.find(({ usage }) => usage.from === node.source.value);

                if (!match) {
                    return;
                }

                context.report({
                    node: node.source,
                    message: buildMessage(match.migration, match.usage),
                    fix(fixer) {
                        if (context.options.includes('disableFix')) return null;

                        return fixer.replaceText(node.source, `'${match.usage.to}'`);
                    },
                });
            },

            Property(node) {
                const propertyName = getPropertyName(node.key);
                const match = objectOptionUsages.find(({ usage }) => usage.from === propertyName);

                if (!match || node.value.type !== 'ObjectExpression') {
                    return;
                }

                context.report({
                    node,
                    message: buildMessage(match.migration, match.usage),
                    fix(fixer) {
                        if (context.options.includes('disableFix')) return null;

                        const replacement = getObjectOptionReplacement(context.sourceCode, node);

                        if (!replacement) {
                            return null;
                        }

                        return fixer.replaceText(node, replacement);
                    },
                });
            },

            CallExpression(node) {
                const propertyName = getMemberPropertyName(node.callee);
                const calleeName = getMemberName(node.callee);
                const match = memberCallUsages.find(({ usage }) => {
                    return matchesMemberCall(usage, propertyName, calleeName);
                });

                if (match) {
                    context.report({
                        node: node.callee.property,
                        message: buildMessage(match.migration, match.usage),
                    });
                }

                const sourceText = context.sourceCode.getText(node);
                const replaceApiMatch = replaceApiUsages.find(({ usage }) => {
                    return usage.from.includes('(')
                        ? normalizeComparableSource(usage.from) === normalizeComparableSource(sourceText)
                        : usage.from === calleeName;
                });

                if (!replaceApiMatch) {
                    return;
                }

                context.report({
                    node: replaceApiMatch.usage.from.includes('(') ? node : node.callee,
                    message: buildMessage(replaceApiMatch.migration, replaceApiMatch.usage),
                    fix(fixer) {
                        if (
                            context.options.includes('disableFix') ||
                            !usageFixesAutomatically(replaceApiMatch.usage)
                        ) {
                            return null;
                        }

                        return fixer.replaceText(
                            replaceApiMatch.usage.from.includes('(') ? node : node.callee,
                            replaceApiMatch.usage.to,
                        );
                    },
                });
            },
        };
    },
};
