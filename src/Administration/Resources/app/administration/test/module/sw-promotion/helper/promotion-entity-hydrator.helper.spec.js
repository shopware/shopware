import hydrator from 'src/module/sw-promotion/helper/promotion-entity-hydrator.helper';

// Disable developer hints in jest output
jest.spyOn(global.console, 'warn').mockImplementation(() => jest.fn());

describe('module/sw-promotion/helper/promotion-entity-hydrator.helper.js', () => {
    it('should have hasOrders FALSE if 0 orders exist', () => {
        const promotion = {
            orderCount: 0
        };
        hydrator.hydrate(promotion);
        expect(promotion.hasOrders).toBe(false);
    });

    it('should have hasOrders FALSE if NULL orders exist', () => {
        const promotion = {
            orderCount: null
        };
        hydrator.hydrate(promotion);
        expect(promotion.hasOrders).toBe(false);
    });

    it('should have hasOrders FALSE if no order count property exists', () => {
        const promotion = {};
        hydrator.hydrate(promotion);
        expect(promotion.hasOrders).toBe(false);
    });

    it('should have hasOrders TRUE if 1 order exists', () => {
        const promotion = {
            orderCount: 1
        };
        hydrator.hydrate(promotion);
        expect(promotion.hasOrders).toBe(true);
    });
});
