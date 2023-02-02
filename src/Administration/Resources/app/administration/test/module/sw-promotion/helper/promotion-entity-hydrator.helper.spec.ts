import hydrator from 'src/module/sw-promotion/helper/promotion-entity-hydrator.helper';

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 * @feature-deprecated (flag:FEATURE_NEXT_13810)
 */
describe('module/sw-promotion/helper/promotion-entity-hydrator.helper.ts', () => {
    it('should not alter promotion if promotion is falsy', async () => {
        const promotion = null;

        hydrator.hydrate(promotion);

        expect(promotion).toBe(null);
    });

    it('should set hasOrders to false if orderCount is undefined', async () => {
        const promotion = {};

        hydrator.hydrate(promotion);

        // @ts-ignore
        expect(promotion.hasOrders).toBe(false);
    });

    it('should set hasOrders to false if orderCount is 0', async () => {
        const promotion = {
            orderCount: 0,
        };

        hydrator.hydrate(promotion);

        // @ts-ignore
        expect(promotion.hasOrders).toBe(false);
    });

    it('should set hasOrders to true if orderCount is bigger than 0', async () => {
        const promotion = {
            orderCount: 2,
        };

        hydrator.hydrate(promotion);

        // @ts-ignore
        expect(promotion.hasOrders).toBe(true);
    });
});
