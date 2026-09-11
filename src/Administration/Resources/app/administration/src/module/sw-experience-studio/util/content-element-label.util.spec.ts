import type { ContentElementNode } from 'src/core/service/content-element.types';
import { formatComponentName, getContentElementLabel } from './content-element-label.util';

const ANCHOR_LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
const GERMAN_LANGUAGE_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
const ANCHOR_CHAIN = [ANCHOR_LANGUAGE_ID];
const GERMAN_CHAIN = [
    GERMAN_LANGUAGE_ID,
    ANCHOR_LANGUAGE_ID,
];

function makeElement(properties: Record<string, unknown>): ContentElementNode {
    return {
        component: 'Sw:Product:Slider',
        properties,
    } as ContentElementNode;
}

describe('module/sw-experience-studio/util/content-element-label.util', () => {
    it('returns the chain-head entry of a translatable property as the label', () => {
        expect(
            getContentElementLabel(
                makeElement({
                    title: {
                        [GERMAN_LANGUAGE_ID]: 'Hallo',
                        [ANCHOR_LANGUAGE_ID]: 'Hello',
                    },
                }),
                ANCHOR_CHAIN,
            ),
        ).toBe('Hello');
    });

    it('returns the earliest chain-language entry of a translatable property as the label', () => {
        expect(
            getContentElementLabel(
                makeElement({
                    title: { [ANCHOR_LANGUAGE_ID]: 'Hello' },
                }),
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
                ANCHOR_CHAIN,
            ),
        ).toBe('Fallback title');
    });

    it('falls back to the component name when the only language map carries no chain language', () => {
        expect(
            getContentElementLabel(
                makeElement({
                    title: { [GERMAN_LANGUAGE_ID]: 'Hallo' },
                }),
                ANCHOR_CHAIN,
            ),
        ).toBe('Slider');
    });

    it('returns a bare string property as the label', () => {
        expect(getContentElementLabel(makeElement({ name: 'Headline' }), ANCHOR_CHAIN)).toBe('Headline');
    });

    it('prefers the name key over title when both resolve to a non-blank value', () => {
        expect(
            getContentElementLabel(
                makeElement({
                    name: { [ANCHOR_LANGUAGE_ID]: 'Anchor name' },
                    title: 'Title string',
                }),
                ANCHOR_CHAIN,
            ),
        ).toBe('Anchor name');
    });

    it('formats a namespaced component identifier into its trailing segment', () => {
        expect(formatComponentName('Sw:Product:Slider')).toBe('Slider');
    });
});
