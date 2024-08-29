/**
 * @package buyers-experience
 * @group disabledCompat
 */
import DiscountComponentHandler from 'src/module/sw-promotion-v2/component/sw-promotion-discount-component/handler';
import { DiscountTypes } from 'src/module/sw-promotion-v2/helper/promotion.helper';

describe('module/sw-promotion-v2/component/sw-promotion-discount-component/handler.js', () => {
    it('should have a min-value function that returns 0.00', async () => {
        const cmp = new DiscountComponentHandler();
        expect(cmp.getMinValue()).toBe(0.00);
    });
    it('should have a max-value that return 100% for percentage types', async () => {
        const cmp = new DiscountComponentHandler();
        expect(cmp.getMaxValue(DiscountTypes.PERCENTAGE)).toBe(100);
    });
    it('should have a max-value that returns NULL for absolute types', async () => {
        const cmp = new DiscountComponentHandler();
        expect(cmp.getMaxValue(DiscountTypes.ABSOLUTE)).toBeNull();
    });
    it('should fix a value of 110% to a maximum of 100%', async () => {
        const cmp = new DiscountComponentHandler();
        expect(cmp.getFixedValue(110, DiscountTypes.PERCENTAGE)).toBe(100);
    });
    it('must not fix a value of below 100%', async () => {
        const cmp = new DiscountComponentHandler();
        expect(cmp.getFixedValue(99, DiscountTypes.PERCENTAGE)).toBe(99);
    });
    it('must not fix a value above 100 in case of an absolute type', async () => {
        const cmp = new DiscountComponentHandler();
        expect(cmp.getFixedValue(101, DiscountTypes.ABSOLUTE)).toBe(101);
    });
    it('should fix values below minValue to be the minValue', async () => {
        const cmp = new DiscountComponentHandler();
        const lowerNr = cmp.getMinValue() - 0.1;
        expect(cmp.getFixedValue(lowerNr, DiscountTypes.PERCENTAGE)).toBe(cmp.getMinValue());
    });
});
