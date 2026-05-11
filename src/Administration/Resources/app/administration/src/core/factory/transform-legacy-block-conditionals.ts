/**
 * @sw-package framework
 */

const SELF_CLOSING_TAG_REG_EXP = /<([A-Za-z][\w:-]*)(?:\s+((?:[^"'<>]|"[^"]*"|'[^']*')*?))?\s*\/>/g;
const CONDITIONAL_REG_EXP = /v-(?:if|else-if|else)\b/;
const SW_BLOCK_PARENT_TAG = 'sw-block-parent';
const SW_BLOCK_TAG = 'sw-block';

type LegacyBlockHelperNames = {
    if: string;
    elseIf: string;
    else: string;
};

const GLOBAL_LEGACY_HELPERS = {
    if: '$swLegacyBlockIf',
    elseIf: '$swLegacyBlockElseIf',
    else: '$swLegacyBlockElse',
} satisfies LegacyBlockHelperNames;
const SHIM_LEGACY_HELPERS = {
    if: 'swLegacyBlockIf',
    elseIf: 'swLegacyBlockElseIf',
    else: 'swLegacyBlockElse',
} satisfies LegacyBlockHelperNames;

function escapeSingleQuotedString(value: string): string {
    return value.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

function escapeDoubleQuotedString(value: string): string {
    return value.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
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

function getConditionalElementFollowingBlockParent(children: Element[]): Element | null {
    let shouldCheckChild = true;

    for (const child of children) {
        if (child.tagName.toLowerCase() === SW_BLOCK_PARENT_TAG) {
            shouldCheckChild = true;
            continue;
        }

        if (shouldCheckChild && (child.hasAttribute('v-else') || child.hasAttribute('v-else-if'))) {
            return child;
        }

        shouldCheckChild = false;
    }

    return null;
}

function rewriteTrailingConditionalChain(
    blockName: string,
    conditionalChain: Element[],
    helpers: LegacyBlockHelperNames = GLOBAL_LEGACY_HELPERS,
): boolean {
    if (conditionalChain.length === 0) {
        return false;
    }

    const firstConditional = conditionalChain[0];
    const firstExpression = firstConditional.getAttribute('v-if');

    if (!firstExpression) {
        return false;
    }

    firstConditional.setAttribute('v-if', createLegacyHelperExpression(helpers.if, blockName, firstExpression));

    conditionalChain.slice(1).forEach((conditionalElement) => {
        const expression = conditionalElement.getAttribute('v-else-if');

        if (!expression) {
            return;
        }

        conditionalElement.removeAttribute('v-else-if');
        conditionalElement.setAttribute('v-if', createLegacyHelperExpression(helpers.elseIf, blockName, expression));
    });

    return true;
}

function rewriteLeadingConditional(
    blockName: string,
    conditionalElement: Element | null,
    helpers: LegacyBlockHelperNames = GLOBAL_LEGACY_HELPERS,
): boolean {
    if (!conditionalElement) {
        return false;
    }

    if (conditionalElement.hasAttribute('v-else')) {
        conditionalElement.removeAttribute('v-else');
        conditionalElement.setAttribute('v-if', createLegacyHelperExpression(helpers.else, blockName));

        return true;
    }

    const expression = conditionalElement.getAttribute('v-else-if');

    if (!expression) {
        return false;
    }

    conditionalElement.removeAttribute('v-else-if');
    conditionalElement.setAttribute('v-if', createLegacyHelperExpression(helpers.elseIf, blockName, expression));

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
            rewriteLeadingConditional(
                blockName,
                getConditionalElementFollowingBlockParent(Array.from(blockElement.children)),
            ) ||
            hasChanges;
    });

    if (!hasChanges) {
        return template;
    }

    return parsedTemplate.innerHTML;
}

/**
 * @private
 */
export function transformLegacyBlockExtensionConditionals(blockName: string, template: string): string {
    if (!CONDITIONAL_REG_EXP.test(template) || typeof document === 'undefined') {
        return template;
    }

    const parsedTemplate = document.createElement('template');
    parsedTemplate.innerHTML =
        `<${SW_BLOCK_TAG} extends="${escapeDoubleQuotedString(blockName)}">` +
        `${normalizeSelfClosingTags(template)}` +
        `</${SW_BLOCK_TAG}>`;

    const blockElement = parsedTemplate.content.querySelector(`${SW_BLOCK_TAG}[extends]`);

    if (
        !blockElement ||
        !rewriteLeadingConditional(
            blockName,
            getConditionalElementFollowingBlockParent(Array.from(blockElement.children)),
            SHIM_LEGACY_HELPERS,
        )
    ) {
        return template;
    }

    return blockElement.innerHTML;
}
