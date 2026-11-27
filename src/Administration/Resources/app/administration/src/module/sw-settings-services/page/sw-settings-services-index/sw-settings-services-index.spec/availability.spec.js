import { MtButton, MtBanner } from '@shopware-ag/meteor-component-library';
import SwSettingsServicesGrantPermissionsCard from '../../../component/sw-settings-services-grant-permissions-card';
import SwSettingsServicesRevokePermissionsModal from '../../../component/sw-settings-services-revoke-permissions-modal';
import SwSettingsServicesDeactivateModal from '../../../component/sw-settings-services-deactivate-modal';
import { mountPage, registerServiceMocks, resetServicesConfig } from './create-wrapper';

describe('/src/module/sw-setting-services/page/sw-settings-services-index availability', () => {
    beforeAll(registerServiceMocks);
    beforeEach(resetServicesConfig);

    it('does not show grant permissions card if services are deactivated', async () => {
        Shopware.Service('shopwareServicesService').getInstalledServices.mockImplementationOnce(async () => []);

        Shopware.Service('shopwareServicesService').getServicesContext.mockImplementationOnce(async () => ({
            disabled: true,
            permissionConsent: null,
        }));

        const page = await mountPage();
        await flushPromises();

        expect(page.findComponent(SwSettingsServicesGrantPermissionsCard).exists()).toBe(false);
        expect(page.findComponent(SwSettingsServicesRevokePermissionsModal).exists()).toBe(false);
        expect(page.findAll('sw-settings-services-service-card-stub')).toHaveLength(0);
    });

    it('shows installed services that remain available when services are deactivated', async () => {
        Shopware.Service('shopwareServicesService').getInstalledServices.mockImplementationOnce(async () => [
            {
                id: 'gmv-service-id',
                active: true,
                name: 'gmv',
                label: 'GMV',
                requirements: ['shopware_account'],
            },
        ]);

        Shopware.Service('shopwareServicesService').getServicesContext.mockImplementationOnce(async () => ({
            disabled: true,
            permissionsConsent: null,
        }));

        const page = await mountPage();
        await flushPromises();

        const activateBanner = page.getComponent(MtBanner);

        expect(activateBanner.props('variant')).toBe('attention');
        expect(page.findComponent(SwSettingsServicesGrantPermissionsCard).exists()).toBe(false);
        expect(page.findComponent(SwSettingsServicesRevokePermissionsModal).exists()).toBe(false);
        expect(page.findComponent(SwSettingsServicesDeactivateModal).exists()).toBe(false);
        expect(page.findAll('sw-settings-services-service-card-stub')).toHaveLength(1);
    });

    it('can activate services', async () => {
        Shopware.Service('shopwareServicesService')
            .getInstalledServices.mockImplementationOnce(async () => [])
            .mockImplementationOnce(async () => []);

        Shopware.Service('shopwareServicesService').getServicesContext.mockImplementationOnce(async () => ({
            disabled: true,
            permissionConsent: null,
        }));

        const page = await mountPage();
        await flushPromises();

        const activateBanner = page.getComponent(MtBanner);

        await activateBanner.getComponent(MtButton).trigger('click');
        await flushPromises();

        expect(page.findComponent(SwSettingsServicesGrantPermissionsCard).exists()).toBe(false);
        expect(page.findAll('sw-settings-services-service-card-stub')).toHaveLength(0);
        expect(page.find('.sw-settings-services-index__installing-card').exists()).toBe(true);
    });

    it('explains that ENABLE_SERVICES=auto keeps services off outside the prod environment', async () => {
        Shopware.Service('shopwareServicesService').getInstalledServices.mockImplementationOnce(async () => []);
        Shopware.Context.app.config.services = { enabled: false, setting: 'auto' };
        Shopware.Context.app.config.settings.appEnv = 'dev';
        const availableServicesCalls = Shopware.Service('shopwareServicesService').getAvailableServices.mock.calls.length;

        const page = await mountPage();
        await flushPromises();

        const banner = page.get('.sw-settings-services-index__services-turned-off-banner');

        expect(banner.text()).toContain('sw-settings-services.index.turned-off.reason-auto');
        expect(banner.text()).not.toContain('sw-settings-services.index.turned-off.reason-disabled');
        expect(banner.findAll('.sw-settings-services-index__debug-options li').map((option) => option.text())).toEqual([
            'sw-settings-services.index.turned-off.option-enable',
            'sw-settings-services.index.turned-off.option-prod',
        ]);
        expect(page.find('.sw-settings-services__listing-section h3').exists()).toBe(false);
        expect(page.find('.sw-settings-services-index__installing-card').exists()).toBe(false);
        expect(Shopware.Service('shopwareServicesService').getAvailableServices).toHaveBeenCalledTimes(
            availableServicesCalls,
        );
    });

    it('explains that ENABLE_SERVICES turns services off', async () => {
        Shopware.Service('shopwareServicesService').getInstalledServices.mockImplementationOnce(async () => []);
        Shopware.Context.app.config.services = { enabled: false, setting: 'false' };
        Shopware.Service('shopwareServicesService').getServicesContext.mockImplementationOnce(async () => ({
            disabled: true,
            permissionsConsent: null,
        }));

        const page = await mountPage();
        await flushPromises();

        const banner = page.get('.sw-settings-services-index__services-turned-off-banner');

        expect(banner.text()).toContain('sw-settings-services.index.turned-off.reason-disabled');
        expect(banner.findAll('.sw-settings-services-index__debug-options li').map((option) => option.text())).toEqual([
            'sw-settings-services.index.turned-off.option-enable',
        ]);
        expect(page.find('.sw-settings-services-index__services-deactivated-banner').exists()).toBe(false);
        expect(page.find('.sw-settings-services-index__installing-card').exists()).toBe(false);
    });
});
