/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { selectedUsages } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');
const { FIX_OPTION_SCHEMA, canAutoFix } = require('./registry/fix-policy');
const { insertTemplateCodemodComment } = require('./registry/codemod-comment');
const {
    findMatchingEventAttribute,
    findObjectVOnAttribute,
    getDirectiveName,
    matchesTemplateName,
} = require('./registry/template-ast');

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
module.exports = {
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

        return context.sourceCode.parserServices.defineTemplateBodyVisitor({
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

                    context.report({
                        node: attribute,
                        message: buildMessage(migration, usage, [
                            usage.message,
                            objectVOnHint,
                        ]),
                        *fix(fixer) {
                            // A second listener for the new event would be a duplicate attribute, which Vue rejects.
                            if (!canAutoFix(context, usage) || objectVOnHint || findMatchingEventAttribute(node, usage.to)) {
                                return;
                            }

                            yield fixer.replaceText(attribute.key.argument, usage.to);

                            if (usage.fix === 'unsafe-auto') {
                                yield insertTemplateCodemodComment(
                                    fixer,
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
};
