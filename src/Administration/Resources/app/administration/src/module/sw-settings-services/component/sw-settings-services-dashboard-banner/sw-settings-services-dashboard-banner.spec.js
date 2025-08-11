import { mount } from '@vue/test-utils';
import MockAdapter from 'axios-mock-adapter';
import createHTTPClient from '../../../../core/factory/http.factory';
import createLoginService from '../../../../core/service/login.service';
import UserConfigService from '../../../../core/service/api/user-config.api.service';
import SwSettingsServicesDashboardBanner from './index';

describe('src/module/sw-settings-services/component/sw-settings-services-dashboard-banner', () => {
    let axiosMock;

    beforeAll(() => {
        const httpClient = createHTTPClient();
        const loginService = createLoginService(httpClient, Shopware.Context.api);

        axiosMock = new MockAdapter(httpClient);

        Shopware.Service().register('userConfigService', () => new UserConfigService(httpClient, loginService));
    });

    it('shows banner if user config is not set', async () => {
        axiosMock.onGet('_info/config-me').replyOnce(204);

        const dashboardBanner = await mount(SwSettingsServicesDashboardBanner);
        await flushPromises();

        expect(dashboardBanner.get('.mt-banner')).toBeTruthy();
    });

    it('shows banner if core.show-services-dashboard-banner is set to false', async () => {
        axiosMock.onGet('_info/config-me').replyOnce(200, {
            data: { 'core.hide-services-dashboard-banner': [false] },
        });

        const dashboardBanner = await mount(SwSettingsServicesDashboardBanner);
        await flushPromises();

        expect(dashboardBanner.get('.mt-banner')).toBeTruthy();
    });

    it('hides banner if core.show-services-dashboard-banner is set to false', async () => {
        axiosMock.onGet('_info/config-me').replyOnce(200, {
            data: { 'core.hide-services-dashboard-banner': [true] },
        });

        const dashboardBanner = await mount(SwSettingsServicesDashboardBanner);
        await flushPromises();

        expect(dashboardBanner.find('.mt-banner').exists()).toBe(false);
    });

    it('can be hidden', async () => {
        axiosMock.onGet('_info/config-me').replyOnce(200, {
            data: {},
        });

        axiosMock
            .onPost('_info/config-me', {
                'core.hide-services-dashboard-banner': [true],
            })
            .replyOnce(204);

        const dashboardBanner = await mount(SwSettingsServicesDashboardBanner);
        await flushPromises();

        const closeButton = dashboardBanner.get('button.mt-banner__close');
        await closeButton.trigger('click');
        await flushPromises();

        expect(dashboardBanner.find('.mt-banner').exists()).toBe(false);
    });

    it('opens the services overview', async () => {
        axiosMock.onGet('_info/config-me').replyOnce(200, {
            data: {},
        });

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
