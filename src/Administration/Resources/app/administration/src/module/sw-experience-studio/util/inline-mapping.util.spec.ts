import { editorHtmlToTokens, tokensToEditorHtml } from './inline-mapping.util';
import type { ContentSystemInlineMappingToken } from 'src/core/service/api/content-system-mapping-candidate.api.service';

const CANONICAL_TOKENS = new Map([
    [
        'product.name',
        '{{map:product.name}}',
    ],
    [
        'product.price',
        '{{map:product.price}}',
    ],
    [
        'product.nmae',
        '{{map:product.nmae}}',
    ],
    [
        'product.customFields.swag_color',
        '{{map:product.customFields.swag_color}}',
    ],
    [
        'product.customFields.größe',
        '{{map:product.customFields.größe}}',
    ],
    [
        'plugin:example.message',
        '{{map:plugin:example.message}}',
    ],
    [
        'external-source:lookup-id.value',
        '{{map:external-source:lookup-id.value}}',
    ],
]);

function utf8ByteLength(text: string): number {
    let bytes = 0;

    for (const character of text) {
        const codePoint = character.codePointAt(0) ?? 0;
        bytes += codePoint <= 0x7f ? 1 : codePoint <= 0x7ff ? 2 : codePoint <= 0xffff ? 3 : 4;
    }

    return bytes;
}

function parsedTokens(text: string): ContentSystemInlineMappingToken[] {
    const tokens: ContentSystemInlineMappingToken[] = [];

    for (const [
        path,
        canonicalToken,
    ] of CANONICAL_TOKENS) {
        const match = canonicalToken;
        let offset = text.indexOf(match);

        while (offset !== -1) {
            const byteOffset = utf8ByteLength(text.slice(0, offset));
            tokens.push({ path, match, canonicalToken, offset: byteOffset, insideMarkup: false, malformed: false });
            offset = text.indexOf(match, offset + match.length);
        }
    }

    return tokens.sort((first, second) => first.offset - second.offset);
}

