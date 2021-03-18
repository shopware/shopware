import hydrator from 'src/module/sw-promotion/helper/promotion-entity-hydrator.helper';

// Disable developer hints in jest output
jest.spyOn(global.console, 'warn').mockImplementation(() => jest.fn());

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 * @feature-deprecated (flag:FEATURE_NEXT_13810)
 */
describe('module/sw-promotion/helper/promotion-entity-hydrator.helper.js', () => {
    it('should have hasOrders FALSE if 0 orders exist', async () => {
        const promotion = {
            orderCount: 0
        };
        hydrator.hydrate(promotion);
        expect(promotion.hasOrders).toBe(false);
    });

    it('should have hasOrders FALSE if NULL orders exist', async () => {
        const promotion = {
            orderCount: null
        };
        hydrator.hydrate(promotion);
        expect(promotion.hasOrders).toBe(false);
    });

    it('should have hasOrders FALSE if no order count property exists', async () => {
        const promotion = {};
        hydrator.hydrate(promotion);
        expect(promotion.hasOrders).toBe(false);
    });

    it('should have hasOrders TRUE if 1 order exists', async () => {
        const promotion = {
            orderCount: 1
        };
        hydrator.hydrate(promotion);
        expect(promotion.hasOrders).toBe(true);
    });
});
