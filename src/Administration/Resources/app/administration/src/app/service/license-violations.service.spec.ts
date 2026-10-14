import createLicenseViolationsService from './license-violations.service';

describe('app/service/license-violations.service', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it.each([
        'localhost',
        'shopware.local',
        'admin.shopware.local',
        'ddev.site',
        'shopware.ddev.site',
        'admin.shopware.ddev.site',
    ])('skips license checks for %s', async (hostname) => {
        const storeService = { getLicenseViolationList: jest.fn() };
        const service = createLicenseViolationsService(storeService);
        jest.spyOn(service, '_getLocationHostname').mockReturnValue(hostname);

        await expect(service.checkForLicenseViolations()).resolves.toEqual({
            warnings: [],
            violations: [],
            other: [],
        });
        expect(storeService.getLicenseViolationList).not.toHaveBeenCalled();
    });

    it.each([
        'example.com',
        'notddev.site',
        'ddev.site.example.com',
    ])('checks licenses for %s', async (hostname) => {
        const storeService = { getLicenseViolationList: jest.fn().mockResolvedValue({ items: [] }) };
        const service = createLicenseViolationsService(storeService);
        jest.spyOn(service, '_getLocationHostname').mockReturnValue(hostname);

        await service.checkForLicenseViolations();

        expect(storeService.getLicenseViolationList).toHaveBeenCalledTimes(1);
    });
});
