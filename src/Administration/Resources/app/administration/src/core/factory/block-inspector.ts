/**
 * @sw-package framework
 * @private
 *
 * Development-only block inspector support.
 *
 * Twig blocks vanish once a component template is rendered, so a developer looking at the DOM
 * has no way to tell which extension block a piece of UI belongs to. When the inspector is enabled,
 * every element that starts a block is marked with the `data-sw-block` attribute holding the names
 * of all blocks starting there, innermost first. The Vue devtools plugin in
 * `src/app/adapter/view/sw-vue-devtools` reads these markers to list, highlight and pick blocks.
 *
 * Marking changes the compiled templates and is therefore opt-in: it is read from `localStorage`
 * once at boot and needs a reload to take effect. Nothing in this module runs in production builds.
 */

/**
 * Attribute carrying the block names of the element, innermost first, separated by a space.
 * Query the DOM with `[data-sw-block~="block_name"]`.
 *
 * @private
 */
export const BLOCK_MARKER_ATTRIBUTE = 'data-sw-block';

/**
 * `localStorage` key that turns the block markers on for the next boot.
 *
 * @private
 */
export const BLOCK_INSPECTOR_STORAGE_KEY = 'sw-admin-block-inspector';

/**
 * How a block is authored.
 *
 * - `twig`: a `{% block %}` in a Twig component template
 * - `native`: an `<sw-block name>` written in a single file component
 *
 * @private
 */
export type InspectedBlockKind = 'twig' | 'native';

/**
 * Metadata the inspector shows for a block.
 *
 * @private
 */
export type InspectedBlock = {
    name: string;
    component: string;
    kind: InspectedBlockKind;
};

type TwigBlockToken = {
    blockName: string;
};

type TwigParseResult = {
    chain: unknown;
    output: string;
};

type TwigBlockHandler = {
    parse: (this: unknown, token: TwigBlockToken, context: unknown, chain: unknown) => PromiseLike<TwigParseResult>;
};

type TwigCoreLike = {
    logic: {
        type: { block: string };
        handler: Record<string, TwigBlockHandler>;
    };
};

/** Elements that never take a closing tag, so they never open a nesting level. */
const VOID_ELEMENTS = new Set([
    'area',
    'base',
    'br',
    'col',
    'embed',
    'hr',
    'img',
    'input',
    'link',
    'meta',
    'param',
    'source',
    'track',
    'wbr',
]);

/**
 * Tags that render no element of their own. Their children are marked instead.
 *
 * `<template>` is Vue's grouping tag for `v-if`, `v-for` and named slots. `<sw-block>` is an
 * extension point wrapper: marking it would fall through to its root and overwrite the marker of
 * the block it hosts, so the marker goes onto its content instead.
 */
const TRANSPARENT_TAGS = new Set([
    'template',
    'sw-block',
]);

/**
 * Built-ins that are no DOM element and pass unknown attributes on in surprising ways: a `<slot>`
 * turns them into slot props, `<transition>` hands them to whatever it wraps.
 */
const UNMARKABLE_TAGS = new Set([
    'slot',
    'teleport',
    'transition',
    'transition-group',
    'keep-alive',
    'suspense',
]);

const TAG_NAME_PATTERN = /^<([a-zA-Z][\w.:-]*)/;

const inspectedBlocks = new Map<string, InspectedBlock>();

let enabledState: boolean | null = null;
let inspectedComponent: string | null = null;

function readStoredFlag(): boolean {
    if (process.env.NODE_ENV === 'production') {
        return false;
    }

    try {
        return globalThis.localStorage?.getItem(BLOCK_INSPECTOR_STORAGE_KEY) === 'true';
    } catch {
        return false;
    }
}

/**
 * Whether block markers are rendered in this session. Read once from `localStorage` at boot.
 *
 * @private
 */
export function isBlockInspectorEnabled(): boolean {
    if (enabledState === null) {
        enabledState = readStoredFlag();
    }

    return enabledState;
}

/**
 * Persists the flag for the next boot and applies it to this session right away.
 *
 * Templates rendered before the call keep their state, so the devtools reload the page after
 * flipping the flag.
 *
 * @private
 */
export function setBlockInspectorEnabled(enabled: boolean): void {
    enabledState = enabled;

    try {
        if (enabled) {
            globalThis.localStorage?.setItem(BLOCK_INSPECTOR_STORAGE_KEY, 'true');
        } else {
            globalThis.localStorage?.removeItem(BLOCK_INSPECTOR_STORAGE_KEY);
        }
    } catch {
        // Storage can be unavailable; the in-memory state is enough for this session.
    }
}

/**
 * Records where a block comes from. The first registration wins, so a Twig block that also hosts a
 * native extension point keeps its Twig origin.
 *
 * @private
 */
export function registerInspectedBlock(block: InspectedBlock): void {
    if (inspectedBlocks.has(block.name)) {
        return;
    }

    inspectedBlocks.set(block.name, block);
}

/**
 * Metadata of one block, or undefined for a block that was never rendered.
 *
 * @private
 */
export function getInspectedBlock(name: string): InspectedBlock | undefined {
    return inspectedBlocks.get(name);
}

/**
 * All blocks seen so far.
 *
 * @private
 */
export function getInspectedBlocks(): ReadonlyMap<string, InspectedBlock> {
    return inspectedBlocks;
}

/**
 * Drops the enabled flag and every registration. Meant for tests.
 *
 * @private
 */
export function resetBlockInspector(): void {
    inspectedBlocks.clear();
    enabledState = null;
    inspectedComponent = null;
}

/**
 * Names in a marker value.
 *
 * @private
 */
