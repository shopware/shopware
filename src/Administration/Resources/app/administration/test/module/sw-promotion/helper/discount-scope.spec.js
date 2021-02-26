import { DiscountScopes } from 'src/module/sw-promotion/helper/promotion.helper';

// Disable developer hints in jest output
jest.spyOn(global.console, 'warn').mockImplementation(() => jest.fn());

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 * @feature-deprecated (flag:FEATURE_NEXT_13810)
 */
describe('module/sw-promotion/helper/discount-scope.js', () => {
    it('should have a CART property that matches our defined identifier string', async () => {
        expect(DiscountScopes.CART).toBe('cart');
    });

    it('should have a DELIVERY property that matches our defined identifier string', async () => {
        expect(DiscountScopes.DELIVERY).toBe('delivery');
    });

    it('should have a SET property that matches our defined identifier string', async () => {
        expect(DiscountScopes.SET).toBe('set');
    });

    it('should have a SETGROUP property that matches our defined identifier string', async () => {
        expect(DiscountScopes.SETGROUP).toBe('setgroup');
    });
});
