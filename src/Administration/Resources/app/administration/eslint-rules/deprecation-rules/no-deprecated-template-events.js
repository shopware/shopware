/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { filterMigrations } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');

/**
 * Builds the report message; empty hints are skipped by appendRegistryContext.
 *
 * @example
 * buildMessage(migration, usage, [usage.message, objectVOnMessage]);
 */
function buildMessage(migration, usage, hints = []) {
    return appendRegistryContext(
        [
            `[${usage.component}] The "${usage.from}" event is deprecated. Use "${usage.to}" instead.`,
            ...hints,
        ],
        migration,
    );
}

/**
 * Tells whether the usage may be rewritten by `--fix`; 'manual' usages are only reported.
 *
 * @example
 * usageFixesAutomatically({ fix: 'unsafe-auto' }); // true
 */
function usageFixesAutomatically(usage) {
    return usage.fix !== 'manual';
}

/**
 * Tells whether the element already listens to the replacement event; renaming would then produce a
 * duplicate attribute, which the Vue template compiler rejects.
 *
 * @example
 * hasReplacementListener(node, 'update:value'); // true for <sw-text-field @input="a" @update:value="b" />
 */
function hasReplacementListener(node, eventName) {
    const normalizedEventName = normalizeEventName(eventName);

    return node.startTag.attributes.some((attribute) => {
        const argument = attribute.key?.argument;
        const listenedName = argument?.rawName ?? argument?.name;

        return (
            attribute.key?.name?.name === 'on' &&
            typeof listenedName === 'string' &&
            normalizeEventName(listenedName) === normalizedEventName
        );
    });
}

/**
 * vue-eslint-parser lowercases v-on argument names; registry names may be camelCase or kebab-case.
 *
 * @example
 * normalizeEventName('update:modelValue') === normalizeEventName('update:model-value'); // true
 */
function normalizeEventName(name) {
    return name.replace(/-/g, '').toLowerCase();
}

function getEventName(attribute) {
    if (attribute.key?.name?.name !== 'on') {
        return null;
    }

    return attribute.key?.argument?.rawName ?? attribute.key?.argument?.name ?? null;
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

                    const match = matchingUsages.find(
                        ({ usage }) => normalizeEventName(usage.from) === normalizeEventName(eventName),
                    );

                    if (!match) {
                        return;
                    }

                    const objectVOnMessage = hasObjectVOn(node)
                        ? `Object v-on can hide the replacement event. Review the bound listener object and rename "${match.usage.from}" to "${match.usage.to}" manually if needed.`
                        : '';

                    context.report({
                        node: attribute,
                        message: buildMessage(match.migration, match.usage, [
                            match.usage.message,
                            objectVOnMessage,
                        ]),
                        *fix(fixer) {
                            if (context.options.includes('disableFix')) return;
                            if (!usageFixesAutomatically(match.usage)) return;
                            if (objectVOnMessage) return;
                            if (hasReplacementListener(node, match.usage.to)) return;

                            yield fixer.replaceText(attribute.key.argument, match.usage.to);

                            if (match.usage.fix === 'unsafe-auto') {
                                const reviewHint = match.usage.message ?? 'Verify this automatic event rename.';

                                yield fixer.insertTextBefore(
                                    node,
                                    `<!-- TODO Codemod: ${reviewHint} -->\n${' '.repeat(node.loc.start.column)}`,
                                );
                            }
                        },
                    });
                });
            },
        });
    },
};
