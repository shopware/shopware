/**
 * @sw-package framework
 * @private
 */

/**
 * Mocks the browser/runtime timezone for tests by replacing the global Date constructor.
 *
 * Use this helper when code under test calls local Date APIs, such as `new Date(year, month, day)`,
 * `getFullYear()`, `getMonth()`, `getDate()`, `getDay()`, or `getHours()`, and the test needs those APIs
 * to behave as if the browser were running in a specific IANA timezone.
 *
 * The mock does not change the current timestamp source. `Date.now()` still delegates to the original Date,
 * so Jest fake timers and `jest.setSystemTime(...)` continue to work.
 *
 * @example
 * jest.setSystemTime(new Date('2020-01-01T00:00:00.000Z'));
 * const resetTimezone = mockTimezone('Europe/Berlin');
 *
 * try {
 *     expect(new Date(2020, 0, 1).toISOString()).toBe('2019-12-31T23:00:00.000Z');
 * } finally {
 *     resetTimezone();
 * }
 */

type TimeZoneParts = {
    year: number;
    month: number;
    date: number;
    hours: number;
    minutes: number;
    seconds: number;
    milliseconds: number;
};

type DatePartConstructorArguments = [
    year: number,
    month: number,
    date?: number,
    hours?: number,
    minutes?: number,
    seconds?: number,
    milliseconds?: number,
];

type MockDateConstructorArguments = [] | [value: number | string] | DatePartConstructorArguments;

type MockDateConstructor = DateConstructor & {
    new(...args: MockDateConstructorArguments): Date;
    (...args: MockDateConstructorArguments): string;
};

/**
 * Returns the calendar and clock parts of a UTC instant as they would appear in the mocked browser timezone.
 * Used by the mocked local Date getters and timezone offset calculations.
 */
function getTimeZoneParts(date: Date, timeZone: string, DateConstructor: DateConstructor): TimeZoneParts {
    const formatter = new Intl.DateTimeFormat('en-CA', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hourCycle: 'h23',
    });

    const parts = formatter.formatToParts(new DateConstructor(date.getTime()));
    const getPart = (type: Intl.DateTimeFormatPartTypes): number => {
        return Number(parts.find((part) => part.type === type)?.value);
    };

    return {
        year: getPart('year'),
        month: getPart('month'),
        date: getPart('day'),
        hours: getPart('hour'),
        minutes: getPart('minute'),
        seconds: getPart('second'),
        milliseconds: date.getUTCMilliseconds(),
    };
}

/**
 * Calculates the timezone offset, in milliseconds, for a UTC timestamp in the requested timezone.
 * Used when converting mocked browser-local date parts back into the real UTC timestamp represented by Date.
 */
function getTimeZoneOffset(timeZone: string, utcMilliseconds: number, DateConstructor: DateConstructor): number {
    const date = new DateConstructor(utcMilliseconds);
    const parts = getTimeZoneParts(date, timeZone, DateConstructor);
    const localAsUtc = DateConstructor.UTC(
        parts.year,
        parts.month - 1,
        parts.date,
        parts.hours,
        parts.minutes,
        parts.seconds,
        parts.milliseconds,
    );

    return localAsUtc - utcMilliseconds;
}

/**
 * Converts mocked browser-local calendar parts into the matching real Date instance.
 * The two-pass offset lookup handles daylight-saving transitions where the offset can change around the target time.
 */
function getDateFromTimeZoneParts(parts: TimeZoneParts, timeZone: string, DateConstructor: DateConstructor): Date {
    const localMilliseconds = DateConstructor.UTC(
        parts.year,
        parts.month - 1,
        parts.date,
        parts.hours,
        parts.minutes,
        parts.seconds,
        parts.milliseconds,
    );
    const firstPass = localMilliseconds - getTimeZoneOffset(timeZone, localMilliseconds, DateConstructor);

    return new DateConstructor(
        localMilliseconds - getTimeZoneOffset(timeZone, firstPass, DateConstructor),
    );
}

