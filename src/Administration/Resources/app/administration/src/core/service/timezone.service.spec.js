/**
 * @package services-settings
 */
import TimezoneService from './timezone.service';

describe('src/core/service/timezone.service.ts', () => {
    it('is registered correctly', () => {
        const timezoneService = new TimezoneService();

        expect(timezoneService).toBeInstanceOf(TimezoneService);
    });

    describe('loadTimezones', () => {
        it('is defined', () => {
            const timezoneService = new TimezoneService();

            expect(timezoneService.loadTimezones).toBeDefined();
        });

        it('returns data correctly', async () => {
            jest.mock('@vvo/tzdb/time-zones-names.json', () => ({
                default: [
                    'America/New_York',
                    'Europe/Berlin',
                    'Asia/Ho_Chi_Minh',
                ],
            }));

            const timezoneService = new TimezoneService();

            const timeZoneResult = await timezoneService.loadTimezones();
            expect(timeZoneResult.default).toEqual(
                expect.arrayContaining([
                    'America/New_York',
                    'Europe/Berlin',
                    'Asia/Ho_Chi_Minh',
                ]),
            );
        });
    });

    describe('getTimezoneOptions', () => {
        it('is defined', () => {
            const timezoneService = new TimezoneService();

            expect(timezoneService.getTimezoneOptions).toBeDefined();
        });
    });

    describe('toUTCTime', () => {
        it('returns data correctly', () => {
            const timezoneService = new TimezoneService();

            expect(timezoneService.toUTCTime(0)).toBe('(UTC)');
            expect(timezoneService.toUTCTime(120)).toBe('(UTC +02:00)');
            expect(timezoneService.toUTCTime(-120)).toBe('(UTC -02:00)');
        });
    });
});
