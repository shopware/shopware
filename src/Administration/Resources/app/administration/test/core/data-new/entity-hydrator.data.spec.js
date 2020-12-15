import EntityHydrator from 'src/core/data-new/entity-hydrator.data';
import entitySchemaMock from 'src/../test/_mocks_/entity-schema.json';
import uuid from 'src/../test/_helper_/uuid';

const entityHydrator = new EntityHydrator();

describe('src/core/data-new/entity-hydrator.data', () => {
    beforeAll(() => {
        Object.entries(entitySchemaMock).forEach(([entityName, entityDefinition]) => {
            Shopware.EntityDefinition.add(entityName, entityDefinition);
        });
    });

    beforeEach(() => {
        entityHydrator.cache = {};
    });

    it('should hydrate empty jsonObject field to empty object', () => {
        const entityName = 'product';
        const row = {
            id: uuid.get('pullover'),
            attributes: {
                customFields: []
            },
            relationships: {},
            type: 'product'
        };

        const result = entityHydrator.hydrateEntity(entityName, row, {}, {}, {});

        expect(result.customFields).toEqual({});
    });

    it('should hydrate jsonObject field with content', () => {
        const entityName = 'product';
        const row = {
            id: uuid.get('pullover'),
            attributes: {
                customFields: {
                    foo: 'bar'
                }
            },
            relationships: {},
            type: 'product'
        };

        const result = entityHydrator.hydrateEntity(entityName, row, {}, {}, {});

        expect(result.customFields).toEqual({
            foo: 'bar'
        });
    });

    it('should hydrate empty jsonList field to empty object', () => {
        const entityName = 'product';
        const row = {
            id: uuid.get('pullover'),
            attributes: {
                variation: {}
            },
            relationships: {},
            type: 'product'
        };

        const result = entityHydrator.hydrateEntity(entityName, row, {}, {}, {});

        expect(result.variation).toEqual([]);
    });

    it('should hydrate jsonList field with content', () => {
        const entityName = 'product';
        const row = {
            id: uuid.get('pullover'),
            attributes: {
                variation: [
                    { foo: 'bar' },
                    { shop: 'ware' }
                ]
            },
            relationships: {},
            type: 'product'
        };

        const result = entityHydrator.hydrateEntity(entityName, row, {}, {}, {});

        expect(result.variation).toEqual([
            { foo: 'bar' },
            { shop: 'ware' }
        ]);
    });
});