export function parseBlockMarker(value: string | null | undefined): string[] {
    return (value ?? '').split(/\s+/).filter((name) => name.length > 0);
}

/**
 * Adds a block name to a marker value, keeping existing names and their order.
 *
 * @private
 */
export function mergeBlockMarker(existing: string | null | undefined, blockName: string): string {
    const names = parseBlockMarker(existing);

    if (!names.includes(blockName)) {
        names.push(blockName);
    }

    return names.join(' ');
}

/** Index just past the `>` closing the tag that starts at `from`, ignoring quoted attribute values. */
function findTagEnd(text: string, from: number): number {
    let quote: string | null = null;

    for (let i = from; i < text.length; i += 1) {
        const char = text[i];

        if (quote) {
            quote = char === quote ? null : quote;
        } else if (char === '"' || char === "'") {
            quote = char;
        } else if (char === '>') {
            return i + 1;
        }
    }

    return -1;
}

/**
 * Adds the block name to a single opening tag, extending a marker that is already there.
 */
function markTag(tag: string, blockName: string): string {
    const markerPattern = new RegExp(`(\\s${BLOCK_MARKER_ATTRIBUTE}=")([^"]*)(")`);
    const existing = markerPattern.exec(tag);

    if (existing) {
        return tag.replace(markerPattern, (_, before: string, value: string, after: string) => {
            return `${before}${mergeBlockMarker(value, blockName)}${after}`;
        });
    }

    return tag.replace(TAG_NAME_PATTERN, `$& ${BLOCK_MARKER_ATTRIBUTE}="${blockName}"`);
}

/**
 * Marks every top-level element of a rendered block with the block name.
 *
 * The scan tracks nesting depth over the tags of the fragment. Comments and `{{ }}` interpolations
 * are skipped, quoted attribute values may contain `>`. Top-level `<template>` and `<sw-block>`
 * tags are transparent: they add no element, so their own top-level children are marked instead.
 * Text-only blocks come back unchanged; they have nothing to point at.
 *
 * @private
 */
export function markBlockElements(html: string, blockName: string): string {
    let result = '';
    let copiedUpTo = 0;
    let depth = 0;
    let openTransparentTags = 0;
    let position = 0;

    while (position < html.length) {
        const tagStart = html.indexOf('<', position);

        if (tagStart === -1) {
            break;
        }

        const interpolationStart = html.indexOf('{{', position);

        if (interpolationStart !== -1 && interpolationStart < tagStart) {
            const interpolationEnd = html.indexOf('}}', interpolationStart + 2);
            position = interpolationEnd === -1 ? html.length : interpolationEnd + 2;

            continue;
        }

        if (html.startsWith('<!--', tagStart)) {
            const commentEnd = html.indexOf('-->', tagStart + 4);
            position = commentEnd === -1 ? html.length : commentEnd + 3;

            continue;
        }

        if (html.startsWith('</', tagStart)) {
            const closingEnd = html.indexOf('>', tagStart);
            position = closingEnd === -1 ? html.length : closingEnd + 1;

            if (depth > 0) {
                depth -= 1;
            } else if (openTransparentTags > 0) {
                openTransparentTags -= 1;
            }

            continue;
        }

        const nameMatch = TAG_NAME_PATTERN.exec(html.slice(tagStart));
        const tagEnd = findTagEnd(html, tagStart);

        if (!nameMatch || tagEnd === -1) {
            position = tagStart + 1;

            continue;
        }

        const tagName = nameMatch[1].toLowerCase();
        const tag = html.slice(tagStart, tagEnd);
        const isSelfClosing = tag.endsWith('/>');
        const opensLevel = !isSelfClosing && !VOID_ELEMENTS.has(tagName);

        if (depth === 0) {
            if (TRANSPARENT_TAGS.has(tagName)) {
                if (opensLevel) {
                    openTransparentTags += 1;
                }

                position = tagEnd;

                continue;
            }

            if (!UNMARKABLE_TAGS.has(tagName)) {
                result += html.slice(copiedUpTo, tagStart) + markTag(tag, blockName);
                copiedUpTo = tagEnd;
            }
        }

        if (opensLevel) {
            depth += 1;
        }

        position = tagEnd;
    }

    return result + html.slice(copiedUpTo);
}

/**
 * Runs a template render for the given component so that the Twig marker can attribute the blocks
 * it sees to that component.
 *
 * @private
 */
export function renderForBlockInspection<T>(componentName: string, render: () => T): T {
    const previous = inspectedComponent;
    inspectedComponent = componentName;

    try {
        return render();
    } finally {
        inspectedComponent = previous;
    }
}

/**
 * Wraps Twig's `{% block %}` handler so that every rendered block leaves a marker on its elements.
 *
 * Nested blocks render first, so an element starting several blocks collects their names from the
 * inside out. Overrides and `{% parent %}` are merged into the token tree before rendering and need
 * no special handling here. The wrapper is a no-op while the inspector is disabled.
 *
 * @private
 */
export function installTwigBlockMarker(TwigCore: TwigCoreLike): void {
    const handler = TwigCore.logic.handler[TwigCore.logic.type.block];
    const originalParse = handler.parse;

    handler.parse = function parseWithBlockMarker(token, context, chain) {
        const result = originalParse.call(this, token, context, chain);

        if (!isBlockInspectorEnabled()) {
            return result;
        }

        return result.then((parsed) => {
            if (inspectedComponent) {
                registerInspectedBlock({ name: token.blockName, component: inspectedComponent, kind: 'twig' });
            }

            return {
                ...parsed,
                output: markBlockElements(parsed.output, token.blockName),
            };
        });
    };
}
