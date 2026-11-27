import { MtBanner } from '@shopware-ag/meteor-component-library';
import SwSettingsServicesHero from '../../../component/sw-settings-services-hero';
import SwSettingsServicesGrantPermissionsCard from '../../../component/sw-settings-services-grant-permissions-card';
import SwSettingsServicesRevokePermissionsModal from '../../../component/sw-settings-services-revoke-permissions-modal';
import SwSettingsServicesDeactivateModal from '../../../component/sw-settings-services-deactivate-modal';
import { mountPage, registerServiceMocks, resetServicesConfig } from './create-wrapper';

describe('/src/module/sw-setting-services/page/sw-settings-services-index listing', () => {
    beforeAll(registerServiceMocks);
    beforeEach(resetServicesConfig);

    it('shows installed services', async () => {
        const page = await mountPage();
        await flushPromises();

        const hero = page.getComponent(SwSettingsServicesHero);

        expect(hero.props('documentationLink')).toBe('https://docs.shopware.com/services');
        expect(hero.props('feedbackLink')).toBe('https://shopware.com/feedback');

        expect(page.findComponent(SwSettingsServicesGrantPermissionsCard).exists()).toBe(false);
        expect(page.findComponent(MtBanner).exists()).toBe(false);

        expect(page.findAll('sw-settings-services-service-card-stub')).toHaveLength(2);

        expect(page.findComponent(SwSettingsServicesRevokePermissionsModal).exists()).toBe(true);
        expect(page.findComponent(SwSettingsServicesDeactivateModal).exists()).toBe(true);
    });

    it('passes Shopware Account services to the global action modals', async () => {
        const page = await mountPage();
        await flushPromises();

        expect(page.getComponent(SwSettingsServicesRevokePermissionsModal).props('servicesWithAccountRequirement')).toEqual([
            {
                name: 'second-service-name',
                label: 'Second Service',
            },
        ]);
        expect(page.getComponent(SwSettingsServicesDeactivateModal).props('servicesWithAccountRequirement')).toEqual([
            {
                name: 'second-service-name',
                label: 'Second Service',
            },
        ]);
    });

    it('shows correct links in the footer', async () => {
        const page = await mountPage();
        await flushPromises();

        const footerLinks = page.findAll('.sw-settings-services__footer a');

        expect(footerLinks).toHaveLength(2);

        const [documentationLink, tosLink] = footerLinks;

        expect(documentationLink.attributes('href')).toBe('https://docs.shopware.com/services');
        expect(tosLink.attributes('href')).toBe('https://shopware.com/agb');
    });

    it('shows error banner', async () => {
        Shopware.Service('shopwareServicesService').getInstalledServices.mockImplementationOnce(async () => {
            throw new Error('failed loading services');
        });

        const page = await mountPage();
        await flushPromises();

        const errorBanner = page.getComponent(MtBanner);
        expect(errorBanner.props('variant')).toBe('critical');
        expect(errorBanner.text()).toContain('failed loading services');
    });
});
