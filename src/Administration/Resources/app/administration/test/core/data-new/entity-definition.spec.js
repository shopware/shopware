import entitySchemaMock from 'src/../test/_mocks_/entity-schema.json';

describe('src/core/data-new/entity-hydrator.data', () => {
    beforeAll(() => {
        Object.entries(entitySchemaMock).forEach(([entityName, entityDefinition]) => {
            Shopware.EntityDefinition.add(entityName, entityDefinition);
        });
    });

    it('should be a jsonObject field', () => {
        const schema = Shopware.EntityDefinition.get('product');

        const result = schema.isJsonObjectField({ type: 'json_object' });

        expect(result).toBe(true);
    });

    it('should not be a jsonObject field', () => {
        const schema = Shopware.EntityDefinition.get('product');

        const result = schema.isJsonObjectField({ type: 'json' });

        expect(result).toBe(false);
    });

    it('should be a jsonList field', () => {
        const schema = Shopware.EntityDefinition.get('product');

        const result = schema.isJsonListField({ type: 'json_list' });

        expect(result).toBe(true);
    });

    it('should not be a jsonList field', () => {
        const schema = Shopware.EntityDefinition.get('product');

        const result = schema.isJsonListField({ type: 'json' });

        expect(result).toBe(false);
    });
});