/**
 * Normalizes Date constructor arguments with native overflow behavior before timezone conversion.
 * This preserves cases such as `new Date(2024, 0, 32)` rolling into February.
 */
function normalizeConstructorParts(
    args: DatePartConstructorArguments,
    DateConstructor: DateConstructor,
): TimeZoneParts {
    const [
        year,
        month,
        date = 1,
        hours = 0,
        minutes = 0,
        seconds = 0,
        milliseconds = 0,
    ] = args;
    const normalized = new DateConstructor(DateConstructor.UTC(
        year,
        month,
        date,
        hours,
        minutes,
        seconds,
        milliseconds,
    ));

    return {
        year: normalized.getUTCFullYear(),
        month: normalized.getUTCMonth() + 1,
        date: normalized.getUTCDate(),
        hours: normalized.getUTCHours(),
        minutes: normalized.getUTCMinutes(),
        seconds: normalized.getUTCSeconds(),
        milliseconds: normalized.getUTCMilliseconds(),
    };
}

function decorateDate(date: Date, timeZone: string, DateConstructor: DateConstructor): Date {
    Object.defineProperties(date, {
        getFullYear: {
            value(this: Date): number {
                return getTimeZoneParts(this, timeZone, DateConstructor).year;
            },
        },
        getMonth: {
            value(this: Date): number {
                return getTimeZoneParts(this, timeZone, DateConstructor).month - 1;
            },
        },
        getDate: {
            value(this: Date): number {
                return getTimeZoneParts(this, timeZone, DateConstructor).date;
            },
        },
        getDay: {
            value(this: Date): number {
                const parts = getTimeZoneParts(this, timeZone, DateConstructor);

                return new DateConstructor(DateConstructor.UTC(parts.year, parts.month - 1, parts.date)).getUTCDay();
            },
        },
        getHours: {
            value(this: Date): number {
                return getTimeZoneParts(this, timeZone, DateConstructor).hours;
            },
        },
        getMinutes: {
            value(this: Date): number {
                return getTimeZoneParts(this, timeZone, DateConstructor).minutes;
            },
        },
        getSeconds: {
            value(this: Date): number {
                return getTimeZoneParts(this, timeZone, DateConstructor).seconds;
            },
        },
        getMilliseconds: {
            value(this: Date): number {
                return getTimeZoneParts(this, timeZone, DateConstructor).milliseconds;
            },
        },
        setDate: {
            value(this: Date, nextDayOfMonth: number): number {
                const parts = getTimeZoneParts(this, timeZone, DateConstructor);
                const nextDate = getDateFromTimeZoneParts(
                    {
                        ...parts,
                        date: nextDayOfMonth,
                    },
                    timeZone,
                    DateConstructor,
                );

                return this.setTime(nextDate.getTime());
            },
        },
    });

    return date;
}

/**
 * Replaces global Date with a constructor whose local-time APIs behave as if the browser timezone were `timeZone`.
 * Returns a cleanup function that restores the original Date constructor and should be called in `finally`.
 *
 * @private
 */
export default function mockTimezone(timeZone: string): () => void {
    const OriginalDate = Date;

    const MockDate = function MockDate(...args: MockDateConstructorArguments): Date | string {
        if (!new.target) {
            return OriginalDate();
        }

        if (args.length >= 2) {
            return decorateDate(
                getDateFromTimeZoneParts(
                    normalizeConstructorParts(args as DatePartConstructorArguments, OriginalDate),
                    timeZone,
                    OriginalDate,
                ),
                timeZone,
                OriginalDate,
            );
        }

        const date = args.length === 0 ? new OriginalDate() : new OriginalDate(args[0]);

        return decorateDate(date, timeZone, OriginalDate);
    } as unknown as MockDateConstructor;

    MockDate.now = () => {
        return OriginalDate.now();
    };
    MockDate.UTC = OriginalDate.UTC;
    MockDate.parse = OriginalDate.parse;
    Object.defineProperty(MockDate, 'prototype', {
        value: OriginalDate.prototype,
    });

    global.Date = MockDate;

    return () => {
        global.Date = OriginalDate;
    };
}
