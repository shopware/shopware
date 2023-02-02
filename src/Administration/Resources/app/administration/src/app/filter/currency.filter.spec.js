/**
 * @package admin
 */

const { Filter } = Shopware;

describe('filter/currency.filter', () => {
    let currencyFilter;
    const currency = 'EUR';
    const precision = 0;

    beforeAll(() => {
        currencyFilter = Filter.getByName('currency');
    });

    it('should handle integers', async () => {
        expect(currencyFilter(42, currency, precision)).toBe('€42');
    });

    it('should handle big int', async () => {
        expect(currencyFilter(42n, currency, precision)).toBe('€42');
    });

    it('should handle floats', async () => {
        expect(currencyFilter(42.20, currency, 2)).toBe('€42.20');
    });

    it('should handle strings', async () => {
        expect(currencyFilter('foo bar', currency, precision)).toBe('foo bar');
        expect(currencyFilter('42', currency, precision)).toBe('€42');
    });

    it('should handle empty strings', async () => {
        expect(currencyFilter('', currency, precision)).toBe('-');
    });

    it('should handle NaN', async () => {
        expect(currencyFilter(NaN, currency, precision)).toBe('-');
    });

    it('should handle undefined', async () => {
        expect(currencyFilter(undefined, currency, precision)).toBe('-');
    });

    it('should handle null', async () => {
        expect(currencyFilter(null, currency, precision)).toBe('-');
    });

    it('should handle boolean', async () => {
        expect(currencyFilter(false, currency, precision)).toBe('-');
        expect(currencyFilter(true, currency, precision)).toBe('-');
    });
});
