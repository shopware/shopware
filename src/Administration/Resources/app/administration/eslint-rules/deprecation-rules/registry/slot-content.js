/**
 * @sw-package framework
 */

// Elements that only wrap text and can be unwrapped when their text moves into a prop
const TEXT_WRAPPERS = ['p', 'span', 'div'];

const MUSTACHE_PATTERN = /{{([\s\S]*?)}}/;

/**
 * Tells whether text contains Twig tags. vue-eslint-parser drops them from text nodes, so they are only
 * visible in the source text.
 *
 * @example
 * containsTwig('{% block sw_foo %}'); // true
 */
function containsTwig(text) {
    return /{%|%}|{#|#}/.test(text);
}

/**
 * Returns the source between an element's start and end tag.
 *
 * @example
 * getInnerSource(sourceCode, slot); // ' My label ' for <template #label> My label </template>
 */
function getInnerSource(sourceCode, element) {
    return sourceCode.text.slice(element.startTag.range[1], element.endTag?.range[0] ?? element.startTag.range[1]);
}

/**
 * Tells whether a node is text without content.
 *
 * @example
 * isWhitespaceText(node); // true for the line break between two elements
 */
function isWhitespaceText(node) {
    return node.type === 'VText' && node.value.trim() === '';
}

function encodeAttributeValue(text) {
    return text.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
}

function toTemplateLiteralText(text) {
    return text.replace(/\\/g, '\\\\').replace(/`/g, '\\`').replace(/\$\{/g, '\\${');
}

/**
 * Writes a prop value as attribute text: a static value as plain attribute, an expression as bound
 * attribute. Returns null for an expression with both quote styles, which no attribute can hold.
 *
 * @example
 * toPropAttribute('label', { kind: 'static', text: 'Name' }); // 'label="Name"'
 * toPropAttribute('label', { kind: 'expr', code: "$t('a')" }); // ':label="$t(\'a\')"'
 */
function toPropAttribute(name, value) {
    if (value.kind === 'static') {
        return `${name}="${encodeAttributeValue(value.text)}"`;
    }

    if (!value.code.includes('"')) {
        return `:${name}="${value.code}"`;
    }

    if (!value.code.includes("'")) {
        return `:${name}='${value.code}'`;
    }

    return null;
}

/**
 * Converts slot content into the value of a prop: plain text becomes `{ kind: 'static', text }`, a single
 * mustache `{ kind: 'expr', code }`, mixed text and mustaches a template literal; empty content is
 * `{ kind: 'none' }`. Returns null for markup or Twig, which only a developer can turn into a prop.
 *
 * @example
 * slotContentToValue(sourceCode, slot.children); // { kind: 'expr', code: "$t('sw-foo.label')" } for {{ $t('sw-foo.label') }}
 */
function slotContentToValue(sourceCode, nodes) {
    const meaningfulNodes = nodes.filter((node) => !isWhitespaceText(node));

    if (meaningfulNodes.length === 0) {
        return { kind: 'none' };
    }

    // Twig blocks inside the content would get lost when it is moved into the prop
    const firstNode = meaningfulNodes[0];
    const lastNode = meaningfulNodes[meaningfulNodes.length - 1];
    if (containsTwig(sourceCode.text.slice(firstNode.range[0], lastNode.range[1]))) {
        return null;
    }

    if (
        meaningfulNodes.length === 1
        && firstNode.type === 'VElement'
        && TEXT_WRAPPERS.includes(firstNode.name)
        && firstNode.startTag.attributes.length === 0
    ) {
        return slotContentToValue(sourceCode, firstNode.children);
    }

    const parts = [];

    for (const node of nodes) {
        if (node.type === 'VText') {
            // Mustaches are only parsed in .vue files, in .twig files they are part of the text
            node.value.split(MUSTACHE_PATTERN).forEach((segment, index) => {
                parts.push(index % 2 === 0 ? { text: segment.replace(/\s+/g, ' ') } : { code: segment.trim() });
            });
        } else if (node.type === 'VExpressionContainer' && node.expression) {
            parts.push({ code: sourceCode.getText(node.expression) });
        } else {
            return null;
        }
    }

    const firstPart = parts[0];
    const lastPart = parts[parts.length - 1];
    if (firstPart.text !== undefined) {
        firstPart.text = firstPart.text.trimStart();
    }
    if (lastPart.text !== undefined) {
        lastPart.text = lastPart.text.trimEnd();
    }

    const filteredParts = parts.filter((part) => part.text !== '');

    if (filteredParts.every((part) => part.text !== undefined)) {
        return { kind: 'static', text: filteredParts.map((part) => part.text).join('') };
    }

    if (filteredParts.length === 1) {
        return { kind: 'expr', code: filteredParts[0].code };
    }

    const templateLiteral = filteredParts.map((part) => {
        return part.text !== undefined ? toTemplateLiteralText(part.text) : `\${${part.code}}`;
    }).join('');

    return { kind: 'expr', code: `\`${templateLiteral}\`` };
}

/**
 * Range of a child node including the whitespace before it, so removing it doesn't leave an empty line.
 * Text nodes keep their trailing whitespace, which separates them from the next sibling.
 */
function getRemovalRange(sourceText, node) {
    let start = node.range[0];
    let end = node.range[1];

    if (node.type === 'VText') {
        const text = sourceText.slice(start, end);
        start += text.length - text.trimStart().length;
        end -= text.length - text.trimEnd().length;
    }

    while (start > 0 && /\s/.test(sourceText[start - 1])) {
        start -= 1;
    }

    return [start, end];
}

module.exports = {
    containsTwig,
    getInnerSource,
    getRemovalRange,
    isWhitespaceText,
    slotContentToValue,
    toPropAttribute,
};
