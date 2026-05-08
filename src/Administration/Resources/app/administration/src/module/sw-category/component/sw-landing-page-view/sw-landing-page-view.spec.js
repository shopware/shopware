/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';

async function createWrapper(routeName = 'sw.category.landingPageDetail.base') {
    Shopware.Store.get('swCategoryDetail').$reset();
    Shopware.Store.get('swCategoryDetail').landingPage = {
        id: 'LANDING_PAGE_ID',
        name: 'Landing page',
    };

    Shopware.Store.unregister('cmsPage');
    Shopware.Store.register({
        id: 'cmsPage',
        state: () => ({
            currentPage: undefined,
        }),
    });

    return mount(await wrapTestComponent('sw-landing-page-view', { sync: true }), {
        global: {
            stubs: {
                'sw-card-view': {
                    template: '<div class="sw-card-view"><slot /></div>',
                },
                'sw-language-info': true,
                'sw-tabs': {
                    name: 'sw-tabs',
                    template: '<div class="sw-tabs"><slot /></div>',
                },
                'sw-tabs-item': {
                    template: '<div class="sw-tabs-item"><slot /></div>',
                    props: [
                        'disabled',
                        'route',
                        'title',
                    ],
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    props: ['defaultItem', 'items', 'positionIdentifier'],
                    template: '<div />',
                },
                'router-view': true,
            },
            mocks: {
                $route: {
                    name: routeName,
                },
                $router: {
                    push: jest.fn(),
                },
                placeholder: (entity, field, fallbackSnippet) => ({
                    entity,
                    field,
                    fallbackSnippet,
                }),
            },
        },
        props: {
            isLoading: false,
        },
    });
}

describe('src/module/sw-category/component/sw-landing-page-view', () => {
    afterEach(() => {
        global.activeAclRoles = [];
        global.activeFeatureFlags = [];
    });

    it('renders legacy sw-tabs when the major migration is inactive', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('renders mt-tabs with landing page items when the major migration is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        global.activeAclRoles = ['landing_page.editor'];

        const wrapper = await createWrapper();
        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
        expect(mtTabs.props('positionIdentifier')).toBe('sw-landing-page-view');
        expect(mtTabs.props('defaultItem')).toBe('general');
        expect(mtTabs.props('items')).toEqual([
            {
                label: 'sw-landing-page.view.general',
                name: 'general',
                route: { name: 'sw.category.landingPageDetail.base' },
                onClick: expect.any(Function),
            },
            {
                label: 'sw-landing-page.view.cms',
                name: 'cms',
                route: { name: 'sw.category.landingPageDetail.cms' },
                disabled: false,
                onClick: expect.any(Function),
            },
        ]);
    });

    it('disables the CMS mt-tabs item without landing page editor privileges', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();
        const cmsTab = wrapper.findComponent({ name: 'mt-tabs' }).props('items')[1];

        expect(cmsTab.disabled).toBe(true);
    });

    it('uses the CMS tab as default item on direct CMS routes', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper('sw.category.landingPageDetail.cms');

        expect(wrapper.findComponent({ name: 'mt-tabs' }).props('defaultItem')).toBe('cms');
    });

    it('does not navigate to the CMS route without landing page editor privileges', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();
        const cmsTab = wrapper.findComponent({ name: 'mt-tabs' }).props('items')[1];

        cmsTab.onClick();

        expect(wrapper.vm.$router.push).not.toHaveBeenCalled();
    });
});
