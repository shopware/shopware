/**
 * @sw-package framework
 */

import mockTimezone from './mock-timezone';

type DateConstructorTestCase = [label: string, dateParts: [year: number, month: number, date: number], isoString: string];

describe('test/_helper_/mock-timezone', () => {
    let resetTimezone: (() => void) | null = null;

    afterEach(() => {
        resetTimezone?.();
        resetTimezone = null;

        jest.useRealTimers();
    });

    it.each<DateConstructorTestCase>([
        [
            'summer time',
            [
                2024,
                4,
                15,
            ],
            '2024-05-14T22:00:00.000Z',
        ],
        [
            'winter time',
            [
                2024,
                0,
                15,
            ],
            '2024-01-14T23:00:00.000Z',
        ],
    ])('should construct local date parts in mocked browser timezone during %s', (label, dateParts, isoString) => {
        resetTimezone = mockTimezone('Europe/Berlin');

        expect(new Date(...dateParts).toISOString()).toBe(isoString);
    });

    it('should return local date parts in mocked browser timezone', () => {
        resetTimezone = mockTimezone('Europe/Berlin');

        const date = new Date('2024-05-14T22:30:15.123Z');

        expect(date.getFullYear()).toBe(2024);
        expect(date.getMonth()).toBe(4);
        expect(date.getDate()).toBe(15);
        expect(date.getDay()).toBe(3);
        expect(date.getHours()).toBe(0);
        expect(date.getMinutes()).toBe(30);
        expect(date.getSeconds()).toBe(15);
        expect(date.getMilliseconds()).toBe(123);
    });

    it('should keep created dates compatible with instanceof Date checks', () => {
        resetTimezone = mockTimezone('Europe/Berlin');

        expect(new Date()).toBeInstanceOf(Date);
        expect(new Date('2024-05-15T12:00:00.000Z')).toBeInstanceOf(Date);
        expect(new Date(2024, 4, 15)).toBeInstanceOf(Date);
    });

    it('should keep Date.now connected to Jest fake timers', () => {
        jest.useFakeTimers().setSystemTime(new Date('2020-01-01T00:00:00.000Z'));

        resetTimezone = mockTimezone('Europe/Berlin');

        expect(Date.now()).toBe(1577836800000);
        expect(new Date().toISOString()).toBe('2020-01-01T00:00:00.000Z');
        expect(new Date().getHours()).toBe(1);
    });

    it('should restore original Date behavior when reset is called', () => {
        resetTimezone = mockTimezone('Europe/Berlin');

        expect(new Date(2024, 4, 15).toISOString()).toBe('2024-05-14T22:00:00.000Z');

        resetTimezone();
        resetTimezone = null;

        expect(new Date(2024, 4, 15).toISOString()).toBe('2024-05-15T00:00:00.000Z');
    });

    it('should preserve mocked browser-local semantics when setting the day of month', () => {
        resetTimezone = mockTimezone('Europe/Berlin');

        const date = new Date(2024, 4, 15);

        date.setDate(16);

        expect(date.toISOString()).toBe('2024-05-15T22:00:00.000Z');
        expect(date.getDate()).toBe(16);
    });
});
