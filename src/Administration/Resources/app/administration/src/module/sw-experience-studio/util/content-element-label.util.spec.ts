import type { ContentElementNode } from 'src/core/service/content-element.types';
import { formatComponentName, getContentElementLabel } from './content-element-label.util';

const ANCHOR_LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
const GERMAN_LANGUAGE_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

function makeElement(properties: Record<string, unknown>): ContentElementNode {
    return {
        component: 'Sw:Product:Slider',
        properties,
    } as ContentElementNode;
}

describe('module/sw-experience-studio/util/content-element-label.util', () => {
    it('returns the anchor entry of a translatable property as the label', () => {
        expect(
            getContentElementLabel(
                makeElement({
                    title: {
                        [GERMAN_LANGUAGE_ID]: 'Hallo',
                        [ANCHOR_LANGUAGE_ID]: 'Hello',
                    },
                }),
            ),
        ).toBe('Hello');
    });

    it('falls through to the next key when the language map has no anchor entry', () => {
        expect(
            getContentElementLabel(
                makeElement({
                    title: { [GERMAN_LANGUAGE_ID]: 'Hallo' },
                    name: 'Fallback name',
                }),
            ),
        ).toBe('Fallback name');
    });

    it('falls back to the component name when the only language map has no anchor entry', () => {
        expect(
            getContentElementLabel(
                makeElement({
                    title: { [GERMAN_LANGUAGE_ID]: 'Hallo' },
                }),
            ),
        ).toBe('Slider');
    });

    it('returns a bare string property as the label', () => {
        expect(getContentElementLabel(makeElement({ name: 'Headline' }))).toBe('Headline');
    });

    it('prefers the name key over title when both resolve to a non-blank value', () => {
        expect(
            getContentElementLabel(
                makeElement({
                    name: { [ANCHOR_LANGUAGE_ID]: 'Anchor name' },
                    title: 'Title string',
                }),
            ),
        ).toBe('Anchor name');
    });

    it('formats a namespaced component identifier into its trailing segment', () => {
        expect(formatComponentName('Sw:Product:Slider')).toBe('Slider');
    });
});
