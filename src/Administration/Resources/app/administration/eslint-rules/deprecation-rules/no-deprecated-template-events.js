/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { filterMigrations } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');

function buildMessage(migration, usage, additionalMessage = '') {
    return appendRegistryContext(
        [`[${usage.component}] The "${usage.from}" event is deprecated. Use "${usage.to}" instead.`, additionalMessage],
        migration,
    );
}

function getEventName(attribute) {
    if (attribute.key?.name?.name !== 'on') {
        return null;
    }

    return attribute.key?.argument?.name ?? null;
}

function isDynamicEvent(attribute) {
    return attribute.key?.argument?.type !== 'VIdentifier';
}

function hasObjectVOn(node) {
    return node.startTag.attributes.some((attribute) => {
        return attribute.key?.name?.name === 'on' && !attribute.key?.argument;
    });
}

module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'No usage of deprecated Administration template event names',
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
        const migrations = filterMigrations(registry.templateEventMigrations).flatMap((migration) => {
            return migration.usage.map((usage) => {
                return {
                    migration,
                    usage,
                };
            });
        });

        return context.sourceCode.parserServices.defineTemplateBodyVisitor({
            VElement(node) {
                const matchingUsages = migrations.filter(({ usage }) => usage.component === node.name);

                if (!matchingUsages.length) {
                    return;
                }

                node.startTag.attributes.forEach((attribute) => {
                    const eventName = getEventName(attribute);

                    if (!eventName || isDynamicEvent(attribute)) {
                        return;
                    }

                    const match = matchingUsages.find(({ usage }) => usage.from === eventName);

                    if (!match) {
                        return;
                    }

                    const objectVOnMessage = hasObjectVOn(node)
                        ? `Object v-on can hide the replacement event. Review the bound listener object and rename "${match.usage.from}" to "${match.usage.to}" manually if needed.`
                        : '';

                    context.report({
                        node: attribute,
                        message: buildMessage(match.migration, match.usage, objectVOnMessage),
                        *fix(fixer) {
                            if (context.options.includes('disableFix')) return;
                            if (objectVOnMessage) return;

                            yield fixer.replaceText(attribute.key.argument, match.usage.to);
                        },
                    });
                });
            },
        });
    },
};
