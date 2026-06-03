import { getServicesWithShopwareAccountRequirement, serviceHasShopwareAccountRequirement } from './index';

describe('src/module/sw-settings-services/requirements', () => {
    it('detects services with the Shopware Account requirement', () => {
        expect(serviceHasShopwareAccountRequirement(['shopware_account'])).toBe(true);
        expect(serviceHasShopwareAccountRequirement(['service_consent'])).toBe(false);
        expect(serviceHasShopwareAccountRequirement([])).toBe(false);
    });

    it('returns services with the Shopware Account requirement', () => {
        expect(
            getServicesWithShopwareAccountRequirement([
                {
                    name: 'gmv-reporting',
                    label: 'GMV Reporting',
                    requirements: ['shopware_account'],
                },
                {
                    name: 'regular-service',
                    label: 'Regular Service',
                    requirements: ['service_consent'],
                },
            ]),
        ).toEqual([
            {
                name: 'gmv-reporting',
                label: 'GMV Reporting',
            },
        ]);
    });
});
