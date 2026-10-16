/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(historyBack: string | null) {
    const routerMock = {
        push: jest.fn(),
        go: jest.fn(),
        options: {
            history: {
                state: { back: historyBack },
            },
        },
    };

    const wrapper = mount(await wrapTestComponent('sw-not-found-content', { sync: true }), {
        global: {
            mocks: {
                $router: routerMock,
            },
        },
    });

    return { wrapper, routerMock };
}

describe('src/app/component/structure/sw-not-found-content', () => {
    it('should navigate back to the previous page when the back button is clicked', async () => {
        const { wrapper, routerMock } = await createWrapper('/sw/product/index');
        const backButton = wrapper.find('.sw-not-found-content__back-button');

        expect(backButton.text()).toBe('global.default.back');

        await backButton.trigger('click');

        expect(routerMock.go).toHaveBeenCalledWith(-1);
        expect(routerMock.push).not.toHaveBeenCalled();
    });

    it('should navigate to the dashboard when there is no previous page', async () => {
        const { wrapper, routerMock } = await createWrapper(null);

        await wrapper.find('.sw-not-found-content__back-button').trigger('click');

        expect(routerMock.push).toHaveBeenCalledWith({ name: 'sw.dashboard.index' });
        expect(routerMock.go).not.toHaveBeenCalled();
    });
});
