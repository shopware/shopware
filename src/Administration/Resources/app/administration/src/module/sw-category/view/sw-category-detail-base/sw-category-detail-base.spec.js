/**
 * @package content
 */
import { shallowMount } from '@vue/test-utils';
import swCategoryDetailBase from 'src/module/sw-category/view/sw-category-detail-base';

Shopware.Component.register('sw-category-detail-base', swCategoryDetailBase);

describe('module/sw-category/view/sw-category-detail-base.spec', () => {
    let wrapper;

    const categoryMock = {
        media: [],
        name: 'Computer parts',
        footerSalesChannels: [],
        navigationSalesChannels: [],
        serviceSalesChannels: [],
        productAssignmentType: 'product',
        isNew: () => false,
    };

    beforeEach(async () => {
        if (Shopware.State.get('swCategoryDetail')) {
            Shopware.State.unregisterModule('swCategoryDetail');
        }

        Shopware.State.registerModule('swCategoryDetail', {
            namespaced: true,
            state: {
                category: categoryMock,
            },
        });

        wrapper = shallowMount(await Shopware.Component.build('sw-category-detail-base'), {
            stubs: {
                'sw-card': true,
                'sw-container': true,
                'sw-text-field': true,
                'sw-switch-field': true,
                'sw-single-select': true,
                'sw-entity-tag-select': true,
                'sw-category-detail-menu': true,
                'sw-category-detail-products': true,
                'sw-entity-single-select': true,
                'sw-category-seo-form': true,
                'sw-alert': {
                    template: '<div class="sw-alert"><slot></slot></div>',
                },
            },
            mocks: {
                placeholder: () => {},
            },
            propsData: {
                isLoading: false,
                manualAssignedProductsCount: 0,
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            get: () => Promise.resolve(null),
                        };
                    },
                },
            },
        });
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });
});
