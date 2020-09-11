import LicenseViolationService from 'src/app/service/license-violations.service';

const Application = Shopware.Application;

describe('app/service/license-violation.service.js', () => {
    const licenseViolationService = LicenseViolationService(Application.getContainer('service').storeService);

    it('should be an object', async () => {
        const type = typeof licenseViolationService;
        expect(type).toEqual('object');
    });

    it('should have the correct lastLicenseWarningsKey', async () => {
        expect(licenseViolationService.key.lastLicenseWarningsKey).toEqual('lastLicenseWarningsShowed');
    });

    it('should have the correct lastLicenseViolationsFetched', async () => {
        expect(licenseViolationService.key.lastLicenseFetchedKey).toEqual('lastLicenseViolationsFetched');
    });

    it('should have the correct licenseViolationCache', async () => {
        expect(licenseViolationService.key.responseCacheKey).toEqual('licenseViolationCache');
    });

    it('should have the correct licenseViolationShowViolations', async () => {
        expect(licenseViolationService.key.showViolationsKey).toEqual('licenseViolationShowViolations');
    });

    it('should save violation to cache', async () => {
        const expectValue = { foo: 'bar' };

        licenseViolationService.saveViolationsToCache(expectValue);

        const match = JSON.parse(localStorage.getItem('licenseViolationCache'));

        expect(expectValue).toEqual(match);
    });

    it('should get violation from cache', async () => {
        localStorage.setItem('licenseViolationCache', JSON.stringify({ test: true }));

        const violations = licenseViolationService.getViolationsFromCache();

        expect(violations).toEqual({ test: true });
    });

    it('should not be expired', async () => {
        const actualDate = new Date();
        const actualTimeValue = String(actualDate.getTime());

        localStorage.setItem('clockClock', actualTimeValue);

        const isExpired = licenseViolationService.isTimeExpired('clockClock');

        expect(isExpired).toBeFalsy();
    });

    it('should not be expired when time is two hour ago', async () => {
        const actualDate = new Date();
        actualDate.setHours(actualDate.getHours() - 2);
        const actualTimeValue = String(actualDate.getTime());

        localStorage.setItem('clockClock', actualTimeValue);

        const isExpired = licenseViolationService.isTimeExpired('clockClock');

        expect(isExpired).toBeFalsy();
    });

    it('should be expired when time is a year ago', async () => {
        const actualDate = new Date();
        actualDate.setFullYear(actualDate.getFullYear() - 1);
        const actualTimeValue = String(actualDate.getTime());

        localStorage.setItem('clockClock', actualTimeValue);

        const isExpired = licenseViolationService.isTimeExpired('clockClock');
        expect(isExpired).toBeTruthy();
    });

    it('should save the time to local storage', async () => {
        const actualDate = new Date();
        const actualTimeValue = String(actualDate.getTime());

        licenseViolationService.saveTimeToLocalStorage('clockClock');
        const localStorageTime = localStorage.getItem('clockClock');

        // Only compare the first time values to ignore timing issues
        expect(localStorageTime.slice(0, 5)).toEqual(actualTimeValue.slice(0, 5));
    });

    it('should reset all license violations', async () => {
        localStorage.setItem('licenseViolationShowViolations', 'hans');
        localStorage.setItem('lastLicenseViolationsFetched', 'franz');
        localStorage.setItem('licenseViolationCache', 'sams');

        licenseViolationService.resetLicenseViolations();

        expect(localStorage.getItem('licenseViolationShowViolations')).not.toEqual('hans');
        expect(localStorage.getItem('lastLicenseViolationsFetched')).not.toEqual('franz');
        expect(localStorage.getItem('licenseViolationCache')).not.toEqual('sams');
    });

    it('should ignore the plugin', async () => {
        licenseViolationService.ignorePlugin('TestIgnore', []);

        const ignoredPlugins = JSON.parse(localStorage.getItem('ignorePluginWarning'));

        expect(ignoredPlugins).toEqual(['TestIgnore']);
    });

    it('should not have any ignored plugins', async () => {
        localStorage.removeItem('ignorePluginWarning');

        const ignoredPlugins = licenseViolationService.getIgnoredPlugins();

        expect(ignoredPlugins).toEqual([]);
    });

    it('should have one ignored plugin', async () => {
        localStorage.setItem('ignorePluginWarning', '["IgnoreMe"]');

        const ignoredPlugins = licenseViolationService.getIgnoredPlugins();

        expect(ignoredPlugins).toEqual(['IgnoreMe']);
    });

    it('should filter the warnings', async () => {
        const warnings = [
            { name: 'Lorem' },
            { name: 'Ipsum' },
            { name: 'Dog' },
            { name: 'Non' },
            { name: 'Dolor' },
            { name: 'Cat' },
            { name: 'Sit' },
            { name: 'Amet' }
        ];

        const ignoreTheseWarnings = ['Dog', 'Cat'];
        const filteredWarnings = licenseViolationService.filterWarnings(warnings, ignoreTheseWarnings);

        const expected = [
            { name: 'Lorem' },
            { name: 'Ipsum' },
            { name: 'Non' },
            { name: 'Dolor' },
            { name: 'Sit' },
            { name: 'Amet' }
        ];

        expect(filteredWarnings).toEqual(expect.arrayContaining(expected));
    });

    it('should remove from local storage', async () => {
        localStorage.setItem('testKey', 'testValue');

        licenseViolationService.removeTimeFromLocalStorage('testKey');

        expect(localStorage.getItem('testKey')).toBeNull();
    });
});
