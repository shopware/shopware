/**
 * @sw-package framework
 * @private
 */

function getTimeZoneParts(date, timeZone, DateConstructor) {
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
    const getPart = (type) => Number(parts.find((part) => part.type === type).value);

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

function getTimeZoneOffset(timeZone, utcMilliseconds, DateConstructor) {
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

function getDateFromTimeZoneParts(parts, timeZone, DateConstructor) {
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

function normalizeConstructorParts(args, DateConstructor) {
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

export default function mockTimezone(timeZone) {
    const OriginalDate = Date;

    class MockDate extends OriginalDate {
        constructor(...args) {
            if (args.length >= 2) {
                const date = getDateFromTimeZoneParts(
                    normalizeConstructorParts(args, OriginalDate),
                    timeZone,
                    OriginalDate,
                );

                super(date.getTime());

                return;
            }

            super(...args);
        }

        getFullYear() {
            return getTimeZoneParts(this, timeZone, OriginalDate).year;
        }

        getMonth() {
            return getTimeZoneParts(this, timeZone, OriginalDate).month - 1;
        }

        getDate() {
            return getTimeZoneParts(this, timeZone, OriginalDate).date;
        }

        getDay() {
            const parts = getTimeZoneParts(this, timeZone, OriginalDate);

            return new OriginalDate(OriginalDate.UTC(parts.year, parts.month - 1, parts.date)).getUTCDay();
        }

        getHours() {
            return getTimeZoneParts(this, timeZone, OriginalDate).hours;
        }

        getMinutes() {
            return getTimeZoneParts(this, timeZone, OriginalDate).minutes;
        }

        getSeconds() {
            return getTimeZoneParts(this, timeZone, OriginalDate).seconds;
        }

        getMilliseconds() {
            return getTimeZoneParts(this, timeZone, OriginalDate).milliseconds;
        }

        setDate(date) {
            const parts = getTimeZoneParts(this, timeZone, OriginalDate);
            const nextDate = getDateFromTimeZoneParts({ ...parts, date }, timeZone, OriginalDate);

            return this.setTime(nextDate.getTime());
        }

        static now() {
            return OriginalDate.now();
        }
    }

    MockDate.UTC = OriginalDate.UTC;
    MockDate.parse = OriginalDate.parse;

    global.Date = MockDate;

    return () => {
        global.Date = OriginalDate;
    };
}
