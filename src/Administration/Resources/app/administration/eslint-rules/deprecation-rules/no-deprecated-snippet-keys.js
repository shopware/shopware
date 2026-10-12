/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { filterMigrations } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');

function buildMessage(migration, usage, snippetKey) {
    return appendRegistryContext(
        [`The Administration snippet key "${snippetKey}" uses removed key "${usage.from}".`],
        migration,
    );
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

        const scriptVisitors = {
            Literal: checkNode,
            TemplateElement: checkNode,
        };
        // Plain .ts files run without vue-eslint-parser, so only templates get the template visitor.
        const defineTemplateBodyVisitor = context.sourceCode.parserServices?.defineTemplateBodyVisitor;

        if (!defineTemplateBodyVisitor) {
            return scriptVisitors;
        }

        return defineTemplateBodyVisitor(
            {
                Literal: checkNode,
                TemplateElement: checkNode,
            },
            scriptVisitors,
        );
    },
};
