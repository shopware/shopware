/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';

const categoryIdMock = 'CATEGORY_MOCK_ID';

async function createWrapper(categoryType) {
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
                    name: 'sw-tabs',
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
                    props: ['items', 'positionIdentifier'],
                    template: '<div />',
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
    afterEach(() => {
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

    it('should render mt-tabs with category items when the major migration is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper('page');
        const mtTabs = wrapper.findComponent({ name: 'mt-tabs' });

        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
        expect(mtTabs.props('positionIdentifier')).toBe('sw-category-view');
        expect(mtTabs.props('items')).toEqual([
            {
                label: 'sw-category.view.general',
                name: 'general',
                route: { name: 'sw.category.detail.base' },
                hasError: false,
                onClick: expect.any(Function),
            },
            {
                label: 'sw-category.view.products',
                name: 'products',
                route: { name: 'sw.category.detail.products' },
                onClick: expect.any(Function),
            },
            {
                label: 'sw-category.view.cms',
                name: 'cms',
                route: { name: 'sw.category.detail.cms' },
                onClick: expect.any(Function),
            },
            {
                label: 'sw-category.view.seo',
                name: 'seo',
                route: { name: 'sw.category.detail.seo' },
                onClick: expect.any(Function),
            },
        ]);
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
});
