import DiscountComponentHandler from 'src/module/sw-promotion/component/sw-promotion-discount-component/handler';
import { DiscountTypes } from 'src/module/sw-promotion/helper/promotion.helper';


// Disable developer hints in jest output
jest.spyOn(global.console, 'warn').mockImplementation(() => jest.fn());

describe('module/sw-promotion/component/sw-promotion-discount-component/handler.js', () => {
    it('should have a min-value function that returns 0.01', () => {
        const cmp = new DiscountComponentHandler();
        expect(cmp.getMinValue()).toBe(0.01);
    });
    it('should have a max-value that return 100% for percentage types', () => {
        const cmp = new DiscountComponentHandler();
        expect(cmp.getMaxValue(DiscountTypes.PERCENTAGE)).toBe(100);
    });
    it('should have a max-value that returns NULL for absolute types', () => {
        const cmp = new DiscountComponentHandler();
        expect(cmp.getMaxValue(DiscountTypes.ABSOLUTE)).toBe(null);
    });
    it('should fix a value of 110% to a maximum of 100%', () => {
        const cmp = new DiscountComponentHandler();
        expect(cmp.getFixedValue(110, DiscountTypes.PERCENTAGE)).toBe(100);
    });
    it('must not fix a value of below 100%', () => {
        const cmp = new DiscountComponentHandler();
        expect(cmp.getFixedValue(99, DiscountTypes.PERCENTAGE)).toBe(99);
    });
    it('must not fix a value above 100 in case of an absolute type', () => {
        const cmp = new DiscountComponentHandler();
        expect(cmp.getFixedValue(101, DiscountTypes.ABSOLUTE)).toBe(101);
    });
    it('should fix values below 0,00 to be the minValue', () => {
        const cmp = new DiscountComponentHandler();
        expect(cmp.getFixedValue(-1, DiscountTypes.PERCENTAGE)).toBe(cmp.getMinValue());
    });
    it('should fix values of 0,00 to be the minValue', () => {
        const cmp = new DiscountComponentHandler();
        expect(cmp.getFixedValue(0, DiscountTypes.ABSOLUTE)).toBe(cmp.getMinValue());
    });
});
