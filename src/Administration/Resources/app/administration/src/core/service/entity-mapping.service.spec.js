/**
 * @sw-package framework
 */

import EntityMappingService from 'src/core/service/entity-mapping.service';

const mockEntitySchemas = {
    product: {
        properties: {
            id: { type: 'string', format: 'uuid' },
            name: { type: 'string' },
            productNumber: { type: 'string' },
            readOnlyField: { type: 'string', readOnly: true },
            price: {
                type: 'json_object',
                properties: {
                    net: { type: 'float' },
                    gross: { type: 'float' },
                    linked: { type: 'boolean' },
                },
            },
            tags: {
                type: 'array',
                items: { type: 'string' },
            },
        },
    },
};

global.Shopware = {
    EntityDefinition: {
        getDefinitionRegistry: jest.fn(() => ({
            get: jest.fn((entity) => {
                return mockEntitySchemas[entity] || { properties: {} };
            }),
        })),
    },
};

describe('core/service/entity-mapping.service.ts', () => {
    beforeEach(() => {
        jest.clearAllMocks();
    });

    it('returns empty schema if no entityName is provided', () => {
        const result = EntityMappingService.getEntityMapping();
        expect(result).toEqual({});
    });

    it('returns empty schema if no entityNameMapping is provided', () => {
        const result = EntityMappingService.getEntityMapping('product');
        expect(result).toEqual({});
    });

    it('builds schema based on entityNameMapping', () => {
        const result = EntityMappingService.getEntityMapping('product', {
            product: 'product',
        });

        expect(result).toHaveProperty('product');
        expect(result.product.entity).toBe('product');
        expect(result.product.type).toBe('object');
    });

    it('returns empty if entity was just selected', () => {
        const result = EntityMappingService.getEntityMapping('product.price', {
            product: 'product',
        });

        expect(Object.keys(result)).toHaveLength(0);
    });

    it('returns only attributes from json object', () => {
        const result = EntityMappingService.getEntityMapping('product.price.', {
            product: 'product',
        });

        expect(Object.keys(result)).toHaveLength(3);
        expect(result).toHaveProperty('net');
        expect(result.net.type).toBe('float');
        expect(result).toHaveProperty('gross');
        expect(result.gross.type).toBe('float');
        expect(result).toHaveProperty('linked');
        expect(result.linked.type).toBe('boolean');
    });

    it('filters out readOnly and uuid fields', () => {
        const result = EntityMappingService.getEntityMapping('product', {
            product: 'product',
        });

        expect(result).not.toHaveProperty('id');
        expect(result).not.toHaveProperty('readonlyField');
    });

    it('transforms array fields to [0] notation', () => {
        const result = EntityMappingService.getEntityMapping('product.', {
            product: 'product',
        });

        expect(Object.keys(result)).toContain('tags[0]');
        expect(result['tags[0]'].type).toBe('array');
    });

    it('returns empty if entity structure is invalid', () => {
        const result = EntityMappingService.getEntityMapping('invalid.path', {
            invalid: 'nonexistent',
        });

        expect(result).toEqual({});
    });
});
