/**
 * @package admin
 */

import { fileSize, date, dateWithUserTimezone, toISODate } from 'src/core/service/utils/format.utils';

describe('src/core/service/utils/format.utils.js', () => {
    describe('filesize', () => {
        it('should convert bytes to a readable format', async () => {
            expect(fileSize(0)).toBe('0.00B');
            expect(fileSize(1018)).toBe('0.99KB');
            expect(fileSize(1023)).toBe('1.00KB');
            expect(fileSize(1024)).toBe('1.00KB');
            expect(fileSize(102400000)).toBe('97.66MB');
        });
    });

    describe('date', () => {
        const setLocale = (locale) => {
            jest.spyOn(Shopware.Application.getContainer('factory').locale, 'getLastKnownLocale').mockImplementation(
                () => locale,
            );
        };
        const setTimeZone = (timeZone) => Shopware.State.commit('setCurrentUser', { timeZone });

        beforeEach(async () => {
            setLocale('en-GB');
            setTimeZone('UTC');
        });

        it('should return empty string for null value', async () => {
            expect(date(null)).toBe('');
        });

        it('should convert the date correctly with timezone UTC in en-GB', async () => {
            setLocale('en-GB');
            setTimeZone('UTC');

            expect(date('2000-06-18T08:30:00.000+00:00')).toBe('18 June 2000 at 08:30');
        });

        it('should convert the date correctly with timezone UTC in en-US', async () => {
            setLocale('en-US');
            setTimeZone('UTC');

            expect(date('2000-06-18T08:30:00.000+00:00')).toBe('June 18, 2000 at 8:30 AM');
        });

        it('should convert the date correctly with timezone UTC in de-DE', async () => {
            setLocale('de-DE');
            setTimeZone('UTC');

            expect(date('2000-06-18T08:30:00.000+00:00')).toBe('18. Juni 2000 um 08:30');
        });

        it('should convert the date correctly with timezone America/New_York in en-GB', async () => {
            setLocale('en-GB');
            setTimeZone('America/New_York');

            expect(date('2000-06-18T08:30:00.000+00:00')).toBe('18 June 2000 at 04:30');
        });

        it('should convert the date correctly with timezone America/New_York in en-US', async () => {
            setLocale('en-US');
            setTimeZone('America/New_York');

            expect(date('2000-06-18T08:30:00.000+00:00')).toBe('June 18, 2000 at 4:30 AM');
        });

        it('should convert the date correctly with timezone America/New_York in de-DE', async () => {
            setLocale('de-DE');
            setTimeZone('America/New_York');

            expect(date('2000-06-18T08:30:00.000+00:00')).toBe('18. Juni 2000 um 04:30');
        });

        it('should not convert the date correctly with timezone America/New_York in de-DE', async () => {
            setLocale('de-DE');
            setTimeZone('America/New_York');

            expect(
                date('2000-06-18T08:30:00.000+00:00', {
                    skipTimezoneConversion: true,
                }),
            ).toBe('18. Juni 2000 um 08:30');
        });
    });

    describe('dateWithUserTimezone', () => {
        const setLocale = (locale) => {
            jest.spyOn(Shopware.Application.getContainer('factory').locale, 'getLastKnownLocale').mockImplementation(
                () => locale,
            );
        };
        const setTimeZone = (timeZone) => Shopware.State.commit('setCurrentUser', { timeZone });

        beforeEach(async () => {
            setLocale('en-GB');
            setTimeZone('UTC');
        });

        it('should convert the date correctly with timezone Pacific/Pago_Pago', async () => {
            setTimeZone('Pacific/Samoa');
            // eslint-disable-next-line no-shadow
            const date = new Date(2000, 1, 1, 11, 13, 37);

            expect(dateWithUserTimezone(date).toString()).toBe(
                'Tue Feb 01 2000 00:13:37 GMT+0000 (Coordinated Universal Time)',
            );
        });

        it('should convert the date correctly with timezone UTC as fallback', async () => {
            setTimeZone(null);
            // eslint-disable-next-line no-shadow
            const date = new Date(2000, 1, 1, 0, 13, 37);

            expect(dateWithUserTimezone(date).toString()).toBe(
                'Tue Feb 01 2000 00:13:37 GMT+0000 (Coordinated Universal Time)',
            );
        });
    });

    describe('currency', () => {
        const currencyFilter = Shopware.Utils.format.currency;

        const precision = 0;

        it('should handle integers', async () => {
            expect(currencyFilter(42, 'EUR', precision)).toBe('€42');
        });

        it('should handle big int', async () => {
            expect(currencyFilter(42n, 'EUR', precision)).toBe('€42');
        });

        it('should handle floats', async () => {
            expect(currencyFilter(42.2, 'EUR', 2)).toBe('€42.20');
        });

        it('should use the provided language', async () => {
            expect(currencyFilter(42, 'EUR', 0, { language: 'en-US' })).toBe('€42');
        });

        it('should use a different fallback language', async () => {
            Shopware.State.commit('setAdminLocale', {
                locales: ['de-DE'],
                locale: 'de-DE',
                languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
            });

            expect(currencyFilter(42, 'EUR', 0)).toBe('42 €');

            Shopware.State.commit('setAdminLocale', {
                locales: ['en-GB'],
                locale: 'en-GB',
                languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
            });
        });

        it('should fallback to the system currency', async () => {
            Shopware.Context.app.systemCurrencyISOCode = 'EUR';

            expect(currencyFilter(42, undefined, 0)).toBe('€42');
        });

        it('should fallback to a different system currency', async () => {
            Shopware.Context.app.systemCurrencyISOCode = 'USD';

            expect(currencyFilter(42, undefined, 0)).toBe('US$42');

            Shopware.Context.app.systemCurrencyISOCode = 'EUR';
        });

        it('should fallback to decimal when the currency ISO code is invalid', async () => {
            Shopware.Context.app.systemCurrencyISOCode = 'INVALID_EXAMPLE_CURRENCY_CODE';

            jest.spyOn(console, 'error').mockImplementationOnce(() => {});

            expect(currencyFilter(42.31415, undefined, 2)).toBe('42.31');

            expect(console.error).toHaveBeenCalledWith(
                new RangeError('Invalid currency code : INVALID_EXAMPLE_CURRENCY_CODE'),
            );

            Shopware.Context.app.systemCurrencyISOCode = 'EUR';
        });
    });

    describe('toISODate', () => {
        it('formats the date with time', async () => {
            const dateWithTime = new Date(Date.UTC(2021, 0, 1, 13, 37, 0));

            expect(toISODate(dateWithTime)).toBe('2021-01-01T13:37:00.000Z');
        });

        it('formats the date without time', async () => {
            const dateWithoutTime = new Date(Date.UTC(2021, 0, 1, 13, 37, 0));

            expect(toISODate(dateWithoutTime, false)).toBe('2021-01-01');
        });
    });
});
