/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import metaInfoPlugin from 'src/app/plugin/meta-info.plugin';

async function createWrapper(historyBack: string | null = '/sw/product/index') {
    const routerMock = {
        push: jest.fn(),
        go: jest.fn(),
        options: {
            history: {
                state: { back: historyBack },
            },
        },
    };

    const wrapper = mount(await wrapTestComponent('sw-not-found', { sync: true }), {
        global: {
            plugins: [metaInfoPlugin],
            stubs: {
                'sw-page': {
                    props: ['showSmartBar'],
                    template: '<div class="sw-page" :data-show-smart-bar="showSmartBar"><slot name="content"></slot></div>',
                },
            },
            mocks: {
                $router: routerMock,
            },
        },
    });

    return { wrapper, routerMock };
}

describe('src/app/component/structure/sw-not-found', () => {
    afterEach(() => {
        document.title = '';
        metaInfoPlugin.pluginInstalled = false;
    });

    it('should render the not found state in a page without smart bar', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.get('.sw-page').attributes('data-show-smart-bar')).toBe('false');

        const content = wrapper.get('.sw-not-found__content');
        expect(content.text()).toContain('sw-not-found.title');
        expect(content.text()).toContain('sw-not-found.description');
    });

    it('should set the document title to the not found title', async () => {
        await createWrapper();

        expect(document.title).toBe('sw-not-found.title | global.sw-admin-menu.textShopwareAdmin');
    });

    it('should navigate back to the previous page when the back button is clicked', async () => {
        const { wrapper, routerMock } = await createWrapper('/sw/product/index');
        const backButton = wrapper.get('.sw-not-found__back-button');

        expect(backButton.text()).toBe('global.default.back');

        await backButton.trigger('click');

        expect(routerMock.go).toHaveBeenCalledWith(-1);
        expect(routerMock.push).not.toHaveBeenCalled();
    });

    it('should navigate to the dashboard when there is no previous page', async () => {
        const { wrapper, routerMock } = await createWrapper(null);

        await wrapper.get('.sw-not-found__back-button').trigger('click');

        expect(routerMock.push).toHaveBeenCalledWith({ name: 'sw.dashboard.index' });
        expect(routerMock.go).not.toHaveBeenCalled();
    });
});
