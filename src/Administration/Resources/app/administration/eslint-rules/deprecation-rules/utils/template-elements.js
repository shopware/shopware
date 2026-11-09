/**
 * @sw-package framework
 *
 * Helpers for the upgrade codemods that rewrite tags and attributes of Administration templates.
 */

/** @param {string} name */
function kebabCase(name) {
    return name.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();
}

/**
 * The prop an attribute sets: `value` for `value="1"`, `:value`, `v-bind:value` and `v-model:value`.
 *
 * @param {import('vue-eslint-parser').AST.VAttribute | import('vue-eslint-parser').AST.VDirective} attribute
 * @returns {{ name: string, directive: string | null } | null}
 */
function attributeProp(attribute) {
    if (!attribute.directive) {
        return { name: kebabCase(attribute.key.rawName ?? attribute.key.name), directive: null };
    }

    const argument = attribute.key.argument;

    if (argument?.type !== 'VIdentifier') {
        return null;
    }

    return { name: kebabCase(argument.rawName ?? argument.name), directive: attribute.key.name.name };
}

/**
 * The attributes of an element that bind one of the props, as plain attribute or with `:`/`v-bind:`.
 *
 * @param {import('vue-eslint-parser').AST.VElement} element
 * @param {string[]} names kebab-case prop names
 */
function propAttributes(element, names) {
    return element.startTag.attributes.filter((attribute) => {
        const prop = attributeProp(attribute);

        return prop !== null && names.includes(prop.name) && [null, 'bind'].includes(prop.directive);
    });
}

/**
 * The range of an attribute including the whitespace in front of it.
 *
 * @param {string} text
 * @param {{ range: [number, number] }} attribute
 * @returns {[number, number]}
 */
function attributeRemovalRange(text, attribute) {
    let start = attribute.range[0];

    while (start > 0 && /\s/.test(text[start - 1])) {
        start -= 1;
    }

    return [start, attribute.range[1]];
}

/**
 * Fixes that rename the start and end tag of an element.
 *
 * @param {import('eslint').Rule.RuleFixer} fixer
 * @param {import('vue-eslint-parser').AST.VElement} element
 * @param {string} name
 */
function renameElement(fixer, element, name) {
    const nameLength = element.rawName.length;
    const fixes = [fixer.replaceTextRange([element.startTag.range[0] + 1, element.startTag.range[0] + 1 + nameLength], name)];

    if (element.endTag) {
        fixes.push(fixer.replaceTextRange([element.endTag.range[0] + 2, element.endTag.range[0] + 2 + nameLength], name));
    }

    return fixes;
}

/**
 * The attribute part of a start tag, e.g. ` v-model:value="x"` of `<sw-select v-model:value="x">`.
 *
 * @param {string} text
 * @param {import('vue-eslint-parser').AST.VElement} element
 */
function startTagAttributesText(text, element) {
    const { startTag } = element;
    const end = startTag.range[1] - (startTag.selfClosing ? 2 : 1);

    return text.slice(startTag.range[0] + 1 + element.rawName.length, end).replace(/\s+$/, '');
}

module.exports = {
    kebabCase,
    propAttributes,
    attributeRemovalRange,
    renameElement,
    startTagAttributesText,
};
