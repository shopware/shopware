/**
 * @sw-package framework
 */

const SELF_CLOSING_TAG_REG_EXP = /<([A-Za-z][\w:-]*)(?:\s+((?:[^"'<>]|"[^"]*"|'[^']*')*?))?\s*\/>/g;
const CONDITIONAL_REG_EXP = /v-(?:else-if|else)\b/;
const SW_BLOCK_PARENT_TAG = 'sw-block-parent';
const SW_BLOCK_TAG = 'sw-block';
const LEGACY_BLOCK_ELSE_IF_HELPER = '$swLegacyBlockElseIf';
const LEGACY_BLOCK_ELSE_HELPER = '$swLegacyBlockElse';

/** Escapes block names for helper calls embedded in single-quoted Vue expressions. */
function escapeSingleQuotedString(value: string): string {
    return value.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

/** Escapes block names for the temporary wrapper attribute used during DOM parsing. */
function escapeDoubleQuotedString(value: string): string {
    return value.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
}

/** Builds the replacement v-if expression that links a branch to the legacy chain state. */
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

/** Finds the first v-else / v-else-if that directly continues after sw-block-parent. */
function getConditionalElementFollowingBlockParent(children: Element[]): Element | null {
    let shouldCheckChild = false;

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

/** Rewrites legacy Twig extension content so Vue no longer needs adjacent v-if/v-else nodes. */
function rewriteLeadingConditional(blockName: string, conditionalElement: Element | null): boolean {
    if (!conditionalElement) {
        return false;
    }

    if (conditionalElement.hasAttribute('v-else')) {
        conditionalElement.removeAttribute('v-else');
        conditionalElement.setAttribute('v-if', createLegacyHelperExpression(LEGACY_BLOCK_ELSE_HELPER, blockName));

        return true;
    }

    const expression = conditionalElement.getAttribute('v-else-if');

    if (!expression) {
        return false;
    }

    conditionalElement.removeAttribute('v-else-if');
    conditionalElement.setAttribute(
        'v-if',
        createLegacyHelperExpression(LEGACY_BLOCK_ELSE_IF_HELPER, blockName, expression),
    );

    return true;
}

/** @private */
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
        !rewriteLeadingConditional(blockName, getConditionalElementFollowingBlockParent(Array.from(blockElement.children)))
    ) {
        return template;
    }

    return blockElement.innerHTML;
}
