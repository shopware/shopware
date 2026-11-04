/**
 * @sw-package framework
 */

const { appendRegistryContext } = require('./format-migration-message');
const { canAutoFix } = require('./fix-policy');
const { getLeadingComments, hasCodemodComment, insertTemplateCodemodComment, isConvertedFrom } = require('./codemod-comment');
const ast = require('./template-ast');
const { containsTwig, getInnerSource, getRemovalRange, slotContentToValue, toPropAttribute } = require('./slot-content');

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
 * Returns the start tag attribute a report points at; reports may point at the attribute, its key or
 * the key's argument.
 */
function findReportedAttribute(element, reportedNode) {
    return (
        element.startTag.attributes.find((attribute) => {
            return attribute === reportedNode || attribute.key === reportedNode || attribute.key?.argument === reportedNode;
        }) ?? null
    );
}

/**
 * Finds another attribute that already passes what a fix would write. Vue rejects duplicate attributes.
 *
 * @example
 * // <mt-text-field value="old" model-value="new" />, renaming value → model-value
 * findProducedAttribute(node, valueAttribute, { kind: 'prop', name: 'model-value' }); // the model-value attribute
 */
function findProducedAttribute(element, replacedAttribute, produces) {
    return (
        element.startTag.attributes.find((attribute) => {
            const description = describeAttribute(attribute);

            return (
                attribute !== replacedAttribute &&
                description?.kind === produces.kind &&
                isSameAttributeName(produces.kind, description.name, produces.name)
            );
        }) ?? null
    );
}

/**
 * Decides how a fix that writes `produces` handles an attribute the element already has: rename as
 * planned when the two are Twig alternatives, remove the deprecated attribute when both pass the same
 * value, otherwise leave both for a manual merge with the reason why.
 *
 * @example
 * resolveDuplicate(context.sourceCode, node, descriptor, produces); // { fix, reason } or null without a duplicate
 */
function resolveDuplicate(sourceCode, element, descriptor, produces) {
    const replacedAttribute = findReportedAttribute(element, descriptor.node);
    const producedAttribute = replacedAttribute && findProducedAttribute(element, replacedAttribute, produces);

    if (!producedAttribute || ast.areTwigAlternatives(sourceCode, replacedAttribute, producedAttribute)) {
        return null;
    }

    if (ast.hasSameValue(sourceCode, replacedAttribute, producedAttribute)) {
        return {
            fix: descriptor.fix && ((fixer) => fixer.removeRange(ast.getAttributeRemovalRange(sourceCode, element, replacedAttribute))),
            reason: `The element already passes "${produces.name}" with the same value, so the deprecated attribute is removed.`,
        };
    }

    return {
        fix: null,
        reason: `Not fixed: the element already passes "${produces.name}" with a different value. Merge them manually.`,
    };
}

/**
 * Builds the API a registry step's `eslint.report(api)` receives for one matching element: the element,
 * the step, reporting with the duplicate-attribute guard, the fix policy and the template helpers.
 * The TypeScript side of this contract is `ComponentUsageRuleApi` in the registry helpers.
 * `assumeConverted` makes `isConverted()` true, for planning the steps of a conversion before it happens.
 *
 * @example
 * usage.eslint.report(createComponentUsageRuleApi(context, node, migration, usage));
 */
function createComponentUsageRuleApi(context, node, migration, usage, { assumeConverted = false } = {}) {
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
            const duplicate = produces ? resolveDuplicate(sourceCode, node, descriptor, produces) : null;

            if (!duplicate) {
                context.report(descriptor);

                return;
            }

            const [headline, ...registryContext] = descriptor.message.split('\n');

            context.report({
                ...descriptor,
                message: [headline, duplicate.reason, ...registryContext].join('\n'),
                fix: duplicate.fix,
            });
        },
        insertCodemodCommentBefore: insertTemplateCodemodComment,
        removeAttribute(fixer, attribute) {
            return fixer.removeRange(ast.getAttributeRemovalRange(sourceCode, node, attribute));
        },
        isConverted() {
            return assumeConverted || isConvertedFrom(sourceCode, node, migration.component);
        },
        ast: {
            findMatchingPropAttribute: ast.findMatchingPropAttribute,
            hasMatchingPropAttribute: ast.hasMatchingPropAttribute,
            findMatchingEventAttribute: ast.findMatchingEventAttribute,
            findMatchingVModelAttribute: ast.findMatchingVModelAttribute,
            findObjectVBindAttribute: ast.findObjectVBindAttribute,
            findObjectVOnAttribute: ast.findObjectVOnAttribute,
            findSlot: ast.findSlot,
            getDirectiveName: ast.getDirectiveName,
            getDirectiveArgumentName: ast.getDirectiveArgumentName,
            getFirstElementChildWithoutSlot: ast.getFirstElementChildWithoutSlot,
            getStaticAttributeName: ast.getStaticAttributeName,
            getAttributeValueSource: (attribute) => ast.getAttributeValueSource(sourceCode, attribute),
            hasCodemodComment: (element, text) => hasCodemodComment(sourceCode, element, text),
            hasLeadingComment: (element, text) => {
                return getLeadingComments(sourceCode, element).some((comment) => comment.value.includes(text));
            },
            getSlotContentValue: (slot) => {
                return containsTwig(getInnerSource(sourceCode, slot)) ? null : slotContentToValue(sourceCode, slot.children);
            },
            getRemovalRange: (node) => getRemovalRange(sourceCode.text, node),
            toPropAttribute,
        },
    };
}

module.exports = {
    createComponentUsageRuleApi,
};
