/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';
import swCategoryView from './index';

const categoryIdMock = 'CATEGORY_MOCK_ID';

function createTabs({ isPage = true, isCustomEntity = false, cmsPage = false, hasCategoryError = false } = {}) {
    const routerPush = jest.fn(() => Promise.resolve());
    const tabs = swCategoryView.computed.tabs.call({
        isPage,
        isCustomEntity,
        cmsPage,
        swCategoryViewError: hasCategoryError,
        $router: {
            push: routerPush,
        },
        $t: (snippet) => snippet,
    });

    return {
        routerPush,
        tabs,
    };
}

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
                    template: '<div class="sw-tabs"><slot /></div>',
                },
                'mt-tabs': true,
                'sw-tabs-item': {
                    template: '<div class="sw-tabs-item"><slot /></div>',
                    props: [
                        'route',
                        'title',
                    ],
                },
                'router-view': {
                    template: '<div class="router-view"></div>',
                    props: ['isLoading'],
                },
            },
            mocks: {
                feature: {
                    isActive: () => false,
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
    it('builds mt-tabs route items for page categories', () => {
        const { tabs } = createTabs({ hasCategoryError: true });

        expect(tabs).toEqual([
            expect.objectContaining({
                label: 'sw-category.view.general',
                name: 'sw.category.detail.base',
                hasError: true,
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
    });

    it('builds mt-tabs route items for custom entity categories', () => {
        const { tabs } = createTabs({
            isPage: true,
            isCustomEntity: true,
            cmsPage: true,
        });

        expect(tabs).toEqual([
            expect.objectContaining({
                name: 'sw.category.detail.base',
            }),
            expect.objectContaining({
                name: 'sw.category.detail.customEntity',
            }),
            expect.objectContaining({
                name: 'sw.category.detail.cms',
            }),
            expect.objectContaining({
                name: 'sw.category.detail.seo',
            }),
        ]);
        expect(tabs).not.toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    name: 'sw.category.detail.products',
                }),
            ]),
        );
    });

    it('pushes the matching category route when a tab is clicked', () => {
        const { routerPush, tabs } = createTabs();

        tabs[0].onClick();
        tabs[1].onClick();
        tabs[2].onClick();
        tabs[3].onClick();

        expect(routerPush).toHaveBeenNthCalledWith(1, { name: 'sw.category.detail.base' });
        expect(routerPush).toHaveBeenNthCalledWith(2, { name: 'sw.category.detail.products' });
        expect(routerPush).toHaveBeenNthCalledWith(3, { name: 'sw.category.detail.cms' });
        expect(routerPush).toHaveBeenNthCalledWith(4, { name: 'sw.category.detail.seo' });
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
});
