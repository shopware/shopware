/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';
import ShopwareError from 'src/core/data/ShopwareError';

const categoryIdMock = 'CATEGORY_MOCK_ID';

async function createWrapper(
    categoryType,
    { featureActive = false, routeName = 'sw.category.detail.base', routerPush = jest.fn() } = {},
) {
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
                acl: {},
                feature: {
                    isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
                },
            },
        },
        props: {
            isLoading: false,
            type: categoryType,
        },
    });
}

describe('src/module/sw-category/component/sw-category-view', () => {
    afterEach(() => {
        Shopware.Store.get('error').resetApiErrors();
        jest.restoreAllMocks();
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

    it('should render the deprecated tabs when the major feature flag is inactive', async () => {
        const wrapper = await createWrapper('page');

        expect(wrapper.find('.sw-tabs').exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        const wrapper = await createWrapper('page', {
            featureActive: true,
            routeName: 'sw.category.detail.products',
        });

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-category-view');
        expect(tabs.props('defaultItem')).toBe('sw.category.detail.products');
        expect(tabs.props('items')).toEqual([
            expect.objectContaining({
                label: 'sw-category.view.general',
                name: 'sw.category.detail.base',
                hasError: false,
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-category.view.products',
                name: 'sw.category.detail.products',
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-category.view.cms',
                name: 'sw.category.detail.cms',
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-category.view.seo',
                name: 'sw.category.detail.seo',
                onClick: expect.any(Function),
            }),
        ]);
        expect(wrapper.find('.sw-tabs').exists()).toBe(false);
    });

    it.each([
        [
            'page',
            [
                'sw.category.detail.base',
                'sw.category.detail.products',
                'sw.category.detail.cms',
                'sw.category.detail.seo',
            ],
        ],
        [
            'folder',
            [
                'sw.category.detail.base',
            ],
        ],
        [
            'link',
            [
                'sw.category.detail.base',
            ],
        ],
        [
            'custom_entity',
            [
                'sw.category.detail.base',
                'sw.category.detail.customEntity',
                'sw.category.detail.cms',
                'sw.category.detail.seo',
            ],
        ],
    ])('should provide the expected meteor tabs for the `%s` category type', async (categoryType, expectedTabNames) => {
        const wrapper = await createWrapper(categoryType, { featureActive: true });
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('items').map((item) => item.name)).toEqual(expectedTabNames);
    });

    it('should navigate when a meteor tab item is clicked', async () => {
        const routerPush = jest.fn();
        const wrapper = await createWrapper('page', {
            featureActive: true,
            routerPush,
        });
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });
        const productsTab = tabs.props('items').find((item) => item.name === 'sw.category.detail.products');

        productsTab.onClick();

        expect(routerPush).toHaveBeenCalledWith({ name: 'sw.category.detail.products' });
    });

    it('should pass the general tab error state to meteor tabs', async () => {
        Shopware.Store.get('error').addApiError({
            expression: 'category.CATEGORY_MOCK_ID.name',
            error: new ShopwareError({
                code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                detail: 'This value should not be blank.',
                status: '400',
                template: 'This value should not be blank.',
            }),
        });

        const wrapper = await createWrapper('page', { featureActive: true });
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('items')[0]).toEqual(
            expect.objectContaining({
                hasError: true,
            }),
        );
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
