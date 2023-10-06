import DateHelper from 'src/helper/date.helper';

const defaultFormatterOptions = {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
};

/**
 * @package storefront
 */
describe('date.helper.js', () => {
    test('it returns an empty string for non numbers', () => {
        expect(DateHelper.format(null)).toStrictEqual('');
        expect(DateHelper.format(1/0)).toStrictEqual('');
        expect(DateHelper.format()).toStrictEqual('');
        expect(DateHelper.format('parse me')).toStrictEqual('');
    });

    test('it returns formatted date from date object', () => {
        // Mon Feb 03 2020 09:00:00 GMT+0100
        const date = new Date(2020, 1, 3, 9, 0, 0);
        Object.defineProperty(navigator, 'language', {
            value: 'en-GB',
        });

        const expectedDate = Intl.DateTimeFormat('en-GB', defaultFormatterOptions).format(date);

        expect(DateHelper.format(date.getTime())).toBe(expectedDate);
        expect(DateHelper.format(date)).toBe(expectedDate);
        expect(DateHelper.format(date.toISOString())).toBe(expectedDate);
    });
});
