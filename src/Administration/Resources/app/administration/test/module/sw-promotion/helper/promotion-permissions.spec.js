import { PromotionPermissions } from 'src/module/sw-promotion/helper/promotion.helper';


// Disable developer hints in jest output
jest.spyOn(global.console, 'warn').mockImplementation(() => jest.fn());

describe('module/sw-promotion/helper/promotion-permissions.js', () => {
    it('should not be allowed to edit a promotion that does not exist', () => {
        expect(PromotionPermissions.isEditingAllowed(null)).toBe(false);
    });

    it('should not be allowed to edit a promotion that is undefined', () => {
        expect(PromotionPermissions.isEditingAllowed(undefined)).toBe(false);
    });

    it('should throw an exception if promotion has not been hydrated and thus has no hasOrders property', () => {
        expect(() => {
            const promotion = {
            };
            PromotionPermissions.isEditingAllowed(promotion);
        }).toThrow();
    });

    it('should not be allowed to edit a promotion that has been used in orders', () => {
        const promotion = {
            hasOrders: true
        };
        expect(PromotionPermissions.isEditingAllowed(promotion)).toBe(false);
    });

    it('should be allowed to edit a promotion that has not been used in orders', () => {
        const promotion = {
            hasOrders: false
        };
        expect(PromotionPermissions.isEditingAllowed(promotion)).toBe(true);
    });
});
