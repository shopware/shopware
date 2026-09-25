/**
 * Converts between the two representations of an inline-mapped text.
 *
 * Storage holds tokens — `<p>Buy the {{map:product.name}} today.</p>` — because the server expands them at render
 * time and a token is the only form that survives a round trip through the layout JSON. The editor holds nodes —
 * `<p>Buy the <span data-sw-map="product.name">Product name</span> today.</p>` — because a token typed as text is
 * editable one character at a time, which lets an author break it into something that silently stops resolving.
 *
 * Both directions live here rather than in the component so the round-trip property is testable on its own. That
 * property is the thing most likely to break without anyone noticing: a conversion that loses a token turns a mapped
 * text into a literal one on the next keystroke.
 *
 * @private
 * @sw-package discovery
 */

/**
 * Mirrors `InlineMappingTokenParser::TOKEN_PATTERN` on the server. The two must agree exactly: a token this file
 * writes but the server does not recognise renders as visible braces on the storefront, and a token the server
 * recognises but this file does not becomes editable text the author can corrupt.
 *
 * @private
 */
const TOKEN_PATTERN = /\{\{\s*map:([A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)+)\s*\}\}/g;

/**
 * A single dotted path, anchored — used to vet an attribute value that came back from the editor.
 *
 * @private
 */
const PATH_PATTERN = /^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)+$/;

/**
 * A whole text mapped to one value: a token and nothing else.
 *
 * @private
 */
const WHOLE_TEXT_TOKEN_PATTERN = /^\{\{\s*map:([A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)+)\s*\}\}$/;

/**
 * @private
 */
export const MAPPING_TOKEN_ATTRIBUTE = 'data-sw-map';

/**
 * @private
 */
export const MAPPING_TOKEN_NODE_NAME = 'mappingToken';

/**
 * The elements tiptap's schema treats as blocks. Only used to decide whether a value already has a block structure,
 * so an unrecognised tag counts as inline and gets wrapped, which is the safe direction.
 *
 * @private
 */
const BLOCK_TAGS = new Set([
    'ADDRESS',
    'ARTICLE',
    'ASIDE',
    'BLOCKQUOTE',
    'DETAILS',
    'DIV',
    'DL',
    'FIELDSET',
    'FIGCAPTION',
    'FIGURE',
    'FOOTER',
    'FORM',
    'H1',
    'H2',
    'H3',
    'H4',
    'H5',
    'H6',
    'HEADER',
    'HR',
    'LI',
    'MAIN',
    'NAV',
    'OL',
    'P',
    'PRE',
    'SECTION',
    'TABLE',
    'UL',
]);

/**
 * @private
 */
export function canonicalInlineMappingToken(path: string): string {
    return `{{map:${path}}}`;
}

/**
 * Lists the mapping paths a stored text refers to, in document order, with duplicates kept.
 *
 * @private
 */
export function parseInlineMappingTokens(text: string): string[] {
    const paths: string[] = [];

    for (const match of text.matchAll(TOKEN_PATTERN)) {
        paths.push(match[1]);
    }

    return paths;
}

/**
 * Stored text to editor markup.
 *
 * `isKnownPath` decides which tokens become nodes. A token naming a path the catalogue does not describe is left as
 * literal text, mirroring how the server leaves it verbatim in the rendered output: the author sees their typo
 * instead of a chip claiming to be a mapping. Turning it into a node would be worse than cosmetic — the node would
 * write itself back as a well-formed token, so the editor would have laundered a mistake into something that looks
 * deliberate.
 *
 * @private
 */
export function tokensToEditorHtml(text: string, isKnownPath: (path: string) => boolean): string {
    const substituted = text.replace(TOKEN_PATTERN, (match, path: string) => {
        if (!isKnownPath(path)) {
            return match;
        }

        // The path charset admits no character that is special in an attribute value, which the anchored pattern
        // above guarantees before the token is ever written back.
        return `<span ${MAPPING_TOKEN_ATTRIBUTE}="${path}"></span>`;
    });

    return ensureBlockLevel(substituted);
}

/**
 * Wraps a value that has no block structure in a paragraph.
 *
 * tiptap's document schema is `block+`, so it puts top-level inline content into a paragraph when it parses. That
 * makes an unwrapped value fail the round trip `mt-text-editor` checks on mount, and the editor answers a failed
 * round trip by locking itself behind a "review these changes" gate. The beautified diff renders the added `<p>` as
 * an empty line before the content, which reads as a stray empty paragraph.
 *
 * The case is not exotic: a text mapped as a whole is stored as exactly one bare token, with nothing around it —
 * which is also the form the migration away from whole-field mapping produces. An empty value has the same problem,
 * since `''` parses to one empty paragraph.
 *
 * This only normalises on the way *into* the editor. Storage is left alone until the author actually edits, because
 * rewriting a value on mere page load would mark the layout dirty for anyone who merely looked at it — and when they
 * do edit, {@link unwrapWholeTextToken} takes the wrapper back off, so the wrapper never reaches storage either.
 *
 * A value that mixes bare text with block elements is left untouched. tiptap may still restructure it, but that is
 * true of any markup this editor is given and is not specific to mapping; guessing at a correct block split would
 * risk changing the author's meaning.
 *
 * @private
 */
