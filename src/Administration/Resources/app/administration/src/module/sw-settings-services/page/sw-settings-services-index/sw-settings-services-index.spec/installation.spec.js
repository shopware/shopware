import { MtButton, MtBanner } from '@shopware-ag/meteor-component-library';
import { mountPage, registerServiceMocks, resetServicesConfig } from './create-wrapper';

describe('/src/module/sw-setting-services/page/sw-settings-services-index installation', () => {
    beforeAll(registerServiceMocks);

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
        resetServicesConfig();
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

    const progressMocks = {
        $t(key, { installed, total } = {}) {
            if (key === 'sw-settings-services.index.installing-services-progress') {
                return `${installed} of ${total} services installed`;
            }

            return key;
        },
    };

    async function mountDeactivatedPage() {
        Shopware.Service('shopwareServicesService').getServicesContext.mockImplementationOnce(async () => ({
            disabled: true,
            permissionsConsent: null,
        }));

        const page = await mountPage(progressMocks);
        await flushPromises();

        return page;
    }

    async function activateServices(page) {
        await page.get('.sw-settings-services-index__services-deactivated-banner').getComponent(MtButton).trigger('click');
        await flushPromises();
    }

    it('shows the progress after activating and refreshes the list until all services are installed', async () => {
        const shopwareServicesService = Shopware.Service('shopwareServicesService');
        shopwareServicesService.getInstalledServices
            .mockImplementationOnce(async () => [])
            .mockImplementationOnce(async () => [firstService])
            .mockImplementationOnce(async () => [
                firstService,
                secondService,
            ]);
        shopwareServicesService.getAvailableServices.mockImplementation(async () => [
            { name: 'first-service-name' },
            { name: 'second-service-name' },
        ]);
        const availableServicesCalls = shopwareServicesService.getAvailableServices.mock.calls.length;

        const page = await mountDeactivatedPage();
        await activateServices(page);

        expect(page.findAll('sw-settings-services-service-card-stub')).toHaveLength(1);
        expect(page.get('.sw-settings-services-index__installing-card-progress').text()).toBe('1 of 2 services installed');

        jest.advanceTimersByTime(10_000);
        await flushPromises();

        expect(page.findAll('sw-settings-services-service-card-stub')).toHaveLength(2);
        expect(page.find('.sw-settings-services-index__installing-card').exists()).toBe(false);

        const callsAfterInstallation = shopwareServicesService.getInstalledServices.mock.calls.length;
        jest.advanceTimersByTime(60_000);
        await flushPromises();

        expect(shopwareServicesService.getInstalledServices).toHaveBeenCalledTimes(callsAfterInstallation);
        expect(shopwareServicesService.getAvailableServices).toHaveBeenCalledTimes(availableServicesCalls + 1);
    });

    it('treats services that do not install within the waiting time as unavailable', async () => {
        const shopwareServicesService = Shopware.Service('shopwareServicesService');
        shopwareServicesService.getInstalledServices
            .mockImplementationOnce(async () => [])
            .mockImplementation(async () => [firstService]);
        shopwareServicesService.getAvailableServices.mockImplementation(async () => [
            { name: 'first-service-name' },
            { name: 'second-service-name' },
        ]);

        const page = await mountDeactivatedPage();
        await activateServices(page);

        expect(page.find('.sw-settings-services-index__installing-card').exists()).toBe(true);

        for (let elapsed = 0; elapsed < 120_000; elapsed += 10_000) {
            jest.advanceTimersByTime(10_000);
            await flushPromises();
        }

        expect(page.find('.sw-settings-services-index__installing-card').exists()).toBe(false);
        expect(page.find('.sw-settings-services-index__installation-stalled-banner').exists()).toBe(false);
        expect(page.findAll('sw-settings-services-service-card-stub')).toHaveLength(1);
    });

    it('lists installed services without waiting for services the registry offers on top', async () => {
        const shopwareServicesService = Shopware.Service('shopwareServicesService');
        shopwareServicesService.getInstalledServices.mockImplementation(async () => [firstService]);
        shopwareServicesService.getAvailableServices.mockImplementation(async () => [
            { name: 'first-service-name' },
            { name: 'second-service-name' },
        ]);

        const page = await mountPage();
        await flushPromises();

        expect(page.find('.sw-settings-services-index__installing-card').exists()).toBe(false);
        expect(page.findAll('sw-settings-services-service-card-stub')).toHaveLength(1);

        const callsAfterLoading = shopwareServicesService.getInstalledServices.mock.calls.length;
        jest.advanceTimersByTime(30_000);
        await flushPromises();

        expect(shopwareServicesService.getInstalledServices).toHaveBeenCalledTimes(callsAfterLoading);
    });

    it('renders the page while the available services are still loading', async () => {
        const shopwareServicesService = Shopware.Service('shopwareServicesService');
        shopwareServicesService.getInstalledServices.mockImplementation(async () => []);
        shopwareServicesService.getAvailableServices.mockImplementation(() => new Promise(() => {}));

        const page = await mountPage();
        await flushPromises();

        expect(page.find('.sw-settings-services-index__installing-card').exists()).toBe(true);
        expect(page.find('.sw-settings-services-index__installing-card-progress').exists()).toBe(false);
    });

    it('leaves out the progress when the available services cannot be loaded', async () => {
        const shopwareServicesService = Shopware.Service('shopwareServicesService');
        shopwareServicesService.getInstalledServices.mockImplementation(async () => []);
        shopwareServicesService.getAvailableServices.mockImplementation(async () => {
            throw new Error('registry unavailable');
        });

        const page = await mountPage();
        await flushPromises();

        expect(page.find('.sw-settings-services-index__installing-card').exists()).toBe(true);
        expect(page.find('.sw-settings-services-index__installing-card-progress').exists()).toBe(false);
        expect(page.findComponent(MtBanner).exists()).toBe(false);
    });

    it('stops refreshing and offers to check again when the installation takes long', async () => {
        const shopwareServicesService = Shopware.Service('shopwareServicesService');
        shopwareServicesService.getInstalledServices.mockImplementation(async () => []);
        shopwareServicesService.getAvailableServices.mockImplementation(async () => [
            { name: 'first-service-name' },
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
