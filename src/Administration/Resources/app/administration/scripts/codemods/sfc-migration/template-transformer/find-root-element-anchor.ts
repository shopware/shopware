/**
 * Locates the element a generated root template ref may be placed on.
 *
 * `this.$el` has no setup equivalent, and after migration it is worse than that:
 * the generated root is `<sw-block>`, which renders a Fragment, so
 * `getCurrentInstance()?.proxy?.$el` resolves to the fragment's text anchor
 * rather than to the element the Options API code meant. `<sw-block>` itself
 * hard-rejects an authored attribute (only its static `name` is allowed), so the
 * ref goes on the first real element inside it — which is what `$el` resolved to
 * for a single-root component.
 *
 * Everything that is not provably that element returns null and keeps the
 * placeholder: a component tag (its `$el` is the child's root, not this one), a
 * conditional or repeated element (it may not be rendered at all), an element
 * that already carries a `ref`, a template with a second root node (whose `$el`
 * is the Fragment's start anchor, not any element), and anything the scan cannot
 * read.
 */

/** Tags that are compiler constructs rather than DOM elements. */
const NON_DOM_TAGS = new Set([
    'template',
    'slot',
    'component',
    'transition',
    'teleport',
    'suspense',
]);

/** Elements that never have a closing tag, so their opening tag is the element. */
const VOID_TAGS = new Set([
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

const TAG_NAME_RE = /^<\/?([a-z][^\s/>]*)/iu;
const CONDITIONAL_ATTRIBUTE_RE = /\sv-(?:if|else-if|else|for)[\s=>/]/u;
const REF_ATTRIBUTE_RE = /\s(?::|v-bind:)?ref\s*=/u;
const BLOCK_CLOSING_TAG_RE = /^<\/sw-block\s*>/u;

/**
 * Returns the offset in `body` where a ` ref="…"` attribute can be inserted —
 * directly after the tag name of the anchor element — or null when there is no
 * such element.
 */
export function findRootElementAnchor(body: string): number | null {
    let index = 0;

    for (;;) {
        index = skipIgnorable(body, index);

        // Text before the first element means the component is multi-root in a
        // way the scan cannot reason about, and a closing tag here means the
        // block is empty or the markup is not what this scan assumes.
        if (body[index] !== '<' || body.startsWith('</', index)) {
            return null;
        }

        const tagName = TAG_NAME_RE.exec(body.slice(index))?.[1];
        const tagEnd = tagName === undefined ? null : findTagEnd(body, index);

        if (tagName === undefined || tagEnd === null) {
            return null;
        }

        const openingTag = body.slice(index, tagEnd + 1);

        // Blocks nest, and none of them is the anchor: they lower into Fragments.
        if (tagName === 'sw-block') {
            if (openingTag.endsWith('/>')) {
                return null;
            }

            index = tagEnd + 1;
            continue;
        }

        if (!isPlainHtmlTag(tagName) || CONDITIONAL_ATTRIBUTE_RE.test(openingTag) || REF_ATTRIBUTE_RE.test(openingTag)) {
            return null;
        }

        // A sibling after this element makes the component multi-root, and a
        // multi-root component's `$el` is the Fragment's start anchor — an empty
        // text node, not this element. Putting the ref here would quietly change
        // what comparisons like `event.target !== this.$el` answer.
        const elementEnd = findElementEnd(body, tagName, index, tagEnd);

        if (elementEnd === null || !isFollowedOnlyByBlockClosings(body, elementEnd)) {
            return null;
        }

        return index + 1 + tagName.length;
    }
}

/** Offset just past the element that starts at `openingTagStart`, or null. */
function findElementEnd(body: string, tagName: string, openingTagStart: number, openingTagEnd: number): number | null {
    if (body.slice(openingTagStart, openingTagEnd + 1).endsWith('/>') || VOID_TAGS.has(tagName)) {
        return openingTagEnd + 1;
    }

    let depth = 1;
    let index = openingTagEnd + 1;

    while (index < body.length) {
        if (body.startsWith('<!--', index)) {
            const commentEnd = body.indexOf('-->', index);

            if (commentEnd === -1) {
                return null;
            }

            index = commentEnd + '-->'.length;
            continue;
        }

        if (body[index] !== '<') {
            index += 1;
            continue;
        }

        const name = TAG_NAME_RE.exec(body.slice(index))?.[1];
        const tagEnd = name === undefined ? null : findTagEnd(body, index);

        // A stray `<` — in an interpolation, say — is not something this scan can
        // read, so the anchor is given up rather than guessed at.
        if (name === undefined || tagEnd === null) {
            return null;
        }

        if (name === tagName) {
            if (body.startsWith('</', index)) {
                depth -= 1;

                if (depth === 0) {
                    return tagEnd + 1;
                }
            } else if (!body.slice(index, tagEnd + 1).endsWith('/>') && !VOID_TAGS.has(name)) {
                depth += 1;
            }
        }

        index = tagEnd + 1;
    }

    return null;
}

/**
 * true when only the closing tags of the blocks the scan descended through —
 * plus whitespace and comments — follow. Anything else is a second root node.
 */
function isFollowedOnlyByBlockClosings(body: string, start: number): boolean {
    let index = skipIgnorable(body, start);

    while (index < body.length) {
        const closingTag = BLOCK_CLOSING_TAG_RE.exec(body.slice(index));

        if (!closingTag) {
            return false;
        }

        index = skipIgnorable(body, index + closingTag[0].length);
    }

    return true;
}

/** A dash or an uppercase letter marks a component, never a DOM element. */
function isPlainHtmlTag(tagName: string): boolean {
    return !tagName.includes('-') && tagName === tagName.toLowerCase() && !NON_DOM_TAGS.has(tagName);
}

function skipIgnorable(body: string, start: number): number {
    let index = start;

    for (;;) {
        while (index < body.length && /\s/u.test(body[index])) {
            index += 1;
        }

        if (!body.startsWith('<!--', index)) {
            return index;
        }

        const commentEnd = body.indexOf('-->', index);

        if (commentEnd === -1) {
            return body.length;
        }

        index = commentEnd + '-->'.length;
    }
}

/** Index of the `>` closing the tag that starts at `start`, quotes respected. */
function findTagEnd(body: string, start: number): number | null {
    let quote: string | null = null;

    for (let index = start; index < body.length; index += 1) {
        const character = body[index];

        if (quote) {
            if (character === quote) {
                quote = null;
            }

            continue;
        }

        if (character === '"' || character === "'") {
            quote = character;
        } else if (character === '>') {
            return index;
        }
    }

    return null;
}
