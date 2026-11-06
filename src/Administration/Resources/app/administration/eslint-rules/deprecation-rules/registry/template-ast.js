/**
 * @sw-package framework
 */

/**
 * Normalizes template names for comparison: vue-eslint-parser lowercases attribute and directive
 * argument names, while the registry writes them in kebab-case or camelCase.
 *
 * @example
 * toComparableName('update:modelValue') === toComparableName('update:model-value'); // true
 */
function toComparableName(name) {
    return name.replace(/-/g, '').toLowerCase();
}

/**
 * Tells whether two template names mean the same prop or event.
 *
 * @example
 * matchesTemplateName('modelvalue', 'model-value'); // true
 */
function matchesTemplateName(actual, expected) {
    return toComparableName(actual) === toComparableName(expected);
}

/**
 * Returns the name of a plain attribute, or null for a directive.
 *
 * @example
 * getStaticAttributeName(labelAttribute); // 'label' for label="x", null for :label="x"
 */
function getStaticAttributeName(attribute) {
    return typeof attribute?.key?.name === 'string' ? attribute.key.name : null;
}

/**
 * Returns the directive of an attribute without its `v-` prefix, or null for a plain attribute.
 *
 * @example
 * getDirectiveName(attribute); // 'bind' for :label="x", 'on' for @click="x", 'model' for v-model="x"
 */
function getDirectiveName(attribute) {
    return attribute?.key?.name?.name ?? null;
}

/**
 * Returns the static argument of a directive, or null without one or for a dynamic argument.
 *
 * @example
 * getDirectiveArgumentName(attribute); // 'label' for :label="x", null for v-bind="x" or :[name]="x"
 */
function getDirectiveArgumentName(attribute) {
    return attribute?.key?.argument?.name ?? null;
}

/**
 * Finds the directive without argument (`v-bind="…"` or `v-on="…"`) on an element. Such an object can
 * hide or override any prop or listener, so fixes on that element fall back to a manual review.
 */
function findObjectDirective(node, directiveName) {
    return (
        node?.startTag?.attributes?.find((attribute) => {
            return attribute.directive === true && getDirectiveName(attribute) === directiveName && !attribute.key.argument;
        }) ?? null
    );
}

/**
 * Finds the object `v-bind="…"` attribute on an element's start tag.
 *
 * @example
 * findObjectVBindAttribute(node); // the VAttribute for <sw-field v-bind="fieldProps" />, null for <sw-field :label="x" />
 */
function findObjectVBindAttribute(node) {
    return findObjectDirective(node, 'bind');
}

/**
 * Finds the object `v-on="…"` attribute on an element's start tag.
 *
 * @example
 * findObjectVOnAttribute(node); // the VAttribute for <sw-field v-on="listeners" />
 */
function findObjectVOnAttribute(node) {
    return findObjectDirective(node, 'on');
}

/**
 * Finds the attribute that passes a prop, statically or bound.
 *
 * @example
 * findMatchingPropAttribute(node, 'is-loading'); // matches isLoading="…", :is-loading="…" and :isLoading="…"
 */
function findMatchingPropAttribute(node, propName) {
    return node.startTag.attributes.find((attribute) => {
        const staticName = getStaticAttributeName(attribute);

        if (staticName) {
            return matchesTemplateName(staticName, propName);
        }

        const argumentName = getDirectiveArgumentName(attribute);

        return getDirectiveName(attribute) === 'bind' && argumentName !== null && matchesTemplateName(argumentName, propName);
    });
}

/**
 * Tells whether the element passes the prop.
 *
 * @example
 * hasMatchingPropAttribute(node, 'label'); // true for <mt-text-field label="x" />
 */
function hasMatchingPropAttribute(node, propName) {
    return Boolean(findMatchingPropAttribute(node, propName));
}

/**
 * Finds the listener for an event.
 *
 * @example
 * findMatchingEventAttribute(node, 'update:value'); // the attribute of @update:value="…"
 */
function findMatchingEventAttribute(node, eventName) {
    return node.startTag.attributes.find((attribute) => {
        const argumentName = getDirectiveArgumentName(attribute);

        return getDirectiveName(attribute) === 'on' && argumentName !== null && matchesTemplateName(argumentName, eventName);
    });
}

/**
 * Finds the `v-model` with the given argument; null finds the plain `v-model`.
 *
 * @example
 * findMatchingVModelAttribute(node, 'value'); // the attribute of v-model:value="…"
 */
function findMatchingVModelAttribute(node, argumentName) {
    return node.startTag.attributes.find((attribute) => {
        return getDirectiveName(attribute) === 'model' && getDirectiveArgumentName(attribute) === argumentName;
    });
}

/**
 * Finds the `<template #slot>` child that fills the named slot.
 *
 * @example
 * findSlot(node, 'label'); // the <template #label> element
 */
