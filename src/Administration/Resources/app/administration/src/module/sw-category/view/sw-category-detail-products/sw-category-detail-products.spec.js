/**
 * @package content
 */
import { shallowMount } from '@vue/test-utils';
import swCategoryDetailProducts from 'src/module/sw-category/view/sw-category-detail-products';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';

Shopware.Component.register('sw-category-detail-products', swCategoryDetailProducts);

describe('module/sw-category/view/sw-category-detail-products.spec', () => {
    let wrapper;

    const categoryMock = {
        media: [],
        name: 'Computer parts',
        footerSalesChannels: [],
        navigationSalesChannels: [],
        serviceSalesChannels: [],
        productAssignmentType: 'product',
        type: 'page',
        isNew: () => false,
    };

    const productStreamMock = {
        name: 'Very cheap pc parts',
        apiFilter: ['foo', 'bar'],
        invalid: false,
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

        wrapper = shallowMount(await Shopware.Component.build('sw-category-detail-products'), {
            stubs: {
                'sw-icon': true,
                'sw-card': true,
                'router-link': true,
                'sw-container': true,
                'sw-text-field': true,
                'sw-switch-field': true,
                'sw-single-select': true,
                'sw-many-to-many-assignment-card': true,
                'sw-empty-state': true,
                'sw-product-stream-grid-preview': {
                    template: '<div class="sw-product-stream-grid-preview"></div>',
                },
                'sw-entity-single-select': true,
                'sw-alert': {
                    template: '<div class="sw-alert"><slot></slot></div>',
                },
            },
            mocks: {
                placeholder: () => {
                },
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
                            search: jest.fn(() => Promise.resolve({})),
                        };
                    },
                },
            },
        });
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render stream select when changing the assignment type to stream', async () => {
        await wrapper.setData({
            category: {
                productAssignmentType: 'product_stream',
            },
        });

        await wrapper.vm.$nextTick();

        // Ensure default select is replaced with stream select inside `select` slot
        expect(wrapper.find('.sw-entity-many-to-many-select').exists()).toBeFalsy();
        expect(wrapper.find('.sw-category-detail-products__product-stream-select').exists()).toBeTruthy();
    });

    it('should render stream preview when changing the assignment type to product stream', async () => {
        await wrapper.setData({
            category: {
                productAssignmentType: 'product_stream',
            },
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
                productAssignmentType: 'product_stream',
            },
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
                productStreamId: '12345',
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.productStreamFilter).toEqual(['foo', 'bar']);
        expect(wrapper.vm.productStreamInvalid).toBe(false);
    });

    it('should return filters from filter registry', () => {
        expect(wrapper.vm.assetFilter).toEqual(expect.any(Function));
    });

    it('should display manufacturer name of assignment variant product', async () => {
        const parentProduct = {
            id: 'product_parent_1',
            parentId: null,
            manufacturerId: 'manufacturer_1',
            manufacturer: {
                id: 'manufacturer_1',
                name: 'Test manufacturer 1',
            },
        };

        await wrapper.setData({
            parentProducts: new EntityCollection(null, 'product', null, new Criteria(1, 25), [parentProduct]),
        });

        let product = {
            id: 'product_1',
            parentId: null,
            manufacturerId: 'manufacturer_2',
            manufacturer: {
                id: 'manufacturer_2',
                name: 'Test manufacturer 2',
            },
        };
        expect(wrapper.vm.getManufacturer(product)).toEqual(product.manufacturer);

        product = {
            id: 'product_1',
            parentId: 'product_parent_1',
        };
        expect(wrapper.vm.getManufacturer(product)).toEqual(parentProduct.manufacturer);
    });

    it('should get parent products on paginate manual product assignment', async () => {
        const manualProductAssignmentCard = wrapper.find('sw-many-to-many-assignment-card-stub');
        expect(manualProductAssignmentCard.exists()).toBe(true);

        const assignment = [
            {
                id: 'product_1',
                parentId: 'product_parent_1',
                manufacturerId: null,
                manufacturer: null,
            },
            {
                id: 'product_2',
                parentId: null,
                manufacturerId: 'manufacturer_1',
                manufacturer: {
                    id: 'manufacturer_1',
                    name: 'Test manufacturer 1',
                },
            },
        ];
        await manualProductAssignmentCard.vm.$emit('paginate', new EntityCollection(
            null,
            'product',
            null,
            new Criteria(1, 25),
            [assignment],
            2,
        ));

        expect(wrapper.vm.productRepository.search).toHaveBeenCalled();
        expect(wrapper.vm.manualAssignedProductsCount).toBe(2);
    });
});
