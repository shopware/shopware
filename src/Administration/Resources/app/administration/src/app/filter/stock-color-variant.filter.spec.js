/**
 * @package admin
 */
describe('src/app/filter/stock-color-variant.filter.ts', () => {
    const stockColorVariantFilter = Shopware.Filter.getByName('stockColorVariant');

    it('should contain a filter', () => {
        expect(stockColorVariantFilter).toBeDefined();
    });

    it('should return empty string fallback when no value is given', () => {
        expect(stockColorVariantFilter()).toBe('');
    });

    it('should return success when value is 25 or higher', () => {
        expect(stockColorVariantFilter(25)).toBe('success');
        expect(stockColorVariantFilter(29)).toBe('success');
    });

    it('should return warning when value is between 1 and 25', () => {
        expect(stockColorVariantFilter(1)).toBe('warning');
        expect(stockColorVariantFilter(18)).toBe('warning');
        expect(stockColorVariantFilter(24)).toBe('warning');
    });

    it('should return error when value is below 0', () => {
        expect(stockColorVariantFilter(0)).toBe('error');
        expect(stockColorVariantFilter(-5)).toBe('error');
    });
});