function ensureBlockLevel(html: string): string {
    if (html.trim() === '') {
        return '<p></p>';
    }

    const document = new DOMParser().parseFromString(`<body>${html}</body>`, 'text/html');
    const hasBlock = Array.from(document.body.children).some((child) => BLOCK_TAGS.has(child.tagName));

    return hasBlock ? html : `<p>${html}</p>`;
}

/**
 * Editor markup to stored text.
 *
 * The walk goes through `DOMParser` rather than a regex over the string. The input is browser-produced markup with
 * arbitrary attribute order and nesting, and a regex over it works right up until an author pastes from Word.
 *
 * A node whose attribute is not a valid dotted path is unwrapped to its text rather than dropped. That case should
 * not arise from our own node, but if markup ever arrives from elsewhere, losing the author's words is a worse
 * outcome than losing a mapping they never made.
 *
 * @private
 */
export function editorHtmlToTokens(html: string): string {
    const document = new DOMParser().parseFromString(`<body>${html}</body>`, 'text/html');
    const nodes = Array.from(document.body.querySelectorAll(`span[${MAPPING_TOKEN_ATTRIBUTE}]`));

    for (const node of nodes) {
        const path = node.getAttribute(MAPPING_TOKEN_ATTRIBUTE) ?? '';
        const replacement = PATH_PATTERN.test(path) ? canonicalInlineMappingToken(path) : (node.textContent ?? '');

        node.replaceWith(document.createTextNode(replacement));
    }

    return bareStoredValue(document.body) ?? document.body.innerHTML;
}

/**
 * Takes the paragraph back off the two texts whose paragraph belongs to the editor rather than to the author, undoing
 * what `ensureBlockLevel()` added on the way in.
 *
 * tiptap cannot represent either shape without a block, and storing the block it invents changes what renders:
 *
 * - **Nothing but one mapping token.** A mapped value carries the markup it was authored with — a category
 *   description is a heading and several paragraphs — and the renderer puts it where the token stood. Inside a `<p>`
 *   that nests blocks in a paragraph, which no browser accepts: the parser closes the paragraph before the first
 *   block and leaves an empty one behind, visible as a blank gap above the mapped content. The bare token renders
 *   exactly as the whole-field mapping it replaces.
 * - **Nothing at all.** An empty document serializes to `<p></p>`, so storing it verbatim leaves a paragraph in the
 *   rendered page that an author has no way to remove — erasing the text is the one gesture available, and it is
 *   already the gesture that produced this. Emptying the editor must empty the property.
 *
 * Both make the conversion symmetric again, since `ensureBlockLevel()` maps `''` and a bare token to exactly these
 * two documents.
 *
 * Deliberately narrow. Anything the author added — a second block (two empty paragraphs are spacing they typed),
 * text beside the token, a mark on the chip, an alignment on the paragraph — means the paragraph is theirs and is
 * kept, and a mapped value with block markup inside such a text is a shape only they can resolve.
 *
 * @private
 */
function bareStoredValue(body: HTMLElement): string | null {
    const paragraph = loneBareParagraph(body);

    if (paragraph === null) {
        return null;
    }

    const text = (paragraph.textContent ?? '').trim();

    // A trailing `<br>` is how ProseMirror keeps an empty paragraph selectable, not content the author put there.
    if (text === '' && Array.from(paragraph.children).every((child) => child.tagName === 'BR')) {
        return '';
    }

    if (!Array.from(paragraph.childNodes).every((node) => node.nodeType === Node.TEXT_NODE)) {
        return null;
    }

    const match = WHOLE_TEXT_TOKEN_PATTERN.exec(text);

    return match ? canonicalInlineMappingToken(match[1]) : null;
}

/**
 * The document's whole content as a single unadorned paragraph, or null when it is anything else.
 *
 * @private
 */
function loneBareParagraph(body: HTMLElement): HTMLElement | null {
    const content = Array.from(body.childNodes).filter(
        (node) => node.nodeType !== Node.TEXT_NODE || (node.textContent ?? '').trim() !== '',
    );

    if (content.length !== 1) {
        return null;
    }

    const [only] = content;

    if (!(only instanceof HTMLElement) || only.tagName !== 'P' || only.attributes.length > 0) {
        return null;
    }

    return only;
}
