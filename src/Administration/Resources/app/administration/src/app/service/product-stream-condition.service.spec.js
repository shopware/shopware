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

    it('should allow filtering products by the existence of documents', async () => {
        expect(service.isPropertyInAllowList('product', 'documents')).toBe(true);
        expect(service.isPropertyInAllowList('product_document', 'id')).toBe(true);
        expect(service.isPropertyInAllowList('product_document', 'productId')).toBe(false);
    });

    it.each([
        ['product_document', true],
        ['category', false],
        ['product', false],
        [null, false],
    ])('should report whether %s is an existence-only entity', async (entity, expected) => {
        expect(service.isExistenceOnlyEntity(entity)).toBe(expected);
    });

    it('should be able to check via isNegatedType', async () => {
        expect(service.isNegatedType('notEqualsAll')).toBe(true);
        expect(service.isNegatedType('equals')).toBe(false);
    });

    it('should be able to check via negateOperator', async () => {
        expect(service.negateOperator('notEqualsAll').identifier).toBe('equalsAll');
        expect(service.negateOperator('equalsAll').identifier).toBe('notEqualsAll');
    });

    it('should offer the empty operators for the price/list price ratio', async () => {
        const identifiers = service.getOperatorSet('float', 'cheapestPrice.percentage').map(({ identifier }) => identifier);

        expect(identifiers).toEqual([
            'equals',
            'greaterThan',
            'greaterThanEquals',
            'lessThan',
            'lessThanEquals',
            'notEquals',
            'range',
            'isEmpty',
            'isNotEmpty',
        ]);
    });

    it.each([
        ['another float field', 'cheapestPrice'],
        ['no field', undefined],
    ])('should not offer the empty operators for %s', async (_description, field) => {
        const identifiers = service.getOperatorSet('float', field).map(({ identifier }) => identifier);

        expect(identifiers).not.toContain('isEmpty');
        expect(identifiers).not.toContain('isNotEmpty');
    });

    it('should be able to check via hasEmptyOperators', async () => {
        expect(service.hasEmptyOperators('cheapestPrice.percentage')).toBe(true);
        expect(service.hasEmptyOperators('cheapestPrice')).toBe(false);
    });

    it('should be able to check via isEmptyType', async () => {
        expect(service.isEmptyType('isEmpty')).toBe(true);
        expect(service.isEmptyType('isNotEmpty')).toBe(true);
        expect(service.isEmptyType('equals')).toBe(false);
        expect(service.isEmptyType(null)).toBe(false);
    });

    it.each([
        ['null', null, []],
        ['undefined', undefined, []],
        ['an empty array', [], []],
        ['a tree without deprecated fields', [{ field: 'stock' }, { field: 'name' }], []],
        ['a deprecated top-level field', [{ field: 'states' }], [statesDeprecation]],
        ['the aliased deprecated field path', [{ field: 'product.states' }], [productStatesDeprecation]],
        [
            'the same deprecated field used multiple times (deduplicated)',
            [{ field: 'states' }, { field: 'stock' }, { field: 'states' }],
            [statesDeprecation],
        ],
        ['a deprecated field nested in queries', [{ field: 'stock', queries: [{ field: 'states' }] }], [statesDeprecation]],
        [
            'a deprecated field nested in children',
            [{ field: 'stock', children: [{ field: 'product.states' }] }],
            [productStatesDeprecation],
        ],
        ['a collection containing null entries', [null, { field: 'states' }, undefined], [statesDeprecation]],
        ['an EntityCollection-like input via toArray', { toArray: () => [{ field: 'states' }] }, [statesDeprecation]],
        ['an iterable input', new Set([{ field: 'states' }]), [statesDeprecation]],
    ])('should resolve deprecations for %s', (_, filters, expected) => {
        expect(service.getDeprecationsInTree(filters)).toEqual(expected);
    });
});
