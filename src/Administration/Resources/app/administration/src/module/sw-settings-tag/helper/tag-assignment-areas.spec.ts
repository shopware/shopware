/**
 * @sw-package inventory
 */
import { getTagAssignmentAreas } from './tag-assignment-areas';

describe('src/module/sw-settings-tag/helper/tag-assignment-areas', () => {
    it('should build one area per many-to-many association', () => {
        const areas = getTagAssignmentAreas({
            id: {},
            media: { relation: 'many_to_many', entity: 'media' },
            extensionThings: { relation: 'many_to_many', entity: 'extension_thing' },
            translations: { relation: 'one_to_many', entity: 'tag_translation' },
        });

        expect(areas).toEqual([
            {
                key: 'media',
                label: 'sw-settings-tag.detail.assignments.media',
                entity: 'media',
                associations: [],
                entities: ['media'],
            },
            {
                key: 'extensionThings',
                label: 'sw-settings-tag.detail.assignments.extensionThings',
                entity: 'extension_thing',
                associations: [],
                entities: ['extension_thing'],
            },
        ]);
    });

    it('should require the reads of the associations the list loads', () => {
        const areas = getTagAssignmentAreas({
            products: { relation: 'many_to_many', entity: 'product' },
            orders: { relation: 'many_to_many', entity: 'order' },
        });

        expect(areas.map(({ key, associations, entities }) => ({ key, associations, entities }))).toEqual([
            {
                key: 'products',
                associations: ['options.group'],
                entities: [
                    'product',
                    'property_group_option',
                    'property_group',
                ],
            },
            {
                key: 'orders',
                associations: ['orderCustomer'],
                entities: [
                    'order',
                    'order_customer',
                ],
            },
        ]);
    });
});
