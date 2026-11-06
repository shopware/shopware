/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { selectedUsages } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');
const { FIX_OPTION_SCHEMA, canAutoFix } = require('./registry/fix-policy');
const { insertTemplateCodemodComment } = require('./registry/codemod-comment');
const {
    areTwigAlternatives,
    defineTemplateBodyVisitor,
    findMatchingEventAttribute,
    findObjectVOnAttribute,
    getAttributeRemovalRange,
    getDirectiveName,
    hasSameValue,
    matchesTemplateName,
} = require('./registry/template-ast');
const { withRunLevel } = require('./registry/run-level');

/**
 * Builds the report message; empty hints are skipped by appendRegistryContext.
 *
 * @example
 * buildMessage(migration, usage, [usage.message, objectVOnHint]);
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
 * Returns the event a listener with a static argument listens to, or null for any other attribute.
 * The raw name keeps the registry's spelling for the report.
 *
 * @example
 * getStaticEventName(attribute); // 'update:modelValue' for @update:modelValue="x", null for @[name]="x"
 */
function getStaticEventName(attribute) {
    const argument = attribute.key?.argument;

    if (getDirectiveName(attribute) !== 'on' || argument?.type !== 'VIdentifier') {
        return null;
    }

    return argument.rawName ?? argument.name;
}

/**
 * This rule renames listeners of deprecated component events, as listed in the registry's template
 * event migrations.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = withRunLevel({
    meta: {
        type: 'suggestion',
        docs: {
            description: 'No usage of deprecated Administration template event names',
            recommended: true,
        },
        fixable: 'code',
        schema: FIX_OPTION_SCHEMA,
    },

    create(context) {
        const usages = selectedUsages(loadRegistry().templateEventMigrations);

        /**
         * Reports a deprecated listener next to a listener for its replacement event. The component no
         * longer emits the deprecated event, so the listener is dead: it is removed when both run the same
         * handler, otherwise its handler has to be merged by hand.
         */
        function reportExistingListener(attribute, existingListener, migration, usage) {
            const sameHandler = hasSameValue(context.sourceCode, attribute, existingListener);
            const hint = sameHandler
                ? `The element already listens to "${usage.to}" with the same handler, so this listener is removed.`
                : `The element already listens to "${usage.to}" with another handler. This listener does not fire anymore: merge its handler manually.`;

            context.report({
                node: attribute,
                message: appendRegistryContext(
                    [`[${usage.component}] The "${usage.from}" event is deprecated.`, hint],
                    migration,
                ),
                fix: sameHandler && canAutoFix(context, usage) ? (fixer) => fixer.removeRange(getAttributeRemovalRange(context.sourceCode, attribute.parent.parent, attribute)) : null,
            });
        }

        return defineTemplateBodyVisitor(context, {
            VElement(node) {
                const componentUsages = usages.filter(({ usage }) => usage.component === node.name);

                if (!componentUsages.length) {
                    return;
                }

                node.startTag.attributes.forEach((attribute) => {
                    const eventName = getStaticEventName(attribute);
                    const match = eventName && componentUsages.find(({ usage }) => matchesTemplateName(usage.from, eventName));

                    if (!match) {
                        return;
                    }

                    const { migration, usage } = match;
                    const objectVOnHint = findObjectVOnAttribute(node)
                        ? `Object v-on can hide the replacement event. Review the bound listener object and rename "${usage.from}" to "${usage.to}" manually if needed.`
                        : '';
                    const existingListener = findMatchingEventAttribute(node, usage.to);
                    const listensTwice = existingListener && !areTwigAlternatives(context.sourceCode, attribute, existingListener);

                    if (listensTwice) {
                        reportExistingListener(attribute, existingListener, migration, usage);

                        return;
                    }

                    context.report({
                        node: attribute,
                        message: buildMessage(migration, usage, [
                            usage.message,
                            objectVOnHint,
                        ]),
                        *fix(fixer) {
                            if (!canAutoFix(context, usage) || objectVOnHint) {
                                return;
                            }

                            yield fixer.replaceText(attribute.key.argument, usage.to);

                            if (usage.fix === 'unsafe-auto') {
                                yield insertTemplateCodemodComment(
                                    fixer,
                                    context.sourceCode,
                                    node,
                                    usage.message ?? 'Verify this automatic event rename.',
                                );
                            }
                        },
                    });
                });
            },
        });
    },
});
