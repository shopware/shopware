import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { MtBanner, MtModalTrigger, MtModalAction, MtButton } from '@shopware-ag/meteor-component-library';
import SwSettingsServicesIndex from './index';
import { useShopwareServicesStore } from '../../store/shopware-services.store';
import SwSettingsServicesHero from '../../component/sw-settings-services-hero';
import SwSettingsServicesGrantPermissionsCard from '../../component/sw-settings-services-grant-permissions-card';
import SwSettingsServicesRevokePermissionsModal from '../../component/sw-settings-services-revoke-permissions-modal';
import SwSettingsServicesDeactivateModal from '../../component/sw-settings-services-deactivate-modal';

describe('/src/module/sw-setting-services/page/sw-settings-services-index', () => {
    let originalLocation;

    beforeAll(() => {
        Shopware.Service().register('serviceRegistryClient', () => ({
            getCurrentRevision: jest.fn(async () => ({
                'latest-revision': '2025-06-25',
                'available-revisions': [
                    {
                        revision: '2025-06-25',
                        links: {
                            'feedback-url': 'https://shopware.com/feedback',
                            'docs-url': 'https://docs.shopware.com/services',
                            'tos-url': 'https://shopware.com/agb',
                        },
                    },
                ],
            })),
        }));

        Shopware.Service().register('shopwareServicesService', () => ({
            getInstalledServices: jest.fn(async () => [
                {
                    id: 'service-id',
                    active: true,
                    name: 'first-service-name',
                },
                {
                    id: 'service-id-2',
                    active: true,
                    name: 'second-service-name',
                },
            ]),
            getServicesContext: jest.fn(async () => ({
                disabled: false,
                permissionsConsent: {
                    identifier: 'revision-id',
                    revision: '2025-06-25',
                    consentingUserId: 'user-id',
                    grantedAt: '2025-07-08',
                },
            })),
            acceptRevision: jest.fn(async () => ({
                disabled: false,
                permissionsConsent: {
                    identifier: 'revision-id',
                    revision: '2025-06-25',
                    consentingUserId: 'user-id',
                    grantedAt: '2025-07-08',
                },
            })),
            revokePermissions: jest.fn(async () => ({
                disabled: false,
                permissionsConsent: null,
            })),
            enableAllServices: jest.fn(async () => ({
                disabled: false,
                permissionsConsent: null,
            })),
        }));

        originalLocation = window.location;

        Object.defineProperty(window, 'location', { configurable: true, value: { reload: jest.fn() } });
    });

    afterAll(() => {
        Object.defineProperty(window, 'location', { configurable: true, value: originalLocation });
    });

    async function mountPage() {
        const pinia = createPinia();
        setActivePinia(pinia);
        useShopwareServicesStore();

        return mount(SwSettingsServicesIndex, {
            global: {
                stubs: {
                    'sw-page': {
                        template: `
                        <div class="sw-page">
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                    },
                    'sw-settings-services-service-card': true,
                },
                plugins: [pinia],
            },
        });
    }

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

    it('shows correct links in the footer', async () => {
        const page = await mountPage();
        await flushPromises();

        const footerLinks = page.findAll('.sw-settings-services__footer a');

        expect(footerLinks).toHaveLength(2);

        const [
            documentationLink,
            tosLink,
        ] = footerLinks;

        expect(documentationLink.attributes('href')).toBe('https://docs.shopware.com/services');
        expect(tosLink.attributes('href')).toBe('https://shopware.com/agb');
    });

    it('can grant permissions', async () => {
        Shopware.Service('shopwareServicesService').getServicesContext.mockImplementationOnce(async () => ({
            disabled: false,
            permissionConsent: null,
        }));

        const page = await mountPage();
        await flushPromises();

        expect(page.findComponent(SwSettingsServicesRevokePermissionsModal).exists()).toBe(false);

        const grantPermissionsCard = page.getComponent(SwSettingsServicesGrantPermissionsCard);

        await grantPermissionsCard.get('.mt-button--primary').trigger('click');
        await flushPromises();

        expect(window.location.reload).toHaveBeenCalled();
    });

    it('can revoke permissions', async () => {
        const page = await mountPage();
        await flushPromises();

        const revokePermissionsModal = page.getComponent(SwSettingsServicesRevokePermissionsModal);

        await revokePermissionsModal.getComponent(MtModalTrigger).trigger('click');
        await revokePermissionsModal.getComponent(MtModalAction).trigger('click');
        await flushPromises();

        expect(window.location.reload).toHaveBeenCalled();
    });

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

    it('can activate services', async () => {
        Shopware.Service('shopwareServicesService').getInstalledServices.mockImplementationOnce(async () => []);

        Shopware.Service('shopwareServicesService').getServicesContext.mockImplementationOnce(async () => ({
            disabled: true,
            permissionConsent: null,
        }));

        const page = await mountPage();
        await flushPromises();

        const activateBanner = page.getComponent(MtBanner);

        await activateBanner.getComponent(MtButton).trigger('click');
        await flushPromises();

        expect(page.findComponent(SwSettingsServicesGrantPermissionsCard).exists()).toBe(true);
        expect(page.findAll('sw-settings-services-service-card-stub')).toHaveLength(0);
        expect(page.find('.sw-settings-services-index__installing-card').exists()).toBe(true);
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
