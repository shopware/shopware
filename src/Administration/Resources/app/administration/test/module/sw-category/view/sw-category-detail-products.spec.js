import { shallowMount, createLocalVue } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-category/view/sw-category-detail-products';


describe('module/sw-category/view/sw-category-detail-products.spec', () => {
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

    const productStreamMock = {
        name: 'Very cheap pc parts',
        apiFilter: ['foo', 'bar'],
        invalid: false
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

        wrapper = shallowMount(Shopware.Component.build('sw-category-detail-products'), {
            localVue,
            stubs: {
                'sw-icon': true,
                'sw-card': true,
                'router-link': true,
                'sw-container': true,
                'sw-text-field': true,
                'sw-switch-field': true,
                'sw-single-select': true,
                'sw-many-to-many-assignment-card': {
                    template: `<div>
                                   <slot name="prepend-select"></slot>
                                   <slot name="select"><div class="sw-entity-many-to-many-select"></div></slot>
                                   <slot name="data-grid"><div class="sw-many-to-many-assignment-card__grid"></div></slot>
                               </div>`
                },
                'sw-product-stream-grid-preview': {
                    template: '<div class="sw-product-stream-grid-preview"></div>'
                },
                'sw-entity-single-select': true,
                'sw-alert': {
                    template: '<div class="sw-alert"><slot></slot></div>'
                }
            },
            mocks: {
                placeholder: () => {}
            },
            propsData: {
                isLoading: false,
                manualAssignedProductsCount: 0
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            get: () => Promise.resolve(productStreamMock)
                        };
                    }
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

    it('should render stream select when changing the assignment type to stream', async () => {
        await wrapper.setData({
            category: {
                productAssignmentType: 'product_stream'
            }
        });

        await wrapper.vm.$nextTick();

        // Ensure default select is replaced with stream select inside `select` slot
        expect(wrapper.find('.sw-entity-many-to-many-select').exists()).toBeFalsy();
        expect(wrapper.find('.sw-category-detail-products__product-stream-select').exists()).toBeTruthy();
    });

    it('should render stream preview when changing the assignment type to product stream', async () => {
        await wrapper.setData({
            category: {
                productAssignmentType: 'product_stream'
            }
        });

        await wrapper.vm.$nextTick();

        // Ensure that the default grid is replaced with product stream preview grid inside `data-grid` slot
        expect(wrapper.find('.sw-many-to-many-assignment-card__grid').exists()).toBeFalsy();
        expect(wrapper.find('.sw-product-stream-grid-preview').exists()).toBeTruthy();
    });

    it('should show message when assigment type is product stream and products are manually assigned', async () => {
        await wrapper.setData({
            manualAssignedProductsCount: 5,
            category: {
                productAssignmentType: 'product_stream'
            }
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-alert').text())
            .toBe('sw-category.base.products.alertManualAssignedProductsOnAssignmentTypeStream');
    });

    it('should have correct default assignment types', async () => {
        const assignmentTypes = wrapper.vm.productAssignmentTypes;

        expect(assignmentTypes[0].value).toBe('product');
        expect(assignmentTypes[1].value).toBe('product_stream');
    });

    it('should try to load product stream preview when stream id is present', async () => {
        await wrapper.setData({
            manualAssignedProductsCount: 5,
            category: {
                productStreamId: '12345'
            }
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.productStreamFilter).toEqual(['foo', 'bar']);
        expect(wrapper.vm.productStreamInvalid).toBe(false);
    });
});
