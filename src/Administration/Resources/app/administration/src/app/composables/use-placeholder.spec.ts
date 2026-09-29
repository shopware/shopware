/**
 * @sw-package framework
 */
import usePlaceholder from './use-placeholder';

type LoosePlaceholder = (entity: Record<string, unknown> | null, field: string, fallbackSnippet: string) => unknown;

function getPlaceholder(): LoosePlaceholder {
    return usePlaceholder().placeholder as unknown as LoosePlaceholder;
}

describe('src/app/composables/use-placeholder', () => {
    beforeEach(() => {
        window.Shopware = {
            Utils: { types: { isString: (value: unknown) => typeof value === 'string' } },
            Context: { api: { language: { parentId: 'parent-id' } } },
        } as unknown as typeof Shopware;
    });

    it('returns the fallback snippet when the entity is missing', () => {
        expect(getPlaceholder()(null, 'name', 'fallback')).toBe('fallback');
    });

    it('returns the direct field when it is a non-empty string', () => {
        const entity = { id: '1', name: 'Direct name' };

        expect(getPlaceholder()(entity, 'name', 'fallback')).toBe('Direct name');
    });

    it('falls back to the parent translation when the direct field is empty', () => {
        const entity = {
            id: '1',
            name: '',
            translations: [{ id: '1-parent-id', name: 'Parent name' }],
        };

        expect(getPlaceholder()(entity, 'name', 'fallback')).toBe('Parent name');
    });

    it('uses the translated field when present and no parent translation matches', () => {
        const entity = {
            id: '1',
            name: '',
            translations: [],
            translated: { name: 'Translated name' },
        };

        expect(getPlaceholder()(entity, 'name', 'fallback')).toBe('Translated name');
    });

    it('returns the fallback snippet when nothing resolves', () => {
        const entity = { id: '1', name: '', translations: [], translated: {} };

        expect(getPlaceholder()(entity, 'name', 'fallback')).toBe('fallback');
    });
});
