import DiscountTypes from 'src/module/sw-promotion/common/discount-type';

// Disable developer hints in jest output
jest.spyOn(global.console, 'warn').mockImplementation(() => jest.fn());

describe('module/sw-promotion/common/discount-type.js', () => {
    it('should have a ABSOLUTE property that matches our defined identifier string', () => {
        expect(DiscountTypes.ABSOLUTE).toBe('absolute');
    });

    it('should have a PERCENTAGE property that matches our defined identifier string', () => {
        expect(DiscountTypes.PERCENTAGE).toBe('percentage');
    });
});
