import { DiscountScopes } from 'src/module/sw-promotion/helper/promotion.helper';

// Disable developer hints in jest output
jest.spyOn(global.console, 'warn').mockImplementation(() => jest.fn());

describe('module/sw-promotion/helper/discount-scope.js', () => {
    it('should have a CART property that matches our defined identifier string', () => {
        expect(DiscountScopes.CART).toBe('cart');
    });
});
