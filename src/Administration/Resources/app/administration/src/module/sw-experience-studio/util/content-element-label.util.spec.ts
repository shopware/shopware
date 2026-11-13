import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';
import type { ContentElementNode } from 'src/core/service/content-element.types';
import { getContentElementLabel } from './content-element-label.util';

const SYSTEM_LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
const GERMAN_LANGUAGE_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
const SYSTEM_CHAIN = [SYSTEM_LANGUAGE_ID];
const GERMAN_CHAIN = [
    GERMAN_LANGUAGE_ID,
    SYSTEM_LANGUAGE_ID,
];

function makeElement(properties: Record<string, unknown>): ContentElementNode {
    return {
        component: 'Sw:Product:Slider',
        properties,
    } as ContentElementNode;
}

function declare(declarations: Record<string, { translatable: boolean }>): Record<string, ContentSystemElementTypeProperty> {
    return declarations as unknown as Record<string, ContentSystemElementTypeProperty>;
}

describe('module/sw-experience-studio/util/content-element-label.util', () => {
    it('returns the chain-head entry of a declared translatable property as the label', () => {
        expect(
            getContentElementLabel(
                makeElement({
                    title: {
                        [GERMAN_LANGUAGE_ID]: 'Hallo',
                        [SYSTEM_LANGUAGE_ID]: 'Hello',
                    },
                }),
                declare({ title: { translatable: true } }),
                SYSTEM_CHAIN,
            ),
        ).toBe('Hello');
    });

    it('returns the earliest chain-language entry of a declared translatable property as the label', () => {
        expect(
            getContentElementLabel(
                makeElement({
                    title: { [SYSTEM_LANGUAGE_ID]: 'Hello' },
                }),
                declare({ title: { translatable: true } }),
                GERMAN_CHAIN,
            ),
        ).toBe('Hello');
    });

    it('falls through to the next key when the language map carries no chain language', () => {
        expect(
            getContentElementLabel(
                makeElement({
                    // the unresolvable map sits on the first key checked, so the fallback proves the fall-through
                    name: { [GERMAN_LANGUAGE_ID]: 'Hallo' },
                    title: 'Fallback title',
                }),
                declare({ name: { translatable: true }, title: { translatable: false } }),
                SYSTEM_CHAIN,
            ),
        ).toBe('Fallback title');
    });

    it('falls back to the component name when the only language map carries no chain language', () => {
        expect(
            getContentElementLabel(
                makeElement({
                    title: { [GERMAN_LANGUAGE_ID]: 'Hallo' },
                }),
                declare({ title: { translatable: true } }),
                SYSTEM_CHAIN,
            ),
        ).toBe('Slider');
    });

    it.each([
        [
            'boolean',
            true,
        ],
        [
            'number',
            42,
        ],
    ])('skips a key whose language map resolves to a %s entry', (_label, entry) => {
        expect(
            getContentElementLabel(
                makeElement({
                    name: { [SYSTEM_LANGUAGE_ID]: entry },
                    title: 'Fallback title',
                }),
                declare({ name: { translatable: true }, title: { translatable: false } }),
                SYSTEM_CHAIN,
            ),
        ).toBe('Fallback title');
    });

    it('throws when a declared translatable label key carries a language map with a null entry', () => {
        expect(() =>
            getContentElementLabel(
                makeElement({
                    name: { [SYSTEM_LANGUAGE_ID]: null },
                    title: 'Fallback title',
                }),
                declare({ name: { translatable: true }, title: { translatable: false } }),
                SYSTEM_CHAIN,
            ),
        ).toThrow(/must be undefined or a non-empty language map of primitive values/);
    });

    it('skips an object value of a non-translatable label key and uses the next key', () => {
        expect(
            getContentElementLabel(
                makeElement({
                    name: { first: 'Ada', last: 'Lovelace' },
                    title: 'Fallback title',
                }),
                declare({ name: { translatable: false }, title: { translatable: false } }),
                SYSTEM_CHAIN,
            ),
        ).toBe('Fallback title');
    });

    it('uses only string values when the element type is not in the store', () => {
        expect(
            getContentElementLabel(
                makeElement({
                    name: { [SYSTEM_LANGUAGE_ID]: 'Hello' },
                    title: 'Fallback title',
                }),
                undefined,
                SYSTEM_CHAIN,
            ),
        ).toBe('Fallback title');
    });

    it('returns a bare string property as the label', () => {
        expect(
            getContentElementLabel(
                makeElement({ name: 'Headline' }),
                declare({ name: { translatable: false } }),
                SYSTEM_CHAIN,
            ),
        ).toBe('Headline');
    });

    it('prefers the name key over title when both resolve to a non-blank value', () => {
        expect(
            getContentElementLabel(
                makeElement({
                    name: { [SYSTEM_LANGUAGE_ID]: 'System-language name' },
                    title: 'Title string',
                }),
                declare({ name: { translatable: true }, title: { translatable: false } }),
                SYSTEM_CHAIN,
            ),
        ).toBe('System-language name');
    });

    it('returns the type-label fallback when the only language map carries no chain language', () => {
        expect(
            getContentElementLabel(
                makeElement({
                    title: { [GERMAN_LANGUAGE_ID]: 'Hallo' },
                }),
                declare({ title: { translatable: true } }),
                SYSTEM_CHAIN,
                'Bild',
            ),
        ).toBe('Bild');
    });

    it('returns the type-label fallback for an element without an authored label', () => {
        expect(
            getContentElementLabel(
                { id: 'element-1', component: 'Sw:Media:Image', properties: {} },
                declare({ title: { translatable: false } }),
                SYSTEM_CHAIN,
                'Bild',
            ),
        ).toBe('Bild');
    });

    it('prefers an authored label over the type-label fallback', () => {
        expect(
            getContentElementLabel(
                { id: 'element-1', component: 'Sw:Media:Image', properties: { title: 'Mein Bild' } },
                declare({ title: { translatable: false } }),
                SYSTEM_CHAIN,
                'Bild',
            ),
        ).toBe('Mein Bild');
    });

    it('returns the trailing component segment for an unnamed element without a type-label fallback', () => {
        expect(
            getContentElementLabel(
                { id: 'element-1', component: 'Sw:Media:Image', properties: {} },
                undefined,
                SYSTEM_CHAIN,
            ),
        ).toBe('Image');
    });
});
