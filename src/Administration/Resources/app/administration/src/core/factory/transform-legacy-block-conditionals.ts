/**
 * @sw-package framework
 */

const SELF_CLOSING_TAG_REG_EXP = /<([A-Za-z][\w:-]*)(?:\s+((?:[^"'<>]|"[^"]*"|'[^']*')*?))?\s*\/>/g;
const CONDITIONAL_REG_EXP = /v-(?:if|else-if|else)\b/;
const SW_BLOCK_PARENT_TAG = 'sw-block-parent';

function escapeSingleQuotedString(value: string): string {
    return value.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

function createLegacyHelperExpression(helperName: string, blockName: string, expression?: string | null): string {
    const escapedBlockName = escapeSingleQuotedString(blockName);

    if (!expression) {
        return `${helperName}('${escapedBlockName}')`;
    }

    return `${helperName}('${escapedBlockName}', ${expression})`;
}

function normalizeSelfClosingTags(template: string): string {
    return template.replace(SELF_CLOSING_TAG_REG_EXP, (match, tagName: string, attributes: string = '') => {
        const trimmedAttributes = attributes.trim();
        const normalizedAttributes = trimmedAttributes.length > 0 ? ` ${trimmedAttributes}` : '';

        return `<${tagName}${normalizedAttributes}></${tagName}>`;
    });
}

function getTrailingConditionalChain(children: Element[]): Element[] {
    const lastElement = children.at(-1);

    if (!lastElement) {
        return [];
    }

    if (lastElement.hasAttribute('v-if')) {
        return [lastElement];
    }

    if (!lastElement.hasAttribute('v-else-if')) {
        return [];
    }

    const conditionalChain = [lastElement];

    for (let index = children.length - 2; index >= 0; index -= 1) {
        const child = children[index];

        if (child.hasAttribute('v-else-if')) {
            conditionalChain.unshift(child);
            continue;
        }

        if (child.hasAttribute('v-if')) {
            conditionalChain.unshift(child);

            return conditionalChain;
        }

        return [];
    }

    return [];
}

function getLeadingConditionalElement(children: Element[]): Element | null {
    for (const child of children) {
        if (child.tagName.toLowerCase() === SW_BLOCK_PARENT_TAG) {
            continue;
        }

        if (child.hasAttribute('v-else') || child.hasAttribute('v-else-if')) {
            return child;
        }

        return null;
    }

    return null;
}

function rewriteTrailingConditionalChain(blockName: string, conditionalChain: Element[]): boolean {
    if (conditionalChain.length === 0) {
        return false;
    }

    const firstConditional = conditionalChain[0];
    const firstExpression = firstConditional.getAttribute('v-if');

    if (!firstExpression) {
        return false;
    }

    firstConditional.setAttribute('v-if', createLegacyHelperExpression('$swLegacyBlockIf', blockName, firstExpression));

    conditionalChain.slice(1).forEach((conditionalElement) => {
        const expression = conditionalElement.getAttribute('v-else-if');

        if (!expression) {
            return;
        }

        conditionalElement.removeAttribute('v-else-if');
        conditionalElement.setAttribute('v-if', createLegacyHelperExpression('$swLegacyBlockElseIf', blockName, expression));
    });

    return true;
}

function rewriteLeadingConditional(blockName: string, conditionalElement: Element | null): boolean {
    if (!conditionalElement) {
        return false;
    }

    if (conditionalElement.hasAttribute('v-else')) {
        conditionalElement.removeAttribute('v-else');
        conditionalElement.setAttribute('v-if', createLegacyHelperExpression('$swLegacyBlockElse', blockName));

        return true;
    }

    const expression = conditionalElement.getAttribute('v-else-if');

    if (!expression) {
        return false;
    }

    conditionalElement.removeAttribute('v-else-if');
    conditionalElement.setAttribute('v-if', createLegacyHelperExpression('$swLegacyBlockElseIf', blockName, expression));

    return true;
}

/**
 * @private
 */
export default function transformLegacyBlockConditionals(template: string): string {
    if (!template.includes('<sw-block') || !CONDITIONAL_REG_EXP.test(template) || typeof document === 'undefined') {
        return template;
    }

    const parsedTemplate = document.createElement('template');
    parsedTemplate.innerHTML = normalizeSelfClosingTags(template);

    let hasChanges = false;

    parsedTemplate.content.querySelectorAll('sw-block[name]').forEach((blockElement) => {
        const blockName = blockElement.getAttribute('name');

        if (!blockName) {
            return;
        }

        hasChanges =
            rewriteTrailingConditionalChain(blockName, getTrailingConditionalChain(Array.from(blockElement.children))) ||
            hasChanges;
    });

    parsedTemplate.content.querySelectorAll('sw-block[extends]').forEach((blockElement) => {
        const blockName = blockElement.getAttribute('extends');

        if (!blockName) {
            return;
        }

        hasChanges =
            rewriteLeadingConditional(blockName, getLeadingConditionalElement(Array.from(blockElement.children))) ||
            hasChanges;
    });

    if (!hasChanges) {
        return template;
    }

    return parsedTemplate.innerHTML;
}
