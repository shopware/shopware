import { fileSize, date } from 'src/core/service/utils/format.utils';

describe('src/core/service/utils/format.utils.js', () => {
    describe('filesize', () => {
        it('should convert bytes to a readable format', () => {
            expect(fileSize(0)).toBe('0.00B');
            expect(fileSize(1018)).toBe('0.99KB');
            expect(fileSize(1023)).toBe('1.00KB');
            expect(fileSize(1024)).toBe('1.00KB');
            expect(fileSize(102400000)).toBe('97.66MB');
        });
    });

    describe('date', () => {
        it('should return empty string for null value', () => {
            expect(date(null)).toBe('');
        });
    });

    describe('currency', () => {
        const currencyFilter = Shopware.Utils.format.currency;

        const precision = 0;

        it('should handle integers', () => {
            expect(currencyFilter(42, 'EUR', precision)).toBe('€42');
        });

        it('should handle big int', () => {
            expect(currencyFilter(42n, 'EUR', precision)).toBe('€42');
        });

        it('should handle floats', () => {
            expect(currencyFilter(42.20, 'EUR', 2)).toBe('€42.20');
        });

        it('should use the provided language', () => {
            expect(currencyFilter(42, 'EUR', 0, { language: 'en-US' })).toBe('€42');
        });

        it('should use a different fallback language', () => {
            Shopware.State.commit('setAdminLocale', {
                locales: ['de-DE'],
                locale: 'de-DE',
                languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b'
            });

            expect(currencyFilter(42, 'EUR', 0)).toBe('€42');

            Shopware.State.commit('setAdminLocale', {
                locales: ['en-GB'],
                locale: 'en-GB',
                languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b'
            });
        });

        it('should fallback to the system currency', () => {
            Shopware.Context.app.systemCurrencyISOCode = 'EUR';

            expect(currencyFilter(42, undefined, 0)).toBe('€42');
        });

        it('should fallback to a different system currency', () => {
            Shopware.Context.app.systemCurrencyISOCode = 'USD';

            expect(currencyFilter(42, undefined, 0)).toBe('$42');

            Shopware.Context.app.systemCurrencyISOCode = 'EUR';
        });
    });
});
