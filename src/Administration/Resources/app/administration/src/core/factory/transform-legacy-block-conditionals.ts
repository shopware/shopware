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

/** Escapes block names for helper calls embedded in single-quoted Vue expressions. */
function escapeSingleQuotedString(value: string): string {
    return value.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

/** Escapes block names for the temporary wrapper attribute used during DOM parsing. */
function escapeDoubleQuotedString(value: string): string {
    return value.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
}

/** Builds the replacement v-if expression that links a case to the legacy condition state. */
function createLegacyHelperExpression(helperName: string, blockName: string, expression?: string | null): string {
    const escapedBlockName = escapeSingleQuotedString(blockName);

    if (!expression) {
        return `${helperName}('${escapedBlockName}')`;
    }

    return `${helperName}('${escapedBlockName}', ${expression})`;
}

/** Expands self-closing custom components so the browser parser keeps the intended tree. */
function normalizeSelfClosingTags(template: string): string {
    return template.replace(SELF_CLOSING_TAG_REG_EXP, (match, tagName: string, attributes: string = '') => {
        const trimmedAttributes = attributes.trim();
        const normalizedAttributes = trimmedAttributes.length > 0 ? ` ${trimmedAttributes}` : '';

        return `<${tagName}${normalizedAttributes}></${tagName}>`;
    });
}

/** Finds the v-if / v-else-if chain at the end of a native block. */
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

/** Finds the leading v-else-if / v-else chain that directly continues another block. */
function getLeadingConditionalChain(children: Element[]): Element[] {
    const firstElement = children[0];

    if (!firstElement || (!firstElement.hasAttribute('v-else') && !firstElement.hasAttribute('v-else-if'))) {
        return [];
    }

    const conditionalChain = [firstElement];

    if (firstElement.hasAttribute('v-else')) {
        return conditionalChain;
    }

    for (let index = 1; index < children.length; index += 1) {
        const child = children[index];

        if (child.hasAttribute('v-else-if')) {
            conditionalChain.push(child);
            continue;
        }

        if (child.hasAttribute('v-else')) {
            conditionalChain.push(child);
        }

        return conditionalChain;
    }

    return conditionalChain;
}

/** Finds the v-else-if / v-else chain that directly continues after sw-block-parent. */
function getConditionalChainFollowingBlockParent(children: Element[]): Element[] {
    let shouldCheckChild = true;

    for (let index = 0; index < children.length; index += 1) {
        const child = children[index];

        if (child.tagName.toLowerCase() === SW_BLOCK_PARENT_TAG) {
            shouldCheckChild = true;
            continue;
        }

        if (shouldCheckChild && (child.hasAttribute('v-else') || child.hasAttribute('v-else-if'))) {
            return getLeadingConditionalChain(children.slice(index));
        }

        shouldCheckChild = false;
    }

    return [];
}

/** Rewrites the parent side of a cross-block conditional chain. */
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

/** Rewrites the extension side so Vue no longer needs adjacent v-if/v-else nodes. */
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

/** Rewrites a leading v-else-if / v-else chain to standalone v-if helper calls. */
function rewriteLeadingConditionalChain(
    blockName: string,
    conditionalChain: Element[],
    helpers: LegacyBlockHelperNames = GLOBAL_LEGACY_HELPERS,
): boolean {
    if (conditionalChain.length === 0) {
        return false;
    }

    let hasChanges = false;

    conditionalChain.forEach((conditionalElement) => {
        hasChanges = rewriteLeadingConditional(blockName, conditionalElement, helpers) || hasChanges;
    });

    return hasChanges;
}

/** Rewrites v-else-if / v-else cases that continue in following sibling sw-blocks. */
function rewriteFollowingNamedBlockConditionals(
    blockName: string,
    blockElement: Element,
    rewrittenContinuationBlocks: WeakSet<Element>,
    helpers: LegacyBlockHelperNames = GLOBAL_LEGACY_HELPERS,
): boolean {
    let nextBlockElement = blockElement.nextElementSibling;
    let hasChanges = false;

    while (nextBlockElement?.tagName.toLowerCase() === SW_BLOCK_TAG && nextBlockElement.hasAttribute('name')) {
        const leadingConditionalChain = getLeadingConditionalChain(Array.from(nextBlockElement.children));

        if (leadingConditionalChain.length === 0) {
            return hasChanges;
        }

        const hasFinalElseCase = leadingConditionalChain.at(-1)?.hasAttribute('v-else') ?? false;

        if (!rewriteLeadingConditionalChain(blockName, leadingConditionalChain, helpers)) {
            return hasChanges;
        }

        rewrittenContinuationBlocks.add(nextBlockElement);
        hasChanges = true;

        if (hasFinalElseCase) {
            return hasChanges;
        }

        nextBlockElement = nextBlockElement.nextElementSibling;
    }

    return hasChanges;
}

/**
 * Rewrites native sw-block conditional chains before Vue compiles them.
 *
 * @private
 */
export default function transformLegacyBlockConditionals(template: string): string {
    if (!template.includes('<sw-block') || !CONDITIONAL_REG_EXP.test(template) || typeof document === 'undefined') {
        return template;
    }

    const parsedTemplate = document.createElement('template');
    parsedTemplate.innerHTML = normalizeSelfClosingTags(template);

    let hasChanges = false;
    const rewrittenContinuationBlocks = new WeakSet<Element>();

    parsedTemplate.content.querySelectorAll('sw-block[name]').forEach((blockElement) => {
        if (rewrittenContinuationBlocks.has(blockElement)) {
            return;
        }

        const blockName = blockElement.getAttribute('name');

        if (!blockName) {
            return;
        }

        if (!rewriteTrailingConditionalChain(blockName, getTrailingConditionalChain(Array.from(blockElement.children)))) {
            return;
        }

        hasChanges = true;
        hasChanges =
            rewriteFollowingNamedBlockConditionals(blockName, blockElement, rewrittenContinuationBlocks) || hasChanges;
    });

    parsedTemplate.content.querySelectorAll('sw-block[extends]').forEach((blockElement) => {
        const blockName = blockElement.getAttribute('extends');

        if (!blockName) {
            return;
        }

        hasChanges =
            rewriteLeadingConditionalChain(
                blockName,
                getConditionalChainFollowingBlockParent(Array.from(blockElement.children)),
            ) || hasChanges;
    });

    if (!hasChanges) {
        return template;
    }

    return parsedTemplate.innerHTML;
}

/**
 * Applies the same rewrite to reconstructed legacy Twig block override content.
 *
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
        !rewriteLeadingConditionalChain(
            blockName,
            getConditionalChainFollowingBlockParent(Array.from(blockElement.children)),
        )
    ) {
        return template;
    }

    return blockElement.innerHTML;
}
