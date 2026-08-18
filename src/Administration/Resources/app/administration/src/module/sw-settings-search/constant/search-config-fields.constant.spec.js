import { SEARCH_CONFIG_FIELD_SNIPPETS } from './search-config-fields.constant';

/**
 * @sw-package inventory
 */

describe('search-config-fields.constant', () => {
    describe('SEARCH_CONFIG_FIELD_SNIPPETS', () => {
        it('maps every searchable field to its configFields snippet key', () => {
            expect(Object.keys(SEARCH_CONFIG_FIELD_SNIPPETS)).toHaveLength(16);
            expect(SEARCH_CONFIG_FIELD_SNIPPETS.name).toBe('name');
            expect(SEARCH_CONFIG_FIELD_SNIPPETS['manufacturer.name']).toBe('manufacturerName');
            expect(SEARCH_CONFIG_FIELD_SNIPPETS['options.name']).toBe('variantValue');
        });

        it('is frozen so consumers cannot drift the shared map', () => {
            expect(Object.isFrozen(SEARCH_CONFIG_FIELD_SNIPPETS)).toBe(true);
        });
    });
});
