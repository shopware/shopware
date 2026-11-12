/**
 * @sw-package framework
 */
import { BC_CHANGE_KINDS, parseMarkers, parseTemplateMarkers } from './api-markers';
import type { MarkerMatch } from './api-markers';

function markersOf(matches: MarkerMatch[]) {
    return matches.map((match) => ('marker' in match ? match.marker : match.errors));
}

function errorsOf(comment: string): string[] {
    return parseMarkers(comment).flatMap((match) => ('errors' in match ? match.errors : []));
}

const DEPRECATED_TEXT_ERROR = '@deprecated needs "- <text>" after the version, for example "- Use load instead".';

describe('parseMarkers', () => {
    it('reads a deprecation from a JSDoc comment', () => {
        const comment = '*\n * Loads the sales channel.\n *\n * @deprecated tag:v6.8.0 - Use {@link load} instead.\n ';

        expect(markersOf(parseMarkers(comment))).toEqual([{ tag: 'deprecated', version: 'v6.8.0' }]);
    });

    it.each([
        ['a one-line JSDoc comment', '* @deprecated tag:v6.8.0 - Will be removed '],
        ['a line comment', ' @deprecated tag:v6.8.0 - Will be removed'],
        ['a Twig comment', ' @deprecated tag:v6.8.0 - Will be removed '],
    ])('reads a deprecation from the content of %s', (_, comment) => {
        expect(markersOf(parseMarkers(comment))).toEqual([{ tag: 'deprecated', version: 'v6.8.0' }]);
    });

    it('reads a planned change with its parameters', () => {
        const comment = [
            '*',
            ' * @bc-change ParameterDefaultValueChange version:v6.8.0 parameter:limit default:25',
            ' *   The default page size goes down from 50.',
            ' ',
        ].join('\n');

        expect(markersOf(parseMarkers(comment))).toEqual([
            {
                tag: 'bc-change',
                kind: 'ParameterDefaultValueChange',
                version: 'v6.8.0',
                parameters: { parameter: 'limit', default: '25' },
            },
        ]);
    });

    it('reads several markers from one comment', () => {
        const comment = [
            '*',
            ' * @bc-change ParameterRemoval version:v6.8.0 parameter:itemIndex',
            ' * @bc-change BecomesPrivate version:v6.9.0',
            ' ',
        ].join('\n');

        expect(markersOf(parseMarkers(comment))).toEqual([
            { tag: 'bc-change', kind: 'ParameterRemoval', version: 'v6.8.0', parameters: { parameter: 'itemIndex' } },
            { tag: 'bc-change', kind: 'BecomesPrivate', version: 'v6.9.0', parameters: {} },
        ]);
    });

    it.each(Object.entries(BC_CHANGE_KINDS))('accepts %s with exactly its keys', (kind, keys) => {
        const parameters = Object.fromEntries(keys.map((key) => [key, 'value']));
        const words = keys.map((key) => `${key}:value`).join(' ');

        expect(markersOf(parseMarkers(` @bc-change ${kind} version:v6.8.0 ${words}`))).toEqual([
            { tag: 'bc-change', kind, version: 'v6.8.0', parameters },
        ]);
    });

    it.each(Object.entries(BC_CHANGE_KINDS).filter(([, keys]) => keys.length > 0))(
        'reports %s without its keys',
        (kind, keys) => {
            expect(errorsOf(` @bc-change ${kind} version:v6.8.0`)).toEqual(
                keys.map((key) => `${kind} needs "${key}:<value>".`),
            );
        },
    );

    it.each([
        ['@deprecated', '@deprecated needs "tag:vX.Y.Z" directly after the tag name.'],
        ['@deprecated Use load instead', '@deprecated needs "tag:vX.Y.Z" directly after the tag name.'],
        ['@deprecated tag:v6.8.0.0', '"v6.8.0.0" is not a version of the form vX.Y.Z, for example v6.8.0.'],
        ['@deprecated tag:6.8.0', '"6.8.0" is not a version of the form vX.Y.Z, for example v6.8.0.'],
        ['@deprecated tag:v6.8', '"v6.8" is not a version of the form vX.Y.Z, for example v6.8.0.'],
        ['@deprecated tag:', '"" is not a version of the form vX.Y.Z, for example v6.8.0.'],
        ['@deprecated tag:v6.8.0', DEPRECATED_TEXT_ERROR],
        ['@deprecated tag:v6.8.0 removed soon', DEPRECATED_TEXT_ERROR],
        ['@deprecated tag:v6.8.0 -', DEPRECATED_TEXT_ERROR],
        ['@deprecated tag:v6.8.0 -- There is no replacement', DEPRECATED_TEXT_ERROR],
        ['@deprecated tag:v6.8.0 -Use load instead', DEPRECATED_TEXT_ERROR],
        ['@deprecated: tag:v6.8.0 - Use load instead', '"@deprecated" must not be followed by ":".'],
        ['@deprecated:tag:v6.8.0 - Use load instead', '"@deprecated" must not be followed by ":".'],
        ['@deprecated, tag:v6.8.0 - Use load instead', '"@deprecated" must not be followed by ",".'],
        ['@bc-change: BecomesPrivate version:v6.8.0', '"@bc-change" must not be followed by ":".'],
        ['@deprecate tag:v6.8.0', '"@deprecate" is not a tag, did you mean "@deprecated"?'],
        ['@depreciated tag:v6.8.0', '"@depreciated" is not a tag, did you mean "@deprecated"?'],
        ['@Deprecated tag:v6.8.0', '"@Deprecated" is not a tag, did you mean "@deprecated"?'],
        ['@bc-chnage BlockMoved version:v6.8.0', '"@bc-chnage" is not a tag, did you mean "@bc-change"?'],
        ['@bcchange BlockMoved version:v6.8.0', '"@bcchange" is not a tag, did you mean "@bc-change"?'],
        [
            '@bc-change',
            `@bc-change needs a kind, one of: ${Object.keys(BC_CHANGE_KINDS).join(', ')}.`,
        ],
        [
            '@bc-change ParamterRemoval version:v6.8.0 parameter:itemIndex',
            '"ParamterRemoval" is not a @bc-change kind, did you mean "ParameterRemoval"?',
        ],
        [
            '@bc-change blockmoved version:v6.8.0 parent:sw_card',
            '"blockmoved" is not a @bc-change kind, did you mean "BlockMoved"?',
        ],
        [
            '@bc-change constructor version:v6.8.0',
            `"constructor" is not a @bc-change kind, use one of: ${Object.keys(BC_CHANGE_KINDS).join(', ')}.`,
        ],
        [
            '@bc-change BecomesPrivate version:v6.8.0 constructor:x',
            'BecomesPrivate has no key "constructor", it takes no keys.',
        ],
        ['@bc-change BecomesPrivate', '@bc-change needs "version:vX.Y.Z".'],
        ['@bc-change BecomesPrivate tag:v6.8.0', '@bc-change needs "version:vX.Y.Z".'],
        [
            '@bc-change BecomesPrivate version:v6.8.0.0',
            '"v6.8.0.0" is not a version of the form vX.Y.Z, for example v6.8.0.',
        ],
        [
            '@bc-change BecomesPrivate version:v6.8.0 parameter:itemIndex',
            'BecomesPrivate has no key "parameter", it takes no keys.',
        ],
        [
            '@bc-change BlockMoved version:v6.8.0 parent:sw_card variable:item',
            'BlockMoved has no key "variable", it takes: parent.',
        ],
        ['@bc-change BlockMoved version:v6.8.0 parent:sw_card parent:sw_modal', '"parent" is given twice.'],
        [
            '@bc-change BecomesPrivate version:v6.8.0 soon',
            '"soon" is not of the form key:value. Free text starts on the line after the marker.',
        ],
    ])('reports "%s"', (line, error) => {
        expect(errorsOf(` * ${line}`)).toContain(error);
    });

    it('reports a missing kind, not a missing version, when the marker starts with the version', () => {
        expect(errorsOf(' @bc-change version:v6.8.0 parameter:itemIndex')).toEqual([
            `@bc-change needs a kind, one of: ${Object.keys(BC_CHANGE_KINDS).join(', ')}.`,
        ]);
    });

    it('reports every problem of one marker', () => {
        expect(errorsOf(' @bc-change ParameterRemoval version:6.8 variable:item')).toEqual([
            '"6.8" is not a version of the form vX.Y.Z, for example v6.8.0.',
            'ParameterRemoval needs "parameter:<value>".',
            'ParameterRemoval has no key "variable", it takes: parameter.',
        ]);
    });

    it('reports a stray punctuation mark together with the problems of the marker behind it', () => {
        expect(errorsOf(' @deprecated: tag:v6.8')).toEqual([
            '"@deprecated" must not be followed by ":".',
            '"v6.8" is not a version of the form vX.Y.Z, for example v6.8.0.',
            DEPRECATED_TEXT_ERROR,
        ]);
    });

    it.each([
        ['an unknown tag followed by a colon', '*\n * @example: load()\n '],
        ['another tag', '*\n * @private\n * @param {string} id\n * @description Deprecated helpers.\n '],
        ['a tag inside a sentence', ' The old way is marked @deprecated and the kinds come from @bc-change.'],
        ['a link to the tag', ' See {@link deprecated} for details.'],
        ['a JSDoc tag that is also an object property', '*\n * @constructor\n * @toString\n '],
        ['a tag two edits away from a marker tag', '*\n * @change Emitted when the value changes\n '],
        ['a Vue listener in commented-out markup', '\n<sw-field\n    @change="onChange"\n    @deprecate="onDeprecate"\n/>'],
    ])('does not treat %s as a marker', (_, comment) => {
        expect(parseMarkers(comment)).toEqual([]);
    });

    it('locates the marker line from its @ on', () => {
        const comment = '*\n * @bc-change BecomesPrivate version:v6.8.0   \n ';
        const [{ offset, length }] = parseMarkers(comment);

        expect(comment.slice(offset, offset + length)).toBe('@bc-change BecomesPrivate version:v6.8.0');
    });
});

