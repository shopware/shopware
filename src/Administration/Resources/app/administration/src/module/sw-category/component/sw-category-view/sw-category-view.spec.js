/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';

const categoryIdMock = 'CATEGORY_MOCK_ID';

async function createWrapper(categoryType, { routeName = 'sw.category.detail.base', routerPush = jest.fn() } = {}) {
    Shopware.Store.get('swCategoryDetail').$reset();
    Shopware.Store.get('swCategoryDetail').category = {
        id: categoryIdMock,
    };
    Shopware.Store.get('swCategoryDetail').isCategoryColumn = true;

    Shopware.Store.unregister('cmsPage');
    Shopware.Store.register({
        id: 'cmsPage',
        state: () => ({
            currentPage: undefined,
        }),
    });

    return mount(await wrapTestComponent('sw-category-view', { sync: true }), {
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
                placeholder: (entity, field, fallbackSnippet) => {
                    return {
                        entity,
                        field,
                        fallbackSnippet,
                    };
                },
            },
            provide: {},
        },
        props: {
            isLoading: false,
            type: categoryType,
        },
    });
}

describe('src/module/sw-category/component/sw-category-view', () => {
    beforeEach(() => {
        global.activeFeatureFlags = [];
    });

    it('should display static snippets and position-identifiers', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.getComponent('.sw-category-view').attributes('position-identifier')).toBe('sw-category-view');
        expect(wrapper.getComponent('.sw-language-info').props('entityDescription')).toStrictEqual({
            entity: {
                id: 'CATEGORY_MOCK_ID',
            },
            fallbackSnippet: 'sw-manufacturer.detail.textHeadline',
            field: 'name',
        });

        expect(wrapper.getComponent('[role="banner"]').props('variant')).toBe('info');
        expect(wrapper.get('.swag-category-view__column-info-header').text()).toBe('sw-category.view.columnInfoHeader');
        expect(wrapper.get('.swag-category-view__column-info-content').text()).toBe('sw-category.view.columnInfo');

        expect(wrapper.get('.sw-category-detail-page__tabs').attributes('position-identifier')).toBe('sw-category-view');
    });

    function checkGeneralTab(generalTab) {
        expect(generalTab.props()).toStrictEqual({
            route: { name: 'sw.category.detail.base' },
            title: 'sw-category.view.general',
        });
        expect(generalTab.text()).toBe('sw-category.view.general');
    }

    function checkProductTab(productTab) {
        expect(productTab.props()).toStrictEqual({
            route: { name: 'sw.category.detail.products' },
            title: 'sw-category.view.products',
        });
        expect(productTab.text()).toBe('sw-category.view.products');
    }

    function checkCustomEntityTab(customEntityTab) {
        expect(customEntityTab.props()).toStrictEqual({
            route: { name: 'sw.category.detail.customEntity' },
            title: 'sw-category.view.customEntity',
        });
        expect(customEntityTab.text()).toBe('sw-category.view.customEntity');
    }

    function checkCmsTab(cmsTab) {
        expect(cmsTab.props()).toStrictEqual({
            route: { name: 'sw.category.detail.cms' },
            title: 'sw-category.view.cms',
        });
        expect(cmsTab.text()).toBe('sw-category.view.cms');
    }

    function checkSeoTab(seoTab) {
        expect(seoTab.props()).toStrictEqual({
            route: { name: 'sw.category.detail.seo' },
            title: 'sw-category.view.seo',
        });
        expect(seoTab.text()).toBe('sw-category.view.seo');
    }

    it('should display the tabs for the `page` category type', async () => {
        const wrapper = await createWrapper('page');

        const generalTab = wrapper.getComponent('.sw-category-detail__tab-base');
        checkGeneralTab(generalTab);

        const productTab = wrapper.getComponent('.sw-category-detail__tab-products');
        checkProductTab(productTab);

        const customEntityTab = wrapper.getComponent('.sw-category-detail__tab-custom-entity');
        expect(customEntityTab.isVisible()).toBe(false);

        const cmsTab = wrapper.getComponent('.sw-category-detail__tab-cms');
        checkCmsTab(cmsTab);

        const seoTab = wrapper.getComponent('.sw-category-detail__tab-seo');
        checkSeoTab(seoTab);
    });

    it('should display the tabs for the `folder` category type', async () => {
        const wrapper = await createWrapper('folder');

        const generalTab = wrapper.getComponent('.sw-category-detail__tab-base');
        checkGeneralTab(generalTab);

        const productTab = wrapper.getComponent('.sw-category-detail__tab-products');
        expect(productTab.isVisible()).toBe(false);

        const customEntityTab = wrapper.getComponent('.sw-category-detail__tab-custom-entity');
        expect(customEntityTab.isVisible()).toBe(false);

        const cmsTab = wrapper.getComponent('.sw-category-detail__tab-cms');
        expect(cmsTab.isVisible()).toBe(false);

        const seoTab = wrapper.getComponent('.sw-category-detail__tab-seo');
        expect(seoTab.isVisible()).toBe(false);
    });

    it('should display the tabs for the `link` category type', async () => {
        const wrapper = await createWrapper('link');

        const generalTab = wrapper.getComponent('.sw-category-detail__tab-base');
        checkGeneralTab(generalTab);

        const productTab = wrapper.getComponent('.sw-category-detail__tab-products');
        expect(productTab.isVisible()).toBe(false);

        const customEntityTab = wrapper.getComponent('.sw-category-detail__tab-custom-entity');
        expect(customEntityTab.isVisible()).toBe(false);

        const cmsTab = wrapper.getComponent('.sw-category-detail__tab-cms');
        expect(cmsTab.isVisible()).toBe(false);

        const seoTab = wrapper.getComponent('.sw-category-detail__tab-seo');
        expect(seoTab.isVisible()).toBe(false);
    });

    it('should display the tabs for the `custom_entity` category type', async () => {
        const wrapper = await createWrapper('custom_entity');

        const generalTab = wrapper.getComponent('.sw-category-detail__tab-base');
        checkGeneralTab(generalTab);

        const productTab = wrapper.getComponent('.sw-category-detail__tab-products');
        expect(productTab.isVisible()).toBe(false);

        const customEntityTab = wrapper.getComponent('.sw-category-detail__tab-custom-entity');
        checkCustomEntityTab(customEntityTab);

        const cmsTab = wrapper.getComponent('.sw-category-detail__tab-cms');
        checkCmsTab(cmsTab);

        const seoTab = wrapper.getComponent('.sw-category-detail__tab-seo');
        checkSeoTab(seoTab);
    });

    it('should render Meteor tab items for the `page` category type', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        const routerPush = jest.fn();
        const wrapper = await createWrapper('page', {
            routeName: 'sw.category.detail.cms',
            routerPush,
        });

        const tabs = wrapper.getComponent('.mt-tabs-stub');
        const items = tabs.props('items');

        expect(tabs.props('positionIdentifier')).toBe('sw-category-view');
        expect(tabs.props('defaultItem')).toBe('sw.category.detail.cms');
        expect(tabs.props('routeTabs')).toBe(true);
        expect(items.map((item) => item.name)).toEqual([
            'sw.category.detail.base',
            'sw.category.detail.products',
            'sw.category.detail.cms',
            'sw.category.detail.seo',
        ]);
        expect(items.map((item) => item.label)).toEqual([
            'sw-category.view.general',
            'sw-category.view.products',
            'sw-category.view.cms',
            'sw-category.view.seo',
        ]);

        items[1].onClick();

        expect(routerPush).toHaveBeenCalledWith({ name: 'sw.category.detail.products' });
    });

    it('should filter Meteor tab items for the `folder` category type', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        const wrapper = await createWrapper('folder', {
            routeName: 'sw.category.detail.products',
        });

        const tabs = wrapper.getComponent('.mt-tabs-stub');

        expect(tabs.props('defaultItem')).toBe('sw.category.detail.base');
        expect(tabs.props('items').map((item) => item.name)).toEqual([
            'sw.category.detail.base',
        ]);
    });

    it('should filter Meteor tab items for the `custom_entity` category type', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        const wrapper = await createWrapper('custom_entity');

        expect(
            wrapper
                .getComponent('.mt-tabs-stub')
                .props('items')
                .map((item) => item.name),
        ).toEqual([
            'sw.category.detail.base',
            'sw.category.detail.customEntity',
            'sw.category.detail.cms',
            'sw.category.detail.seo',
        ]);
    });
});
