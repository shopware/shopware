import { shallowMount, createLocalVue } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-category/view/sw-category-detail-base';


describe('module/sw-category/view/sw-category-detail-base.spec', () => {
    let wrapper;
    let localVue;

    const categoryMock = {
        media: [],
        name: 'Computer parts',
        footerSalesChannels: [],
        navigationSalesChannels: [],
        serviceSalesChannels: [],
        productAssignmentType: 'product',
        isNew: () => false
    };

    beforeEach(() => {
        localVue = createLocalVue();
        localVue.use(Vuex);

        Shopware.State.registerModule('swCategoryDetail', {
            namespaced: true,
            state: {
                category: categoryMock
            }
        });

        wrapper = shallowMount(Shopware.Component.build('sw-category-detail-base'), {
            localVue,
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
                    template: '<div class="sw-alert"><slot></slot></div>'
                }
            },
            mocks: {
                $tc: key => key,
                $store: Shopware.State._store,
                placeholder: () => {}
            },
            propsData: {
                isLoading: false,
                manualAssignedProductsCount: 0
            },
            provide: {
                acl: {
                    can: () => true
                },
                repositoryFactory: {
                    create: () => {
                        return {
                            get: () => Promise.resolve(null)
                        };
                    }
                },
                feature: {
                    isActive: () => true
                }
            }
        });
    });

    afterEach(() => {
        wrapper.destroy();
        localVue = null;
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });
});
