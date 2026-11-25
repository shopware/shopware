/**
 * @sw-package framework
 */
import shortDate from './short-date';

describe('module/sw-settings-system-health/short-date', () => {
    it('should leave out the year of a date in the current year', () => {
        const date = jest.spyOn(Shopware.Utils.format, 'date').mockReturnValue('24 Nov, 10:00');

        expect(shortDate(`${new Date().getFullYear()}-11-24T10:00:00+00:00`)).toBe('24 Nov, 10:00');
        expect(date).toHaveBeenCalledWith(expect.any(String), { month: 'short', year: undefined });
    });

    it('should name the year of a date in another year', () => {
        const date = jest.spyOn(Shopware.Utils.format, 'date').mockReturnValue('24 Nov 2020, 10:00');

        shortDate('2020-11-24T10:00:00+00:00');

        expect(date).toHaveBeenCalledWith(expect.any(String), { month: 'short', year: 'numeric' });
    });
});
