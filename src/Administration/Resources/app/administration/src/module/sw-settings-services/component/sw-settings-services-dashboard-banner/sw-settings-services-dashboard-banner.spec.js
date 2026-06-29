import { mount } from '@vue/test-utils';
import SwSettingsServicesDashboardBanner from './index';
import 'src/app/store/admin-user-config.store';

describe('src/module/sw-settings-services/component/sw-settings-services-dashboard-banner', () => {
    beforeEach(() => {
        Shopware.Store.get('adminUserConfig').$reset();
        jest.spyOn(Shopware.Store.get('adminUserConfig'), 'get').mockResolvedValue(undefined);
        jest.spyOn(Shopware.Store.get('adminUserConfig'), 'upsert').mockResolvedValue();
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('shows banner if user config is not set', async () => {
        const dashboardBanner = await mount(SwSettingsServicesDashboardBanner);
        await flushPromises();

        expect(dashboardBanner.get('.mt-banner')).toBeTruthy();
    });

    it('shows banner if core.show-services-dashboard-banner is set to false', async () => {
        Shopware.Store.get('adminUserConfig').get.mockResolvedValue([false]);

        const dashboardBanner = await mount(SwSettingsServicesDashboardBanner);
        await flushPromises();

        expect(dashboardBanner.get('.mt-banner')).toBeTruthy();
    });

    it('hides banner if core.show-services-dashboard-banner is set to false', async () => {
        Shopware.Store.get('adminUserConfig').get.mockResolvedValue([true]);

        const dashboardBanner = await mount(SwSettingsServicesDashboardBanner);
        await flushPromises();

        expect(dashboardBanner.find('.mt-banner').exists()).toBe(false);
    });

    it('can be hidden', async () => {
        const dashboardBanner = await mount(SwSettingsServicesDashboardBanner);
        await flushPromises();

        const closeButton = dashboardBanner.get('button.mt-banner__close');
        await closeButton.trigger('click');
        await flushPromises();

        expect(dashboardBanner.find('.mt-banner').exists()).toBe(false);
        expect(Shopware.Store.get('adminUserConfig').upsert).toHaveBeenCalledWith({
            'core.hide-services-dashboard-banner': [true],
        });
    });

    it('opens the services overview', async () => {
        const routerMock = { push: jest.fn() };

        const dashboardBanner = await mount(SwSettingsServicesDashboardBanner, {
            global: {
                mocks: {
                    $router: routerMock,
                },
            },
        });
        await flushPromises();

        const exploreNowButton = dashboardBanner.get('.mt-button.mt-button--primary');
        await exploreNowButton.trigger('click');

        expect(routerMock.push).toHaveBeenCalled();
    });
});
