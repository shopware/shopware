import { DiscountTypes } from 'src/module/sw-promotion/helper/promotion.helper';

// Disable developer hints in jest output
jest.spyOn(global.console, 'warn').mockImplementation(() => jest.fn());

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 * @feature-deprecated (flag:FEATURE_NEXT_13810)
 */
describe('module/sw-promotion/helper/discount-type.js', () => {
    it('should have a ABSOLUTE property that matches our defined identifier string', async () => {
        expect(DiscountTypes.ABSOLUTE).toBe('absolute');
    });

    it('should have a PERCENTAGE property that matches our defined identifier string', async () => {
        expect(DiscountTypes.PERCENTAGE).toBe('percentage');
    });

    it('should have a FIXED property that matches our defined identifier string', async () => {
        expect(DiscountTypes.FIXED).toBe('fixed');
    });

    it('should have a FIXED_UNIT property that matches our defined identifier string', async () => {
        expect(DiscountTypes.FIXED_UNIT).toBe('fixed_unit');
    });
});
