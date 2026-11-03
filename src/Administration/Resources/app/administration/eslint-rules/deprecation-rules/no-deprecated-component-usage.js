/**
 * @sw-package framework
 */

const { loadRegistry } = require('./registry/load-registry');
const { filterMigrations } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');
const { FIX_OPTION_SCHEMA, canAutoFix } = require('./registry/fix-policy');
const { hasCodemodComment, insertTemplateCodemodComment, isConvertedFrom } = require('./registry/codemod-comment');
const ast = require('./registry/template-ast');

/**
 * Names what an attribute passes to the element, in the shape registry steps declare as `produces`.
 *
 * @example
 * describeAttribute(attribute); // { kind: 'prop', name: 'label' } for :label="x", { kind: 'model', name: 'v-model' } for v-model="x"
 */
function describeAttribute(attribute) {
    const staticName = ast.getStaticAttributeName(attribute);
    const directive = ast.getDirectiveName(attribute);
    const argumentName = ast.getDirectiveArgumentName(attribute);

    if (staticName) {
        return { kind: 'prop', name: staticName };
    }

    if (directive === 'bind' && argumentName) {
        return { kind: 'prop', name: argumentName };
    }

    if (directive === 'on' && argumentName) {
        return { kind: 'event', name: argumentName };
    }

    if (directive === 'model') {
        return { kind: 'model', name: argumentName ? `v-model:${argumentName}` : 'v-model' };
    }

    return null;
}

/**
 * Compares attribute names of one kind; `v-model:modelValue` is the plain `v-model`.
 *
 * @example
 * isSameAttributeName('model', 'v-model', 'v-model:model-value'); // true
 */
function isSameAttributeName(kind, left, right) {
    const toComparable = (name) => {
        const comparable = ast.toComparableName(name);

        return kind === 'model' && comparable === 'vmodel:modelvalue' ? 'vmodel' : comparable;
    };

    return toComparable(left) === toComparable(right);
}

/**
 * Tells whether the element already has the attribute a fix would write, apart from the attribute the
 * fix replaces. Vue rejects duplicate attributes, so such a fix is dropped and only the report stays.
 *
 * @example
 * // <mt-text-field value="old" model-value="new" />, renaming value → model-value
 * producesDuplicateAttribute(node, { node: valueAttribute, produces: { kind: 'prop', name: 'model-value' } }); // true
 */
function producesDuplicateAttribute(element, descriptor) {
    const { produces } = descriptor;

    if (!produces) {
        return false;
    }

    return element.startTag.attributes.some((attribute) => {
        const isReplacedAttribute =
            attribute === descriptor.node || attribute.key === descriptor.node || attribute.key?.argument === descriptor.node;
        const description = describeAttribute(attribute);

        return (
            !isReplacedAttribute &&
            description?.kind === produces.kind &&
            isSameAttributeName(produces.kind, description.name, produces.name)
        );
    });
}

/**
 * Builds the API a registry step's `eslint.report(api)` receives for one matching element: the element,
 * the step, reporting with the duplicate-attribute guard, the fix policy and the template helpers.
 * The TypeScript side of this contract is `ComponentUsageRuleApi` in the registry helpers.
 *
 * @example
 * usage.eslint.report(createComponentUsageRuleApi(context, node, migration, usage));
 */
function createComponentUsageRuleApi(context, node, migration, usage) {
    const sourceCode = context.sourceCode;

    return {
        node,
        migration,
        usage,
        sourceCode,
        canAutoFix(step = usage) {
            return canAutoFix(context, step);
        },
        buildMessage(text) {
            return appendRegistryContext([text], migration);
        },
        report({ produces, ...descriptor }) {
            context.report({
                ...descriptor,
                fix:
                    descriptor.fix &&
                    ((fixer) => {
                        return producesDuplicateAttribute(node, { node: descriptor.node, produces })
                            ? null
                            : descriptor.fix(fixer);
                    }),
            });
        },
        insertCodemodCommentBefore: insertTemplateCodemodComment,
        ast: {
            findMatchingPropAttribute: ast.findMatchingPropAttribute,
            hasMatchingPropAttribute: ast.hasMatchingPropAttribute,
            findMatchingEventAttribute: ast.findMatchingEventAttribute,
            findMatchingVModelAttribute: ast.findMatchingVModelAttribute,
            findObjectVBindAttribute: ast.findObjectVBindAttribute,
            findObjectVOnAttribute: ast.findObjectVOnAttribute,
            findSlot: ast.findSlot,
            getCondensedTextContent: ast.getCondensedTextContent,
            getDirectiveName: ast.getDirectiveName,
            getDirectiveArgumentName: ast.getDirectiveArgumentName,
            getFirstElementChildWithoutSlot: ast.getFirstElementChildWithoutSlot,
            getStaticAttributeName: ast.getStaticAttributeName,
            getAttributeValueSource: (attribute) => ast.getAttributeValueSource(sourceCode, attribute),
            hasCodemodComment: (element, text) => hasCodemodComment(sourceCode, element, text),
        },
    };
}

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

        return context.sourceCode.parserServices.defineTemplateBodyVisitor({
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
