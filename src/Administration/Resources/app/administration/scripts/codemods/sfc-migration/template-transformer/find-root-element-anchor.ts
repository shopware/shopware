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
 * that already carries a `ref`, and anything the scan cannot read.
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

const TAG_NAME_RE = /^<([a-z][^\s/>]*)/iu;
const CONDITIONAL_ATTRIBUTE_RE = /\sv-(?:if|else-if|else|for)[\s=>/]/u;
const REF_ATTRIBUTE_RE = /\s(?::|v-bind:)?ref\s*=/u;

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
        // way the scan cannot reason about.
        if (body[index] !== '<') {
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

        return index + 1 + tagName.length;
    }
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
