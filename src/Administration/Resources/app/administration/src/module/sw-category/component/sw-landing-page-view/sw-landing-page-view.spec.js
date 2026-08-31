/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';

const landingPageIdMock = 'LANDING_PAGE_MOCK_ID';

async function createWrapper({
    featureActive = false,
    routeName = 'sw.category.landingPageDetail.base',
    routerPush = jest.fn(),
    canEditLandingPage = true,
} = {}) {
    Shopware.Store.get('swCategoryDetail').$reset();
    Shopware.Store.get('swCategoryDetail').landingPage = {
        id: landingPageIdMock,
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
                        'disabled',
                        'route',
                        'title',
                    ],
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    template: '<div class="mt-tabs"></div>',
                    props: {
                        defaultItem: {
                            type: String,
                            required: false,
                            default: undefined,
                        },
                        items: {
                            type: Array,
                            required: true,
                        },
                        positionIdentifier: {
                            type: String,
                            required: true,
                        },
                    },
                },
                'mt-banner': {
                    name: 'mt-banner',
                    template: '<div class="mt-banner"><slot /></div>',
                    props: ['variant'],
                },
                'router-view': {
                    template: '<div class="router-view"></div>',
                    props: ['isLoading'],
                },
            },
            mocks: {
                placeholder: (entity, field, fallbackSnippet) => {
                    return {
                        entity,
                        field,
                        fallbackSnippet,
                    };
                },
                $route: {
                    name: routeName,
                },
                $router: {
                    push: routerPush,
                },
            },
            provide: {
                acl: {
                    can: (privilege) => privilege === 'landing_page.editor' && canEditLandingPage,
                },
                feature: {
                    isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
                },
            },
            directives: {
                tooltip: {},
            },
        },
        props: {
            isLoading: false,
        },
    });
}

describe('src/module/sw-category/component/sw-landing-page-view', () => {
    it('should display static snippets and position-identifiers', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.getComponent('.sw-landing_page-view').attributes('position-identifier')).toBe('sw-landing-page-view');
        expect(wrapper.getComponent('.sw-language-info').props('entityDescription')).toBe(
            'sw-manufacturer.detail.textHeadline',
        );

        expect(wrapper.get('.sw-customer-detail-page__tabs').attributes('position-identifier')).toBe('sw-landing-page-view');
    });

    it('should render the deprecated tabs when the major feature flag is inactive', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-tabs').exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should keep the fallback tab route contract', async () => {
        const wrapper = await createWrapper();
        const generalTab = wrapper.getComponent('.sw-category-detail__tab-base');
        const cmsTab = wrapper.getComponent('.sw-landing-page-detail__tab-cms');

        expect(generalTab.props('route')).toStrictEqual({ name: 'sw.category.landingPageDetail.base' });
        expect(generalTab.props('title')).toBe('sw-landing-page.view.general');
        expect(generalTab.text()).toBe('sw-landing-page.view.general');

        expect(cmsTab.props('route')).toStrictEqual({ name: 'sw.category.landingPageDetail.cms' });
        expect(cmsTab.props('title')).toBe('sw-landing-page.view.cms');
        expect(cmsTab.props('disabled')).toBe(false);
        expect(cmsTab.text()).toBe('sw-landing-page.view.cms');
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        const wrapper = await createWrapper({
            featureActive: true,
            routeName: 'sw.category.landingPageDetail.cms',
        });

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-landing-page-view');
        expect(tabs.props('defaultItem')).toBe('sw.category.landingPageDetail.cms');
        expect(tabs.props('items')).toEqual([
            expect.objectContaining({
                label: 'sw-landing-page.view.general',
                name: 'sw.category.landingPageDetail.base',
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-landing-page.view.cms',
                name: 'sw.category.landingPageDetail.cms',
                disabled: false,
                onClick: expect.any(Function),
            }),
        ]);
        expect(wrapper.find('.sw-tabs').exists()).toBe(false);
    });

    it('should pass the cms tab disabled state to meteor tabs', async () => {
        const wrapper = await createWrapper({
            featureActive: true,
            canEditLandingPage: false,
        });
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });
        const cmsTab = tabs.props('items').find((item) => item.name === 'sw.category.landingPageDetail.cms');

        expect(cmsTab).toEqual(
            expect.objectContaining({
                disabled: true,
            }),
        );
    });

    it('should render a cms permission warning banner for meteor tabs without landing page editor permission', async () => {
        const wrapper = await createWrapper({
            featureActive: true,
            canEditLandingPage: false,
        });
        const banner = wrapper.get('.sw-landing-page-view__cms-permission-warning');

        expect(banner.text()).toBe('sw-privileges.tooltip.warning');
        expect(wrapper.getComponent({ name: 'mt-banner' }).props('variant')).toBe('attention');
    });

    it('should not render a cms permission warning banner for meteor tabs with landing page editor permission', async () => {
        const wrapper = await createWrapper({
            featureActive: true,
            canEditLandingPage: true,
        });

        expect(wrapper.find('.sw-landing-page-view__cms-permission-warning').exists()).toBe(false);
    });

    it('should navigate when a meteor tab item is clicked', async () => {
        const routerPush = jest.fn();
        const wrapper = await createWrapper({
            featureActive: true,
            routerPush,
        });
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });
        const cmsTab = tabs.props('items').find((item) => item.name === 'sw.category.landingPageDetail.cms');

        cmsTab.onClick();

        expect(routerPush).toHaveBeenCalledWith({ name: 'sw.category.landingPageDetail.cms' });
    });
});
