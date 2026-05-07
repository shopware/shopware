/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';

async function createWrapper({ routeName = 'sw.category.landingPageDetail.base', routerPush = jest.fn() } = {}) {
    Shopware.Store.get('swCategoryDetail').$reset();
    Shopware.Store.get('swCategoryDetail').landingPage = {
        id: 'landing-page-id',
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
                'sw-language-info': {
                    template: '<div class="sw-language-info"></div>',
                    props: ['entityDescription'],
                },
                'sw-tabs': {
                    template: '<div class="sw-tabs"><slot /></div>',
                },
                'sw-tabs-item': {
                    template: '<div class="sw-tabs-item"><slot /></div>',
                    props: [
                        'route',
                        'title',
                        'disabled',
                    ],
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    template: '<div class="mt-tabs-stub"></div>',
                    props: {
                        items: {
                            type: Array,
                            required: true,
                        },
                        positionIdentifier: {
                            type: String,
                            required: false,
                            default: null,
                        },
                        defaultItem: {
                            type: String,
                            required: false,
                            default: null,
                        },
                        routeTabs: {
                            type: Boolean,
                            required: false,
                            default: false,
                        },
                    },
                },
                'router-view': {
                    template: '<div class="router-view"></div>',
                    props: ['isLoading'],
                },
            },
            mocks: {
                $route: {
                    name: routeName,
                },
                $router: {
                    push: routerPush,
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
    beforeEach(() => {
        global.activeFeatureFlags = [];
        global.activeAclRoles = [];
    });

    it('should display legacy tabs while the feature flag is inactive', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.getComponent('.sw-landing_page-view').attributes('position-identifier')).toBe('sw-landing-page-view');
        expect(wrapper.getComponent('.sw-language-info').props('entityDescription')).toBe(
            'sw-manufacturer.detail.textHeadline',
        );
        expect(wrapper.getComponent('.sw-category-detail__tab-base').props('route')).toStrictEqual({
            name: 'sw.category.landingPageDetail.base',
        });
        expect(wrapper.getComponent('.sw-landing-page-detail__tab-cms').props('disabled')).toBe(true);
    });

    it('should render Meteor tab items with ACL disabled state', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        const routerPush = jest.fn();
        const wrapper = await createWrapper({
            routeName: 'sw.category.landingPageDetail.cms',
            routerPush,
        });

        const tabs = wrapper.getComponent('.mt-tabs-stub');
        const items = tabs.props('items');

        expect(tabs.props('positionIdentifier')).toBe('sw-landing-page-view');
        expect(tabs.props('defaultItem')).toBe('sw.category.landingPageDetail.cms');
        expect(tabs.props('routeTabs')).toBe(true);
        expect(items.map((item) => ({ name: item.name, label: item.label, disabled: item.disabled }))).toEqual([
            {
                name: 'sw.category.landingPageDetail.base',
                label: 'sw-landing-page.view.general',
                disabled: undefined,
            },
            {
                name: 'sw.category.landingPageDetail.cms',
                label: 'sw-landing-page.view.cms',
                disabled: true,
            },
        ]);

        items[0].onClick();

        expect(routerPush).toHaveBeenCalledWith({ name: 'sw.category.landingPageDetail.base' });
    });

    it('should enable the Meteor CMS tab with landing page editor privileges', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        global.activeAclRoles = ['landing_page.editor'];
        const wrapper = await createWrapper();

        expect(wrapper.getComponent('.mt-tabs-stub').props('items')[1].disabled).toBe(false);
    });
});
