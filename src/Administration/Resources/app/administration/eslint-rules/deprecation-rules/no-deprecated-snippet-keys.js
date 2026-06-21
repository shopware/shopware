const { loadRegistry } = require('./registry/load-registry');
const { filterMigrations } = require('./registry/filter-migrations');

function formatReferences(migration) {
    if (!migration.references?.length) {
        return '';
    }

    return migration.references.map((reference) => `${reference.type}: ${reference.target}`).join('\n');
}

function buildMessage(migration, usage, snippetKey) {
    const references = formatReferences(migration);

    return [
        `The Administration snippet key "${snippetKey}" uses removed key "${usage.from}".`,
        '',
        migration.description,
        `Removed in Shopware ${migration.removedIn}.`,
        references ? `References:\n${references}` : '',
    ]
        .filter(Boolean)
        .join('\n');
}

function getStringValue(node) {
    if (node.type === 'Literal' && typeof node.value === 'string') {
        return node.value;
    }

    if (node.type === 'TemplateElement') {
        return node.value.cooked ?? node.value.raw;
    }

    return null;
}

function findDeprecatedSnippet(value, usages) {
    return (
        usages.find(({ usage }) => {
            return value === usage.from || value.startsWith(`${usage.from}.`);
        }) ?? null
    );
}

module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description: 'No usage of removed Administration snippet keys',
            recommended: true,
        },
        schema: [],
    },

    create(context) {
        const registry = loadRegistry();
        const usages = filterMigrations(registry.snippetKeyMigrations).flatMap((migration) => {
            return migration.usage.map((usage) => {
                return {
                    migration,
                    usage,
                };
            });
        });

        function checkNode(node) {
            const value = getStringValue(node);

            if (!value) {
                return;
            }

            const match = findDeprecatedSnippet(value, usages);

            if (!match) {
                return;
            }

            context.report({
                node,
                message: buildMessage(match.migration, match.usage, value),
            });
        }

        return {
            Literal: checkNode,
            TemplateElement: checkNode,
        };
    },
};
