/**
 * @package content
 */
import { mount } from '@vue/test-utils';

const categoryMock = {
    media: [],
    name: 'Computer parts',
    footerSalesChannels: [],
    navigationSalesChannels: [],
    serviceSalesChannels: [],
    productAssignmentType: 'product',
    isNew: () => false,
};

const productStreamMock = {
    name: 'Very cheap pc parts',
    apiFilter: ['foo', 'bar'],
    invalid: false,
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-category-detail-products', { sync: true }), {
        global: {
            stubs: {
                'sw-icon': true,
                'sw-card': true,
                'router-link': true,
                'sw-container': true,
                'sw-text-field': true,
                'sw-switch-field': true,
                'sw-single-select': true,
                'sw-many-to-many-assignment-card': {
                    template: `
                        <div>
                            <slot name="prepend-select"></slot>
                            <slot name="select"></slot>
                            <slot name="data-grid"></slot>
                        </div>`,
                },
                'sw-product-stream-grid-preview': {
                    template: '<div class="sw-product-stream-grid-preview"></div>',
                },
                'sw-entity-single-select': {
                    template: '<div class="sw-entity-single-select"></div>',
                },
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
                            get: () => Promise.resolve(productStreamMock),
                        };
                    },
                },
            },
        },
    });
}

describe('module/sw-category/view/sw-category-detail-products.spec', () => {
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
    });

    it('should render stream select when changing the assignment type to stream', async () => {
        const wrapper = await createWrapper();

        await wrapper.getComponent('.sw-category-detail-products__product-assignment-type-select').vm.$emit('update:value', 'product_stream');

        // Ensure default select is replaced with stream select inside `select` slot
        expect(wrapper.find('.sw-entity-many-to-many-select').exists()).toBeFalsy();
        expect(wrapper.find('.sw-category-detail-products__product-stream-select').exists()).toBe(true);
    });

    it('should render stream preview when changing the assignment type to product stream', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            category: {
                productAssignmentType: 'product_stream',
            },
        });

        // Ensure that the default grid is replaced with product stream preview grid inside `data-grid` slot
        expect(wrapper.find('.sw-many-to-many-assignment-card__grid').exists()).toBeFalsy();
        expect(wrapper.find('.sw-product-stream-grid-preview').exists()).toBeTruthy();
    });

    it('should show message when assignment type is product stream and products are manually assigned', async () => {
        const wrapper = await createWrapper();

        await wrapper.getComponent('.sw-category-detail-products__product-assignment-type-select').vm.$emit('update:value', 'product_stream');
        await wrapper.setData({
            manualAssignedProductsCount: 5,
        });

        expect(wrapper.find('.sw-alert').text())
            .toBe('sw-category.base.products.alertManualAssignedProductsOnAssignmentTypeStream');
    });

    it('should have correct default assignment types', async () => {
        const wrapper = await createWrapper();

        const assignmentTypes = wrapper.vm.productAssignmentTypes;

        expect(assignmentTypes[0].value).toBe('product');
        expect(assignmentTypes[1].value).toBe('product_stream');
    });

    it('should try to load product stream preview when stream id is present', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            manualAssignedProductsCount: 5,
        });

        await wrapper.getComponent('.sw-category-detail-products__product-stream-select').vm.$emit('update:value', 'some_product_stream_id');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.productStreamFilter).toEqual(['foo', 'bar']);
        expect(wrapper.vm.productStreamInvalid).toBe(false);
    });
});
