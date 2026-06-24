/**
 * @sw-package framework
 */
import ProductStreamConditionService from 'src/app/service/product-stream-condition.service';

const statesDeprecation = {
    field: 'states',
    label: 'sw-product-stream.filter.values.states',
    version: 'v6.8.0',
    replacement: {
        field: 'type',
        label: 'sw-product-stream.filter.values.type',
    },
};

const productStatesDeprecation = {
    ...statesDeprecation,
    field: 'product.states',
};

describe('app/service/product-stream-condition.service.js', () => {
    const service = new ProductStreamConditionService();

    it('should be able to add properties to general allowlist', async () => {
        expect(service.isPropertyInAllowList(null, 'newProp')).toBe(false);
        service.addToGeneralAllowList(['newProp']);
        expect(service.isPropertyInAllowList(null, 'newProp')).toBe(true);
    });

    it('should be able to add properties to entity allowlist', async () => {
        expect(service.isPropertyInAllowList('category', 'newEntityProp')).toBe(false);
        service.addToEntityAllowList('category', ['newEntityProp']);
        expect(service.isPropertyInAllowList('category', 'newEntityProp')).toBe(true);

        expect(service.isPropertyInAllowList('newEntity', 'anotherNewEntityProp')).toBe(false);
        service.addToEntityAllowList('newEntity', ['anotherNewEntityProp']);
        expect(service.isPropertyInAllowList('newEntity', 'anotherNewEntityProp')).toBe(true);
    });

    it('should be able to remove properties from general allowlist', async () => {
        expect(service.isPropertyInAllowList(null, 'id')).toBe(true);
        service.removeFromGeneralAllowList(['id']);
        expect(service.isPropertyInAllowList(null, 'id')).toBe(false);
    });

    it('should be able to remove properties from entity allowlist', async () => {
        expect(service.isPropertyInAllowList('product', 'name')).toBe(true);
        service.removeFromEntityAllowList('product', ['name']);
        expect(service.isPropertyInAllowList('product', 'name')).toBe(false);
    });

    it('should be able to check via isNegatedType', async () => {
        expect(service.isNegatedType('notEqualsAll')).toBe(true);
        expect(service.isNegatedType('equals')).toBe(false);
    });

    it('should be able to check via negateOperator', async () => {
        expect(service.negateOperator('notEqualsAll').identifier).toBe('equalsAll');
        expect(service.negateOperator('equalsAll').identifier).toBe('notEqualsAll');
    });

    it.each([
        [
            'null',
            null,
            [],
        ],
        [
            'undefined',
            undefined,
            [],
        ],
        [
            'an empty array',
            [],
            [],
        ],
        [
            'a tree without deprecated fields',
            [
                { field: 'stock' },
                { field: 'name' },
            ],
            [],
        ],
        [
            'a deprecated top-level field',
            [{ field: 'states' }],
            [statesDeprecation],
        ],
        [
            'the aliased deprecated field path',
            [{ field: 'product.states' }],
            [productStatesDeprecation],
        ],
        [
            'the same deprecated field used multiple times (deduplicated)',
            [
                { field: 'states' },
                { field: 'stock' },
                { field: 'states' },
            ],
            [statesDeprecation],
        ],
        [
            'a deprecated field nested in queries',
            [{ field: 'stock', queries: [{ field: 'states' }] }],
            [statesDeprecation],
        ],
        [
            'a deprecated field nested in children',
            [{ field: 'stock', children: [{ field: 'product.states' }] }],
            [productStatesDeprecation],
        ],
        [
            'a collection containing null entries',
            [
                null,
                { field: 'states' },
                undefined,
            ],
            [statesDeprecation],
        ],
        [
            'an EntityCollection-like input via toArray',
            { toArray: () => [{ field: 'states' }] },
            [statesDeprecation],
        ],
        [
            'an iterable input',
            new Set([{ field: 'states' }]),
            [statesDeprecation],
        ],
    ])('should resolve deprecations for %s', (_, filters, expected) => {
        expect(service.getDeprecationsInTree(filters)).toEqual(expected);
    });
});
