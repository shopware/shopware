import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import {
    MtModal,
    MtModalClose,
    MtModalAction,
    MtModalTrigger,
    MtModalRoot,
    MtButton,
    MtBanner,
} from '@shopware-ag/meteor-component-library';
import SwSettingsServicesIndex from './index';
import { useShopwareServicesStore } from '../../store/shopware-services.store';
import SwSettingsServicesHero from '../../component/sw-settings-services-hero';
import SwSettingsServicesGrantPermissionsCard from '../../component/sw-settings-services-grant-permissions-card';
import SwSettingsServicesRevokePermissionsModal from '../../component/sw-settings-services-revoke-permissions-modal';
import SwSettingsServicesDeactivateModal from '../../component/sw-settings-services-deactivate-modal';
import * as permissionsComposable from '../../composables/permissions';

jest.mock('../../composables/permissions', () => {
    const useShopwareServicesStore = require('../../store/shopware-services.store').useShopwareServicesStore;
    const _reloadPageMock = jest.fn();
    return {
        async grantPermissions() {
            const store = useShopwareServicesStore();
            const revision = store.currentRevision?.revision;
            if (!revision) throw new Error('No revision available');
            await Shopware.Service('shopwareServicesService').acceptRevision(revision);
            _reloadPageMock();
        },
        async revokePermissions() {
            await Shopware.Service('shopwareServicesService').revokePermissions();
            _reloadPageMock();
        },
        _reloadPage: _reloadPageMock,
    };
});

describe('/src/module/sw-setting-services/page/sw-settings-services-index', () => {
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
                    label: 'First Service',
                    requirements: ['service_consent'],
                },
                {
                    id: 'service-id-2',
                    active: true,
                    name: 'second-service-name',
                    label: 'Second Service',
                    requirements: ['shopware_account'],
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
            getAvailableServices: jest.fn(async () => []),
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
    });

    beforeEach(() => {
        Shopware.Context.app.config.services = { enabled: true, setting: 'auto' };
        Shopware.Context.app.config.settings = { ...Shopware.Context.app.config.settings, appEnv: 'prod' };
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
                    'mt-modal': MtModal,
                    'mt-modal-close': MtModalClose,
                    'mt-modal-action': MtModalAction,
                    'mt-modal-trigger': MtModalTrigger,
                    'mt-modal-root': MtModalRoot,
                    'sw-extension-component-section': true,
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

        expect(permissionsComposable._reloadPage).toHaveBeenCalled();
    });

    it('can revoke permissions', async () => {
        const page = await mountPage();
        await flushPromises();

        const revokePermissionsModal = page.getComponent(SwSettingsServicesRevokePermissionsModal);

        await revokePermissionsModal.getComponent(MtModalTrigger).trigger('click');
        await revokePermissionsModal.getComponent(MtModalAction).trigger('click');
        await flushPromises();

        expect(permissionsComposable._reloadPage).toHaveBeenCalled();
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

    describe('while services install', () => {
        const firstService = {
            id: 'service-id',
            active: true,
            name: 'first-service-name',
            label: 'First Service',
            requirements: ['service_consent'],
        };

        const secondService = {
            id: 'service-id-2',
            active: true,
            name: 'second-service-name',
            label: 'Second Service',
            requirements: ['service_consent'],
        };

        let defaultInstalledServices;
        let defaultAvailableServices;

        beforeEach(() => {
            jest.useFakeTimers();

            const shopwareServicesService = Shopware.Service('shopwareServicesService');
            defaultInstalledServices = shopwareServicesService.getInstalledServices.getMockImplementation();
            defaultAvailableServices = shopwareServicesService.getAvailableServices.getMockImplementation();
        });

        afterEach(() => {
            jest.useRealTimers();

            const shopwareServicesService = Shopware.Service('shopwareServicesService');
            shopwareServicesService.getInstalledServices.mockImplementation(defaultInstalledServices);
            shopwareServicesService.getAvailableServices.mockImplementation(defaultAvailableServices);
        });

        it('shows pending services and refreshes the list until they are installed', async () => {
            const shopwareServicesService = Shopware.Service('shopwareServicesService');
            shopwareServicesService.getInstalledServices
                .mockImplementationOnce(async () => [firstService])
                .mockImplementationOnce(async () => [
                    firstService,
                    secondService,
                ]);
            shopwareServicesService.getAvailableServices.mockImplementation(async () => [
                { name: 'first-service-name', label: 'First Service' },
                { name: 'second-service-name', label: 'Second Service' },
            ]);
            const availableServicesCalls = shopwareServicesService.getAvailableServices.mock.calls.length;

            const page = await mountPage();
            await flushPromises();

            expect(page.findAll('sw-settings-services-service-card-stub')).toHaveLength(1);
            expect(page.get('.sw-settings-services-index__pending-service').text()).toContain('Second Service');

            jest.advanceTimersByTime(10_000);
            await flushPromises();

            expect(page.findAll('sw-settings-services-service-card-stub')).toHaveLength(2);
            expect(page.find('.sw-settings-services-index__pending-service').exists()).toBe(false);

            const callsAfterInstallation = shopwareServicesService.getInstalledServices.mock.calls.length;
            jest.advanceTimersByTime(60_000);
            await flushPromises();

            expect(shopwareServicesService.getInstalledServices).toHaveBeenCalledTimes(callsAfterInstallation);
            expect(shopwareServicesService.getAvailableServices).toHaveBeenCalledTimes(availableServicesCalls + 1);
        });

        it('stops refreshing and offers to check again when the installation takes long', async () => {
            const shopwareServicesService = Shopware.Service('shopwareServicesService');
            shopwareServicesService.getInstalledServices.mockImplementation(async () => []);
            shopwareServicesService.getAvailableServices.mockImplementation(async () => [
                { name: 'first-service-name', label: 'First Service' },
            ]);

            const page = await mountPage();
            await flushPromises();

            expect(page.find('.sw-settings-services-index__installation-stalled-banner').exists()).toBe(false);

            for (let elapsed = 0; elapsed < 120_000; elapsed += 10_000) {
                jest.advanceTimersByTime(10_000);
                await flushPromises();
            }

            const stalledBanner = page.get('.sw-settings-services-index__installation-stalled-banner');
            expect(stalledBanner.text()).toContain('sw-settings-services.index.installation-stalled.description');
            expect(stalledBanner.find('code').exists()).toBe(false);

            const callsWhenStalled = shopwareServicesService.getInstalledServices.mock.calls.length;
            jest.advanceTimersByTime(60_000);
            await flushPromises();

            expect(shopwareServicesService.getInstalledServices).toHaveBeenCalledTimes(callsWhenStalled);

            shopwareServicesService.getInstalledServices.mockImplementation(async () => [firstService]);

            await stalledBanner.getComponent(MtButton).trigger('click');
            await flushPromises();

            expect(page.find('.sw-settings-services-index__installation-stalled-banner').exists()).toBe(false);
            expect(page.findAll('sw-settings-services-service-card-stub')).toHaveLength(1);
        });
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
