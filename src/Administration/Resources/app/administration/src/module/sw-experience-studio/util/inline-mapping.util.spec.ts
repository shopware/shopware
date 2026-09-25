import {
    canonicalInlineMappingToken,
    editorHtmlToTokens,
    parseInlineMappingTokens,
    tokensToEditorHtml,
} from './inline-mapping.util';

const KNOWN = new Set([
    'product.name',
    'product.price',
    'product.customFields.swag_color',
]);
const isKnownPath = (path: string) => KNOWN.has(path);

describe('module/sw-experience-studio/util/inline-mapping.util', () => {
    it.each([
        [
            'a bare token',
            '{{map:product.name}}',
            ['product.name'],
        ],
        [
            'surrounding prose',
            '<p>Buy the {{map:product.name}} today, only {{map:product.price}}.</p>',
            [
                'product.name',
                'product.price',
            ],
        ],
        [
            'internal whitespace',
            '{{ map:product.name }}',
            ['product.name'],
        ],
        [
            'a custom field path',
            '{{map:product.customFields.swag_color}}',
            ['product.customFields.swag_color'],
        ],
        [
            'the same path twice',
            '{{map:product.name}} and {{map:product.name}}',
            [
                'product.name',
                'product.name',
            ],
        ],
        [
            'an ordinary placeholder',
            '{{ product.name }}',
            [],
        ],
        [
            'an undotted path',
            '{{map:product}}',
            [],
        ],
        [
            'a prefix without braces',
            'map:product.name',
            [],
        ],
        [
            'a path with a dash',
            '{{map:product.some-name}}',
            [],
        ],
    ])('lists the mapping paths in %s', (_name, text, expected) => {
        expect(parseInlineMappingTokens(text)).toEqual(expected);
    });

    it('turns known tokens into nodes and leaves unknown ones as text', () => {
        const html = tokensToEditorHtml(
            '<p>Buy the {{map:product.name}} in {{map:product.nmae}} today.</p>',
            isKnownPath,
        );

        expect(html).toBe('<p>Buy the <span data-sw-map="product.name"></span> in {{map:product.nmae}} today.</p>');
    });

    it('normalises whitespace inside a token when converting to a node', () => {
        expect(tokensToEditorHtml('{{  map:product.name  }}', isKnownPath)).toBe(
            '<p><span data-sw-map="product.name"></span></p>',
        );
    });

    /**
     * tiptap's document schema is `block+`, so it puts top-level inline content into a paragraph when it parses —
     * and `mt-text-editor` locks itself behind a review gate whenever parsing changes the markup it was given.
     * Wrapping here is what keeps a whole-text mapping, stored as one bare token, editable.
     */
    it.each([
        [
            'a bare token',
            '{{map:product.name}}',
            '<p><span data-sw-map="product.name"></span></p>',
        ],
        [
            'bare prose',
            'Just words.',
            '<p>Just words.</p>',
        ],
        [
            'bare inline markup',
            '<strong>Bold</strong> words',
            '<p><strong>Bold</strong> words</p>',
        ],
        [
            'an empty value',
            '',
            '<p></p>',
        ],
        [
            'a whitespace-only value',
            '   ',
            '<p></p>',
        ],
    ])('wraps %s in a paragraph', (_name, stored, expected) => {
        expect(tokensToEditorHtml(stored, isKnownPath)).toBe(expected);
    });

    it.each([
        ['a paragraph', '<p>{{map:product.name}}</p>'],
        ['a heading', '<h2>Title</h2>'],
        ['several blocks', '<p>One</p><p>Two</p>'],
        ['a list', '<ul><li><p>One</p></li></ul>'],
    ])('leaves %s alone, because it already has a block structure', (_name, stored) => {
        expect(tokensToEditorHtml(stored, () => false)).toBe(stored);
    });

    it('leaves an ordinary placeholder untouched', () => {
        expect(tokensToEditorHtml('<p>Hello {{ product.name }}</p>', isKnownPath)).toBe('<p>Hello {{ product.name }}</p>');
    });

    it('turns nodes back into tokens, discarding the rendered label', () => {
        const stored = editorHtmlToTokens(
            '<p>Buy the <span data-sw-map="product.name" class="chip">Product name</span> today.</p>',
        );

        expect(stored).toBe('<p>Buy the {{map:product.name}} today.</p>');
    });

    it('reads the path regardless of attribute order', () => {
        const stored = editorHtmlToTokens('<span class="chip" contenteditable="false" data-sw-map="product.price">9</span>');

        expect(stored).toBe('{{map:product.price}}');
    });

    it('unwraps a node whose path is not a dotted path rather than dropping the text', () => {
        expect(editorHtmlToTokens('<p>a <span data-sw-map="nonsense">kept words</span> b</p>')).toBe(
            '<p>a kept words b</p>',
        );
    });

    it('keeps a token the author typed as text, so typing one by hand works', () => {
        expect(editorHtmlToTokens('<p>Buy the {{map:product.name}} today.</p>')).toBe(
            '<p>Buy the {{map:product.name}} today.</p>',
        );
    });

    it('round-trips a stored text through the editor representation', () => {
        const stored = '<p>Buy the {{map:product.name}} today, only {{map:product.price}}.</p>';

        expect(editorHtmlToTokens(tokensToEditorHtml(stored, isKnownPath))).toBe(stored);
    });

    it('round-trips a text whose tokens are all unknown', () => {
        const stored = '<p>Buy the {{map:product.nmae}} today.</p>';

        expect(editorHtmlToTokens(tokensToEditorHtml(stored, isKnownPath))).toBe(stored);
    });

    /**
     * The paragraph the editor needs must not survive into storage. A mapped value brings the markup it was authored
     * with — a description is a heading and several paragraphs — and blocks nested in a `<p>` are markup no browser
     * accepts: it closes the paragraph early and leaves an empty one above the mapped content.
     */
    it('round-trips a whole-text mapping to the bare token it was stored as', () => {
        expect(editorHtmlToTokens(tokensToEditorHtml('{{map:product.name}}', isKnownPath))).toBe(
            '{{map:product.name}}',
        );
    });

    it('takes the paragraph off a text that holds nothing but a token', () => {
        expect(editorHtmlToTokens('<p><span data-sw-map="product.name">Product name</span></p>')).toBe(
            '{{map:product.name}}',
        );
    });

    /**
     * An emptied editor must empty the property. Its document serializes to `<p></p>`, and storing that leaves a
     * paragraph in the rendered page the author cannot get rid of — erasing the text is the only gesture they have,
     * and it is the one that produced it.
     */
    it.each([
        ['an emptied document', '<p></p>'],
        ['the trailing break ProseMirror keeps an empty paragraph selectable with', '<p><br></p>'],
        ['a paragraph holding only whitespace', '<p>   </p>'],
        ['nothing at all', ''],
    ])('stores %s as an empty value', (_name, editorHtml) => {
        expect(editorHtmlToTokens(editorHtml)).toBe('');
    });

    it('round-trips an empty value', () => {
        expect(editorHtmlToTokens(tokensToEditorHtml('', isKnownPath))).toBe('');
    });

    it('keeps empty paragraphs the author typed as spacing', () => {
        expect(editorHtmlToTokens('<p></p><p></p>')).toBe('<p></p><p></p>');
    });

    it.each([
        ['text beside it', '<p><span data-sw-map="product.name"></span> on sale</p>', '<p>{{map:product.name}} on sale</p>'],
        [
            'a second block',
            '<p><span data-sw-map="product.name"></span></p><p>Now cheaper</p>',
            '<p>{{map:product.name}}</p><p>Now cheaper</p>',
        ],
        [
            'a mark on the chip',
            '<p><strong><span data-sw-map="product.name"></span></strong></p>',
            '<p><strong>{{map:product.name}}</strong></p>',
        ],
        [
            'an alignment on the paragraph',
            '<p style="text-align: center"><span data-sw-map="product.name"></span></p>',
            '<p style="text-align: center">{{map:product.name}}</p>',
        ],
    ])('keeps the paragraph when the author put %s there', (_name, editorHtml, expected) => {
        expect(editorHtmlToTokens(editorHtml)).toBe(expected);
    });

    it('writes the canonical token form', () => {
        expect(canonicalInlineMappingToken('product.name')).toBe('{{map:product.name}}');
    });
});
