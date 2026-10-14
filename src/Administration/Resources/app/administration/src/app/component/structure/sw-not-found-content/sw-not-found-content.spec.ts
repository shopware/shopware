/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

const routerPush = jest.fn();

async function createWrapper() {
    return mount(await wrapTestComponent('sw-not-found-content', { sync: true }), {
        global: {
            mocks: {
                $router: { push: routerPush },
            },
        },
    });
}

describe('src/app/component/structure/sw-not-found-content', () => {
    beforeEach(() => {
        routerPush.mockClear();
    });

    it('should navigate to the dashboard when the button is clicked', async () => {
        const wrapper = await createWrapper();
        const dashboardButton = wrapper.find('.sw-not-found-content__dashboard-button');

        expect(dashboardButton.text()).toBe('sw-not-found.buttonDashboard');

        await dashboardButton.trigger('click');

        expect(routerPush).toHaveBeenCalledWith({ name: 'sw.dashboard.index' });
    });
});
