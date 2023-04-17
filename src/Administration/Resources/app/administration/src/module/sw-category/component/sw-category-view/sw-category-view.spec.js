import { shallowMount } from '@vue/test-utils';
import swCategoryView from 'src/module/sw-category/component/sw-category-view';

const categoryIdMock = 'CATEGORY_MOCK_ID';

Shopware.Component.register('sw-category-view', swCategoryView);

async function createWrapper(categoryType) {
    if (Shopware.State.get('swCategoryDetail')) {
        Shopware.State.unregisterModule('swCategoryDetail');
    }

    Shopware.State.registerModule('swCategoryDetail', {
        namespaced: true,
        state: {
            category: {
                id: categoryIdMock,
                isColumn: true,
            }
        }
    });

    if (Shopware.State.get('cmsPageState')) {
        Shopware.State.unregisterModule('cmsPageState');
    }

    Shopware.State.registerModule('cmsPageState', {
        namespaced: true,
        state: {
            currentPage: undefined,
        }
    });

    return shallowMount(await Shopware.Component.build('sw-category-view'), {
        stubs: {
            'sw-card-view': {
                template: '<div class="sw-card-view"><slot /></div>',
            },
            'sw-language-info': {
                template: '<div class="sw-language-info"></div>',
                props: ['entityDescription']
            },
            'sw-alert': {
                template: '<div class="sw-alert"><slot /></div>',
                props: ['variant']
            },
            'sw-tabs': {
                template: '<div class="sw-tabs"><slot /></div>',
            },
            'sw-tabs-item': {
                template: '<div class="sw-tabs-item"><slot /></div>',
                props: ['route', 'title']
            },
            'router-view': {
                template: '<div class="router-view"></div>',
                props: ['isLoading']
            }
        },
        mocks: {
            placeholder: (entity, field, fallbackSnippet) => {
                return {
                    entity, field, fallbackSnippet
                };
            },
        },
        provide: {},
        propsData: {
            isLoading: false,
            type: categoryType,
        },
    });
}

describe('src/module/sw-category/component/sw-category-view', () => {
    it('should display static snippets and position-identifiers', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.get('.sw-category-view').attributes('position-identifier')).toBe('sw-category-view');
        expect(wrapper.get('.sw-language-info').props('entityDescription')).toStrictEqual({
            entity: {
                id: 'CATEGORY_MOCK_ID',
                isColumn: true
            },
            fallbackSnippet: 'sw-manufacturer.detail.textHeadline',
            field: 'name'
        });

        expect(wrapper.get('.sw-alert').props('variant')).toBe('info');
        expect(wrapper.get('.swag-category-view__column-info-header').text()).toBe('sw-category.view.columnInfoHeader');
        expect(wrapper.get('.swag-category-view__column-info-content').text()).toBe('sw-category.view.columnInfo');

        expect(wrapper.get('.sw-customer-detail-page__tabs').attributes('position-identifier')).toBe('sw-category-view');
        expect(wrapper.get('.router-view').props()).toStrictEqual({ isLoading: false });
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

        const generalTab = wrapper.get('.sw-category-detail__tab-base');
        checkGeneralTab(generalTab);

        const productTab = wrapper.get('.sw-category-detail__tab-products');
        checkProductTab(productTab);

        const customEntityTab = wrapper.get('.sw-category-detail__tab-custom-entity');
        expect(customEntityTab.isVisible()).toBe(false);

        const cmsTab = wrapper.get('.sw-category-detail__tab-cms');
        checkCmsTab(cmsTab);

        const seoTab = wrapper.get('.sw-category-detail__tab-seo');
        checkSeoTab(seoTab);
    });

    it('should display the tabs for the `folder` category type', async () => {
        const wrapper = await createWrapper('folder');

        const generalTab = wrapper.get('.sw-category-detail__tab-base');
        checkGeneralTab(generalTab);

        const productTab = wrapper.get('.sw-category-detail__tab-products');
        expect(productTab.isVisible()).toBe(false);

        const customEntityTab = wrapper.get('.sw-category-detail__tab-custom-entity');
        expect(customEntityTab.isVisible()).toBe(false);

        const cmsTab = wrapper.get('.sw-category-detail__tab-cms');
        expect(cmsTab.isVisible()).toBe(false);

        const seoTab = wrapper.get('.sw-category-detail__tab-seo');
        expect(seoTab.isVisible()).toBe(false);
    });

    it('should display the tabs for the `link` category type', async () => {
        const wrapper = await createWrapper('link');

        const generalTab = wrapper.get('.sw-category-detail__tab-base');
        checkGeneralTab(generalTab);

        const productTab = wrapper.get('.sw-category-detail__tab-products');
        expect(productTab.isVisible()).toBe(false);

        const customEntityTab = wrapper.get('.sw-category-detail__tab-custom-entity');
        expect(customEntityTab.isVisible()).toBe(false);

        const cmsTab = wrapper.get('.sw-category-detail__tab-cms');
        expect(cmsTab.isVisible()).toBe(false);

        const seoTab = wrapper.get('.sw-category-detail__tab-seo');
        expect(seoTab.isVisible()).toBe(false);
    });

    it('should display the tabs for the `custom_entity` category type', async () => {
        const wrapper = await createWrapper('custom_entity');

        const generalTab = wrapper.get('.sw-category-detail__tab-base');
        checkGeneralTab(generalTab);

        const productTab = wrapper.get('.sw-category-detail__tab-products');
        expect(productTab.isVisible()).toBe(false);

        const customEntityTab = wrapper.get('.sw-category-detail__tab-custom-entity');
        checkCustomEntityTab(customEntityTab);

        const cmsTab = wrapper.get('.sw-category-detail__tab-cms');
        checkCmsTab(cmsTab);

        const seoTab = wrapper.get('.sw-category-detail__tab-seo');
        checkSeoTab(seoTab);
    });
});
