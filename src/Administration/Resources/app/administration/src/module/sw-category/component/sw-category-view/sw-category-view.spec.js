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

    [
        {
            type: 'page',
            displayGeneralTab: true,
            displayProductTab: true,
            displayCustomEntityTab: false,
            displayCmsTab: true,
            displaySeoTab: true,
        },
        {
            type: 'folder',
            displayGeneralTab: true,
            displayProductTab: false,
            displayCustomEntityTab: false,
            displayCmsTab: false,
            displaySeoTab: false,
        },
        {
            type: 'custom_entity',
            displayGeneralTab: true,
            displayProductTab: false,
            displayCustomEntityTab: true,
            displayCmsTab: true,
            displaySeoTab: true,
        },
        {
            type: 'link',
            displayGeneralTab: true,
            displayProductTab: false,
            displayCustomEntityTab: false,
            displayCmsTab: false,
            displaySeoTab: false,
        },
    ].forEach(testcase => {
        const {
            type,
            displayGeneralTab,
            displayProductTab,
            displayCustomEntityTab,
            displayCmsTab,
            displaySeoTab
        } = testcase;
        it(`should display the tabs for the '${type}' category type`, async () => {
            const wrapper = await createWrapper(type);

            const generalTab = wrapper.find('.sw-category-detail__tab-base');
            expect(generalTab.isVisible()).toBe(displayGeneralTab);
            if (displayGeneralTab) {
                expect(generalTab.props()).toStrictEqual({
                    route: { name: 'sw.category.detail.base' },
                    title: 'sw-category.view.general',
                });
                expect(generalTab.text()).toBe('sw-category.view.general');
            }

            const productTab = wrapper.find('.sw-category-detail__tab-products');
            expect(productTab.isVisible()).toBe(displayProductTab);
            if (displayProductTab) {
                expect(productTab.props()).toStrictEqual({
                    route: { name: 'sw.category.detail.products' },
                    title: 'sw-category.view.products',
                });
                expect(productTab.text()).toBe('sw-category.view.products');
            }

            const customEntityTab = wrapper.find('.sw-category-detail__tab-custom-entity');
            expect(customEntityTab.isVisible()).toBe(displayCustomEntityTab);
            if (displayCustomEntityTab) {
                expect(customEntityTab.props()).toStrictEqual({
                    route: { name: 'sw.category.detail.customEntity' },
                    title: 'sw-category.view.customEntity',
                });
                expect(customEntityTab.text()).toBe('sw-category.view.customEntity');
            }

            const cmsTab = wrapper.find('.sw-category-detail__tab-cms');
            expect(cmsTab.isVisible()).toBe(displayCmsTab);
            if (displayCmsTab) {
                expect(cmsTab.props()).toStrictEqual({
                    route: { name: 'sw.category.detail.cms' },
                    title: 'sw-category.view.cms',
                });
                expect(cmsTab.text()).toBe('sw-category.view.cms');
            }

            const seoTab = wrapper.find('.sw-category-detail__tab-seo');
            expect(seoTab.isVisible()).toBe(displaySeoTab);
            if (displaySeoTab) {
                expect(seoTab.props()).toStrictEqual({
                    route: { name: 'sw.category.detail.seo' },
                    title: 'sw-category.view.seo',
                });
                expect(seoTab.text()).toBe('sw-category.view.seo');
            }
        });
    });
});
