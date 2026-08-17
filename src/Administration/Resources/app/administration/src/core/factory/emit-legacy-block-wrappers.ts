/**
 * @sw-package framework
 * @private
 *
 * Twig → Native Block shim, base-template half.
 *
 * Stringifies a merged TwigJS token tree like `template.render()` does, but keeps the boundary of every
 * block a native override targets as a real `<sw-block>` element. That is the single missing piece that
 * lets an extension extend a Twig-based core component with a native `.override.vue` file: the override
 * side (global block context, hidden boot components, `$dataScope` with its Options-API proxy fallback)
 * is entirely agnostic of how the owning component is written — it only needs something to render
 * `<sw-block name="...">`.
 *
 * The emitted wrapper carries `from-twig-template`, which tells `sw-block` not to additionally consume
 * the legacy Twig block index: for a Twig owner, `TemplateFactory` has already merged legacy Twig
 * overrides into this very token tree, so consuming the index too would render them twice.
 *
 * Only blocks in {@link hasNativeBlockOverride} are wrapped. Untargeted templates never reach this module.
 */

import type { TwigToken } from './reconstruct-twig-template';
import { hasNativeBlockOverride } from './native-block-override-registry';

/**
 * Matches the first element of a fragment, capturing its tag name and its raw attribute text.
 * Quoted attribute values may contain `>`, so they are consumed as units.
 */
const FIRST_ELEMENT_REG_EXP = /<\s*([a-zA-Z][\w.-]*)((?:"[^"]*"|'[^']*'|[^>"'])*)\/?>/;

const V_ELSE_REG_EXP = /(?:^|\s)v-else(?:-if)?(?:[=\s]|$)/;

const NAMED_SLOT_REG_EXP = /(?:^|\s)(?:#|v-slot(?::|$))/;

/**
 * A block boundary that cannot become an element without changing what the template means.
 */
type WrapRejection = {
    blockName: string;
    reason: string;
};

/**
 * What a single emit run produced: how many boundaries became elements, and which ones could not.
 */
type EmitState = {
    wrapped: number;
    rejections: WrapRejection[];
};

/**
 * Returns the first element of a fragment as `[tagName, attributeText]`, or `null` for a fragment that
 * starts with text, a comment or nothing at all.
 */
function findFirstElement(fragment: string): [string, string] | null {
    const match = FIRST_ELEMENT_REG_EXP.exec(fragment.replace(/<!--[\s\S]*?-->/g, ''));

    if (!match) {
        return null;
    }

    return [
        match[1],
        match[2] ?? '',
    ];
}

/**
 * Explains why a block boundary must not become a `<sw-block>` element, or returns `null` when it may.
 *
 * `sw-block` renders its content as a fragment, so it adds no DOM node — but it does add a component
 * boundary, and two Vue features resolve across exactly that boundary:
 *
 * - a `<template #slot>` must be a *direct* child of the component owning the slot,
 * - `v-else` / `v-else-if` must be the immediate sibling of its `v-if`.
 *
 * Both patterns are common in Twig templates (~1.000 of ~5.100 core blocks), which is why wrapping is
 * decided per block instead of globally.
 *
 * Known gap: the sibling check only sees the raw content directly after the block, so a `v-else` that
 * lives in a *following block* is not detected.
 */
function findWrapRejection(innerTemplate: string, followingContent: string): string | null {
    const firstElement = findFirstElement(innerTemplate);

    if (firstElement) {
        const [
            tagName,
            attributes,
        ] = firstElement;

        if (tagName === 'template' && NAMED_SLOT_REG_EXP.test(attributes)) {
            return 'it starts with a named slot template, which must stay a direct child of the slot owner';
        }

        if (V_ELSE_REG_EXP.test(attributes)) {
            return 'it starts with a v-else branch whose v-if lives outside the block';
        }
    }

    const followingElement = findFirstElement(followingContent);

    if (followingElement && V_ELSE_REG_EXP.test(followingElement[1])) {
        return 'it is directly followed by a v-else branch, whose v-if would end up inside the block';
    }

    return null;
}

/**
 * Escapes a block name for use in a double-quoted HTML attribute.
 */
function escapeAttributeValue(value: string): string {
    return value.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
}

/**
 * Returns the leading text content of the tokens following a block, used for the `v-else` sibling check.
 */
function peekFollowingContent(tokens: TwigToken[], index: number): string {
    const next = tokens[index + 1];

    return next?.type === 'raw' ? (next.value ?? '') : '';
}

/**
 * Stringifies a token list, wrapping every targeted block boundary.
 *
 * Mirrors `reconstruct-twig-template`, with the same known limitation: Twig control-flow tags collapse
 * to an empty string, which matches the admin contract of `{% block %}` and `{% parent %}` only. A
 * `{% parent %}` that survives the merge is dropped, exactly like the `parentRegExp` strip does today.
 */
function stringifyTokens(tokens: TwigToken[], state: EmitState): string {
    return tokens
        .map((token, index) => {
            if (token.type === 'raw') {
                return token.value ?? '';
            }

            if (token.type !== 'logic') {
                return '';
            }

            const blockName = token.token?.blockName;

            if (blockName === undefined) {
                return '';
            }

            const innerTemplate = stringifyTokens(token.token?.output ?? [], state);

            if (!hasNativeBlockOverride(blockName)) {
                return innerTemplate;
            }

            const rejection = findWrapRejection(innerTemplate, peekFollowingContent(tokens, index));

            if (rejection) {
                state.rejections.push({ blockName, reason: rejection });
                return innerTemplate;
            }

            state.wrapped += 1;

            return (
                `<sw-block name="${escapeAttributeValue(blockName)}" from-twig-template :data="$dataScope">` +
                `${innerTemplate}</sw-block>`
            );
        })
        .join('');
}

/**
 * Renders a merged Twig token tree to HTML with `<sw-block>` wrappers around natively overridden blocks.
 *
 * Returns `null` when the tree contains no targeted block, so the caller keeps TwigJS' own renderer and
 * templates nobody extends natively stay on the exact code path they are on today.
 *
 * Use it in `template.factory` in place of `template.render()`.
 *
 * @example
 * const html = emitLegacyBlockWrappers(templateDefinition.template.tokens, 'sw-page');
 *
 * @private
 */
export default function emitLegacyBlockWrappers(tokens: TwigToken[], componentName: string): string | null {
    const state: EmitState = { wrapped: 0, rejections: [] };
    const html = stringifyTokens(tokens, state);

    state.rejections.forEach((rejection) => {
        console.warn(
            `[sw-block] Block "${rejection.blockName}" of the Twig component "${componentName}" cannot be ` +
                `extended by a native override because ${rejection.reason}. ` +
                'Use a Twig block override for this block, or migrate the component to a native SFC.',
        );
    });

    return state.wrapped > 0 ? html : null;
}