describe('module/sw-experience-studio/util/inline-mapping.util', () => {
    it('turns all server-parsed tokens into nodes, including an unavailable path', () => {
        const text = '<p>Buy the {{map:product.name}} in {{map:product.nmae}} today.</p>';
        const { html } = tokensToEditorHtml(text, parsedTokens(text));

        expect(html).toBe(
            '<p>Buy the <span data-sw-map="product.name"></span> in <span data-sw-map="product.nmae"></span> today.</p>',
        );
    });

    it('leaves mapping-looking text literal when the server supplies no token spans', () => {
        const text = '<p>Buy the {{map:product.name}} today.</p>';

        expect(tokensToEditorHtml(text, []).html).toBe(text);
    });

    it('keeps a server-parsed token inside an HTML attribute literal', () => {
        const text = '<a title="{{map:product.name}}">Product</a>';
        const tokens: ContentSystemInlineMappingToken[] = [
            {
                path: 'product.name',
                match: '{{map:product.name}}',
                canonicalToken: '{{map:product.name}}',
                offset: 10,
                insideMarkup: true,
                malformed: false,
            },
        ];

        expect(tokensToEditorHtml(text, tokens).html).toBe(`<p>${text}</p>`);
    });

    it('uses UTF-8 byte spans correctly when text before a token contains an astral character', () => {
        const text = '🙂 {{map:product.name}}';
        const spans = [
            {
                path: 'product.name',
                match: '{{map:product.name}}',
                canonicalToken: '{{map:product.name}}',
                offset: 5,
                insideMarkup: false,
                malformed: false,
            },
        ];

        expect(tokensToEditorHtml(text, spans).html).toBe('<p>🙂 <span data-sw-map="product.name"></span></p>');
    });

    it('normalises whitespace inside a token when converting to a node', () => {
        const text = '{{  map:product.name  }}';
        const spans = [
            {
                path: 'product.name',
                match: text,
                canonicalToken: '{{map:product.name}}',
                offset: 0,
                insideMarkup: false,
                malformed: false,
            },
        ];

        expect(tokensToEditorHtml(text, spans).html).toBe('<p><span data-sw-map="product.name"></span></p>');
    });

    it.each([
        [
            'dotless',
            '{{map:product}}',
            'product',
            '{{map:product}}',
        ],
        [
            'quoted',
            '{{map:x" onmouseover="alert(1)}}',
            'x" onmouseover="alert(1)',
            '{{map:x" onmouseover="alert(1)}}',
        ],
        [
            'markup-bearing',
            '{{map:product.<img src=x onerror=alert(1)>}}',
            'product.<img src=x onerror=alert(1)>',
            '{{map:product.&lt;img src=x onerror=alert(1)&gt;}}',
        ],
    ])('keeps a malformed %s server token as safe text', (_case, text, path, expected) => {
        const tokens: ContentSystemInlineMappingToken[] = [
            {
                path,
                match: text,
                canonicalToken: null,
                offset: 0,
                insideMarkup: false,
                malformed: true,
            },
        ];

        const { html } = tokensToEditorHtml(text, tokens);

        expect(html).toBe(`<p>${expected}</p>`);
        expect(new DOMParser().parseFromString(html, 'text/html').querySelector('img')).toBeNull();
    });

    it('serializes chip attributes safely if a span contains an unexpected quoted path', () => {
        const path = 'x" onmouseover="alert(1)';
        const text = `{{map:${path}}}`;
        const tokens: ContentSystemInlineMappingToken[] = [
            {
                path,
                match: text,
                canonicalToken: text,
                offset: 0,
                insideMarkup: false,
                malformed: false,
            },
        ];

        const { html } = tokensToEditorHtml(text, tokens);
        const chip = new DOMParser().parseFromString(html, 'text/html').querySelector('span');

        expect(chip?.getAttribute('data-sw-map')).toBe(path);
        expect(chip?.getAttribute('onmouseover')).toBeNull();
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
        expect(tokensToEditorHtml(stored, parsedTokens(stored)).html).toBe(expected);
    });

    it.each([
        [
            'a paragraph',
            '<p>{{map:product.name}}</p>',
            '<p><span data-sw-map="product.name"></span></p>',
        ],
        [
            'a heading',
            '<h2>Title</h2>',
            '<h2>Title</h2>',
        ],
        [
            'several blocks',
            '<p>One</p><p>Two</p>',
            '<p>One</p><p>Two</p>',
        ],
        [
            'a list',
            '<ul><li><p>One</p></li></ul>',
            '<ul><li><p>One</p></li></ul>',
        ],
    ])('preserves %s block structure while converting parsed tokens', (_name, stored, expected) => {
        expect(tokensToEditorHtml(stored, parsedTokens(stored)).html).toBe(expected);
    });

    it('turns nodes back into tokens, discarding the rendered label', () => {
        const stored = editorHtmlToTokens(
            '<p>Buy the <span data-sw-map="product.name" class="chip">Product name</span> today.</p>',
            CANONICAL_TOKENS,
        );

        expect(stored).toBe('<p>Buy the {{map:product.name}} today.</p>');
    });

    it('reads the path regardless of attribute order', () => {
        const stored = editorHtmlToTokens(
            '<span class="chip" contenteditable="false" data-sw-map="product.price">9</span>',
            CANONICAL_TOKENS,
        );

        expect(stored).toBe('{{map:product.price}}');
    });

    it.each([
        'nonsense',
        'product.other',
    ])('unwraps a chip whose path %s is absent from the canonical token map', (path) => {
        expect(editorHtmlToTokens(`<p>a <span data-sw-map="${path}">kept words</span> b</p>`, CANONICAL_TOKENS)).toBe(
            '<p>a kept words b</p>',
        );
    });

    it('keeps a token the author typed as text, so typing one by hand works', () => {
        expect(editorHtmlToTokens('<p>Buy the {{map:product.name}} today.</p>', CANONICAL_TOKENS)).toBe(
            '<p>Buy the {{map:product.name}} today.</p>',
        );
    });

    it('round-trips a stored text through the editor representation', () => {
        const stored = '<p>Buy the {{map:product.name}} today, only {{map:product.price}}.</p>';

        const editorValue = tokensToEditorHtml(stored, parsedTokens(stored));

        expect(editorHtmlToTokens(editorValue.html, CANONICAL_TOKENS, editorValue.addedParagraph)).toBe(stored);
    });

    it('round-trips a Unicode custom field path through the editor representation', () => {
        const stored = '<p>Size: {{map:product.customFields.größe}}</p>';

        const editorValue = tokensToEditorHtml(stored, parsedTokens(stored));

        expect(editorHtmlToTokens(editorValue.html, CANONICAL_TOKENS, editorValue.addedParagraph)).toBe(stored);
    });

    it('round-trips a server-recognized token whose source is unavailable', () => {
        const stored = '<p>Buy the {{map:product.nmae}} today.</p>';

        const editorValue = tokensToEditorHtml(stored, parsedTokens(stored));

        expect(editorHtmlToTokens(editorValue.html, CANONICAL_TOKENS, editorValue.addedParagraph)).toBe(stored);
    });

    /**
     * The paragraph the editor needs must not survive into storage. A mapped value brings the markup it was authored
     * with — a description is a heading and several paragraphs — and blocks nested in a `<p>` are markup no browser
     * accepts: it closes the paragraph early and leaves an empty one above the mapped content.
     */
    it('round-trips a whole-text mapping to the bare token it was stored as', () => {
        const stored = '{{map:product.name}}';
        const editorValue = tokensToEditorHtml(stored, parsedTokens(stored));

        expect(editorHtmlToTokens(editorValue.html, CANONICAL_TOKENS, editorValue.addedParagraph)).toBe(stored);
    });

    it('preserves a token-only paragraph that was already stored', () => {
        const stored = '<p>{{map:product.name}}</p>';
        const editorValue = tokensToEditorHtml(stored, parsedTokens(stored));

        expect(editorHtmlToTokens(editorValue.html, CANONICAL_TOKENS, editorValue.addedParagraph)).toBe(stored);
    });

    it('preserves a token-only paragraph when its ownership is unknown', () => {
        expect(editorHtmlToTokens('<p><span data-sw-map="product.name">Product name</span></p>', CANONICAL_TOKENS)).toBe(
            '<p>{{map:product.name}}</p>',
        );
    });

    it('takes off a known editor-added paragraph that holds nothing but a token', () => {
        expect(
            editorHtmlToTokens('<p><span data-sw-map="product.name">Product name</span></p>', CANONICAL_TOKENS, true),
        ).toBe('{{map:product.name}}');
    });

    /**
     * An emptied editor must empty the property. Its document serializes to `<p></p>`, and storing that leaves a
     * paragraph in the rendered page the author cannot get rid of — erasing the text is the only gesture they have,
     * and it is the one that produced it.
     */
    it.each([
        [
            'an emptied document',
            '<p></p>',
        ],
        [
            'the trailing break ProseMirror keeps an empty paragraph selectable with',
            '<p><br></p>',
        ],
        [
            'a paragraph holding only whitespace',
            '<p>   </p>',
        ],
        [
            'nothing at all',
            '',
        ],
    ])('stores %s as an empty value', (_name, editorHtml) => {
        expect(editorHtmlToTokens(editorHtml, CANONICAL_TOKENS)).toBe('');
    });

    it('round-trips an empty value', () => {
        const editorValue = tokensToEditorHtml('', []);

        expect(editorHtmlToTokens(editorValue.html, CANONICAL_TOKENS, editorValue.addedParagraph)).toBe('');
    });

    it('keeps empty paragraphs the author typed as spacing', () => {
        expect(editorHtmlToTokens('<p></p><p></p>', CANONICAL_TOKENS)).toBe('<p></p><p></p>');
    });

    it.each([
        [
            'text beside it',
            '<p><span data-sw-map="product.name"></span> on sale</p>',
            '<p>{{map:product.name}} on sale</p>',
        ],
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
        expect(editorHtmlToTokens(editorHtml, CANONICAL_TOKENS, true)).toBe(expected);
    });

    it('serializes the exact canonical token text returned by the server', () => {
        const serverCanonicalToken = 'SERVER-CANONICAL-TOKEN';
        const canonicalTokens = new Map([
            [
                'product.name',
                serverCanonicalToken,
            ],
        ]);

        expect(
            editorHtmlToTokens('<p><span data-sw-map="product.name">Product name</span></p>', canonicalTokens, true),
        ).toBe(serverCanonicalToken);
    });
});
