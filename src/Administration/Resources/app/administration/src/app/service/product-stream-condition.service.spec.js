import ProductStreamConditionService from 'src/app/service/product-stream-condition.service';

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
});
