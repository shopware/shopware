import ProductStreamConditionService from 'src/app/service/product-stream-condition.service';

describe('app/service/product-stream-condition.service.js', () => {
    const service = new ProductStreamConditionService();

    it('should be able to add properties to general blacklist', async () => {
        expect(service.isPropertyInBlacklist(null, 'newProp')).toBe(false);
        service.addToGeneralBlacklist(['newProp']);
        expect(service.isPropertyInBlacklist(null, 'newProp')).toBe(true);
    });

    it('should be able to add properties to entity blacklist', async () => {
        expect(service.isPropertyInBlacklist('category', 'newEntityProp')).toBe(false);
        service.addToEntityBlacklist('category', ['newEntityProp']);
        expect(service.isPropertyInBlacklist('category', 'newEntityProp')).toBe(true);

        expect(service.isPropertyInBlacklist('newEntity', 'anotherNewEntityProp')).toBe(false);
        service.addToEntityBlacklist('newEntity', ['anotherNewEntityProp']);
        expect(service.isPropertyInBlacklist('newEntity', 'anotherNewEntityProp')).toBe(true);
    });

    it('should be able to remove properties from general blacklist', async () => {
        expect(service.isPropertyInBlacklist(null, 'createdAt')).toBe(true);
        service.removeFromGeneralBlacklist(['createdAt']);
        expect(service.isPropertyInBlacklist(null, 'createdAt')).toBe(false);
    });

    it('should be able to remove properties from entity blacklist', async () => {
        expect(service.isPropertyInBlacklist('category', 'path')).toBe(true);
        service.removeFromEntityBlacklist('category', ['path']);
        expect(service.isPropertyInBlacklist('category', 'path')).toBe(false);
    });
});