function findSlot(node, slotName) {
    return node.children.find((child) => {
        return (
            child.type === 'VElement' &&
            child.name === 'template' &&
            child.startTag?.attributes?.some((attribute) => {
                return getDirectiveName(attribute) === 'slot' && getDirectiveArgumentName(attribute) === slotName;
            })
        );
    });
}

/**
 * Returns the first child element that is not a slot template.
 *
 * @example
 * getFirstElementChildWithoutSlot(node); // <sw-tabs-item> inside <sw-tabs>
 */
function getFirstElementChildWithoutSlot(node) {
    return node.children.find((child) => child.type === 'VElement' && child.name !== 'template');
}

/**
 * Returns the source of an attribute value: the expression of a bound attribute or the text of a
 * static one, null without a value.
 *
 * @example
 * getAttributeValueSource(sourceCode, attribute); // 'isActive' for :active="isActive", 'x' for label="x"
 */
function getAttributeValueSource(sourceCode, attribute) {
    if (!attribute?.value) {
        return null;
    }

    if (getDirectiveName(attribute) === 'bind') {
        return sourceCode.getText(attribute.value.expression ?? attribute.value);
    }

    return attribute.value.value;
}

/**
 * Tells whether two attributes sit in different branches of a Twig `{% if %}`, so only one of them is
 * rendered and both may carry the same name.
 *
 * @example
 * // <mt-text-field {% if VUE2 %}v-model="x"{% else %}v-model:value="x"{% endif %} />
 * areTwigAlternatives(sourceCode, vModel, vModelValue); // true
 */
function areTwigAlternatives(sourceCode, left, right) {
    const [first, second] = left.range[0] < right.range[0] ? [left, right] : [right, left];

    return /{%-?\s*(?:else|elseif)\b/.test(sourceCode.text.slice(first.range[1], second.range[0]));
}

/**
 * Tells whether two attributes pass the same value, so the deprecated one is redundant.
 *
 * @example
 * hasSameValue(sourceCode, attributeOf(':value="name"'), attributeOf(':model-value="name"')); // true
 */
function hasSameValue(sourceCode, left, right) {
    const valueOf = (attribute) => (attribute.value ? sourceCode.getText(attribute.value) : '');

    return (
        (getDirectiveName(left) === null) === (getDirectiveName(right) === null) && valueOf(left) === valueOf(right)
    );
}

/**
 * Returns the range that removes an attribute together with the whitespace separating it from its
 * neighbour in the start tag, so the tag keeps neither a double space nor a whitespace-only line. It
 * takes the whitespace after the attribute instead when the attribute starts its line and the next
 * attribute follows on it, or when it is the first attribute on the tag's line.
 *
 * @example
 * getAttributeRemovalRange(sourceCode, node, attribute);
 * // <mt-banner appearance="notification" variant="info"> → <mt-banner variant="info">
 */
function getAttributeRemovalRange(sourceCode, element, attribute) {
    const text = sourceCode.text;
    const [tagStart, tagEnd] = element.startTag.range;
    const [start, end] = attribute.range;
    const before = text.slice(tagStart, start).match(/\s*$/)[0];
    const after = text.slice(end, tagEnd).match(/^\s*/)[0];
    const isFollowedOnSameLine = after !== '' && !after.includes('\n');
    const sharesLineWithNext = isFollowedOnSameLine && !['/', '>'].includes(text.charAt(end + after.length));
    // A first attribute keeps the space after the tag name, where other fixes insert attributes.
    const isFirst = start - before.length === tagStart + 1 + element.rawName.length;

    if ((before.includes('\n') && sharesLineWithNext) || (isFirst && isFollowedOnSameLine)) {
        return [start, end + after.length];
    }

    return [start - before.length, end];
}

/**
 * Registers template visitors through vue-eslint-parser. A file without a Vue template, such as a
 * `.ts` file a CLI `--rule` reaches, gets only the script visitors instead of a crash.
 *
 * @example
 * return defineTemplateBodyVisitor(context, { VElement(node) { … } });
 */
function defineTemplateBodyVisitor(context, templateVisitor, scriptVisitor = {}) {
    const define = context.sourceCode.parserServices?.defineTemplateBodyVisitor;

    return define ? define(templateVisitor, scriptVisitor) : scriptVisitor;
}

module.exports = {
    getAttributeRemovalRange,
    areTwigAlternatives,
    hasSameValue,
    defineTemplateBodyVisitor,
    findMatchingEventAttribute,
    findMatchingPropAttribute,
    findMatchingVModelAttribute,
    findObjectVBindAttribute,
    findObjectVOnAttribute,
    findSlot,
    getAttributeValueSource,
    getDirectiveArgumentName,
    getDirectiveName,
    getFirstElementChildWithoutSlot,
    getStaticAttributeName,
    hasMatchingPropAttribute,
    matchesTemplateName,
    toComparableName,
};