describe('parseTemplateMarkers', () => {
    it('reads markers from Twig and HTML comments', () => {
        const template = [
            '{# @bc-change BlockMoved version:v6.8.0 parent:sw_example_card_header #}',
            '{% block sw_example_card_title %}',
            '    <!-- @deprecated tag:v6.8.0 - Use the title prop instead -->',
            '    {#- @deprecated tag:v6.9.0 - Use the header slot instead -#}',
            '{% endblock %}',
        ].join('\n');

        expect(markersOf(parseTemplateMarkers(template))).toEqual([
            {
                tag: 'bc-change',
                kind: 'BlockMoved',
                version: 'v6.8.0',
                parameters: { parent: 'sw_example_card_header' },
            },
            { tag: 'deprecated', version: 'v6.8.0' },
            { tag: 'deprecated', version: 'v6.9.0' },
        ]);
    });

    it('reads a marker from a comment that spans lines', () => {
        const template = '{#\n    @deprecated tag:v6.8.0 - Use sw_example_card_title instead\n#}\n<div></div>';

        expect(markersOf(parseTemplateMarkers(template))).toEqual([{ tag: 'deprecated', version: 'v6.8.0' }]);
    });

    it('ignores markup and text outside comments', () => {
        expect(parseTemplateMarkers('<p>\n@deprecated tag:broken\n</p>')).toEqual([]);
    });

    it('locates a malformed marker in the template', () => {
        const template = '<div>\n    {# @bc-chnage BlockMoved version:v6.8.0 parent:sw_card #}\n</div>';
        const [match] = parseTemplateMarkers(template);

        expect(match).toEqual(
            expect.objectContaining({ errors: ['"@bc-chnage" is not a tag, did you mean "@bc-change"?'] }),
        );
        expect(template.slice(match.offset, match.offset + match.length)).toBe(
            '@bc-chnage BlockMoved version:v6.8.0 parent:sw_card',
        );
    });
});
