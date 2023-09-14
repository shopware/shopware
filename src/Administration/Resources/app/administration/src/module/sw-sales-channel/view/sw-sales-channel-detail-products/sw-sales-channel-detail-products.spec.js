/**
 * @package sales-channel
 */

/* eslint-disable prefer-promise-reject-errors */
import { shallowMount } from '@vue/test-utils';
import swSalesChannelDetailProducts from 'src/module/sw-sales-channel/view/sw-sales-channel-detail-products';
import 'src/app/component/base/sw-card';

Shopware.Component.register('sw-sales-channel-detail-products', swSalesChannelDetailProducts);

function mockCriteria() {
    return {
        limit: 25,
        page: 1,
        sortings: [{ field: 'name', naturalSorting: false, order: 'ASC' }],
        resetSorting() {
            this.sortings = [];
        },
        addSorting(sorting) {
            this.sortings.push(sorting);
        },
    };
}

async function createWrapper(privileges = []) {
    return shallowMount(await Shopware.Component.build('sw-sales-channel-detail-products'), {
        stubs: {
            'sw-card': await Shopware.Component.build('sw-card'),
            'sw-container': {
                template: `
                    <div class="sw-container">
                        <slot></slot>
                    </div>
                `,
            },
            'sw-card-section': {
                template: `
                    <div class="sw-card-section">
                        <slot></slot>
                    </div>
                `,
            },
            'sw-entity-listing': {
                props: ['items'],
                template: `
                    <div class="sw-entity-listing">
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>
                `,
            },
            'sw-empty-state': {
                template: `
                    <div class="sw-empty-state">
                        <slot></slot>
                        <slot name="actions"></slot>
                    </div>
                `,
            },
            'sw-pagination': true,
            'sw-simple-search-field': true,
            'sw-button': true,
            'sw-icon': true,
            'sw-sales-channel-products-assignment-modal': true,
            'sw-context-menu-item': true,
            'sw-extension-component-section': true,
            'sw-loader': true,
            'sw-ignore-class': true,
        },
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        create: () => {
                            return Promise.resolve();
                        },
                        search: () => {
                            return Promise.resolve();
                        },
                        delete: () => {
                            return Promise.resolve();
                        },
                        syncDeleted: () => {
                            return Promise.resolve();
                        },
                        saveAll: () => {
                            return Promise.resolve();
                        },
                    };
                },
            },
            feature: {
                isActive: () => true,
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                },
            },
        },
        propsData: {
            salesChannel: {
                id: 'storefrontSalesChannelTypeId',
            },
        },
    });
}

describe('src/module/sw-sales-channel/view/sw-sales-channel-detail-products', () => {
    const productsMock = [
        { id: '101', active: true, productNumber: '001' },
        { id: '102', active: false, productNumber: '002' },
    ];
    const variantProductMocks = [
        { id: '201', active: true, productNumber: '001.1', parentId: '101', visibilities: [{ id: '1', productId: '101', salesChannelId: 'storefrontSalesChannelTypeId' }] },
        { id: '202', active: true, productNumber: '001.2', parentId: '101', visibilities: [{ id: '2', productId: '202', salesChannelId: 'storefrontSalesChannelTypeId' }] },
    ];
    productsMock.has = (id) => {
        return productsMock.some((item) => {
            return item.id === id;
        });
    };

    const productMock = { visibilities: [
        { id: '01', productId: '101', salesChannelId: 'apiSalesChannelTypeId' },
        { id: '02', productId: '101', salesChannelId: 'storefrontSalesChannelTypeId' },
    ] };

    const $refsMock = { entityListing: {
        selection: {
            101: productMock,
        },
    } };

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should get products successful', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.productRepository.search = jest.fn(() => Promise.resolve(productsMock));

        await wrapper.setProps({ salesChannel: { id: 'apiSalesChannelTypeId' } });

        expect(wrapper.vm.products).toEqual(productsMock);
        wrapper.vm.productRepository.search.mockRestore();
    });

    it('should get products failed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.productRepository.search = jest.fn(() => Promise.reject());

        await wrapper.setProps({ salesChannel: { id: 'storefrontSalesChannelTypeId' } });

        expect(wrapper.vm.products).toEqual([]);
        wrapper.vm.productRepository.search.mockRestore();
    });

    it('should delete product successful', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        await wrapper.setData({ $refs: $refsMock });

        wrapper.vm.productVisibilityRepository.delete = jest.fn(() => Promise.resolve());
        wrapper.vm.getProducts = jest.fn(() => Promise.resolve());

        await wrapper.setProps({ salesChannel: { id: 'apiSalesChannelTypeId' } });
        await wrapper.vm.onDeleteProduct(productMock);

        expect(wrapper.vm.getDeleteId(productMock)).toBe('01');
        expect(wrapper.vm.getProducts).toHaveBeenCalled();

        wrapper.vm.productVisibilityRepository.delete.mockRestore();
        wrapper.vm.getProducts.mockRestore();
    });

    it('should delete product failed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.productVisibilityRepository.delete = jest.fn(() => Promise.reject({ message: 'Error' }));
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.setData({ $refs: $refsMock });
        await wrapper.setProps({ salesChannel: { id: 'apiSalesChannelTypeId' } });
        await wrapper.vm.onDeleteProduct(productMock);

        expect(wrapper.vm.getDeleteId(productMock)).toBe('01');
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(1);

        wrapper.vm.productVisibilityRepository.delete.mockRestore();
        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should delete products successful', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        await wrapper.setData({ $refs: $refsMock });

        wrapper.vm.productVisibilityRepository.syncDeleted = jest.fn(() => Promise.resolve());
        wrapper.vm.getProducts = jest.fn(() => Promise.resolve());

        await wrapper.setProps({ salesChannel: { id: 'apiSalesChannelTypeId' } });
        await wrapper.vm.onDeleteProducts();

        expect(wrapper.vm.getProducts).toHaveBeenCalled();

        wrapper.vm.productVisibilityRepository.syncDeleted.mockRestore();
        wrapper.vm.getProducts.mockRestore();
    });

    it('should delete products failed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.productVisibilityRepository.syncDeleted = jest.fn(() => Promise.reject({ message: 'Error' }));
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.setData({ $refs: $refsMock });
        await wrapper.setProps({ salesChannel: { id: 'apiSalesChannelTypeId' } });
        await wrapper.vm.onDeleteProducts();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(1);

        wrapper.vm.productVisibilityRepository.syncDeleted.mockRestore();
        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should get delete id correctly', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setProps({ salesChannel: { id: 'apiSalesChannelTypeId' } });

        const deleteId = wrapper.vm.getDeleteId(productMock);

        expect(deleteId).toBe('01');
    });

    it('should get products when changing search term', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.getProducts = jest.fn();

        await wrapper.setData({
            page: 2,
        });

        expect(wrapper.vm.page).toBe(2);

        await wrapper.vm.onChangeSearchTerm('Awesome Product');

        expect(wrapper.vm.searchTerm).toBe('Awesome Product');
        expect(wrapper.vm.productCriteria.term).toBe('Awesome Product');
        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.getProducts).toHaveBeenCalledTimes(1);
        wrapper.vm.getProducts.mockRestore();
    });

    it('should get products when changing page', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.getProducts = jest.fn();
        expect(wrapper.vm.productCriteria.sortings).toEqual([]);
        wrapper.vm.products.criteria = mockCriteria();

        await wrapper.vm.onChangePage({ page: 2, limit: 25 });

        expect(wrapper.vm.page).toBe(2);
        expect(wrapper.vm.limit).toBe(25);
        expect(wrapper.vm.productCriteria.sortings).toEqual([
            { field: 'name', naturalSorting: false, order: 'ASC' },
        ]);
        expect(wrapper.vm.getProducts).toHaveBeenCalledTimes(1);
        wrapper.vm.getProducts.mockRestore();
    });

    it('should be able to add products in empty state', async () => {
        const wrapper = await createWrapper([
            'sales_channel.editor',
        ]);
        await flushPromises();

        await wrapper.setData({ products: [], searchTerm: null });

        const createButton = wrapper.find('sw-button-stub');
        expect(createButton.attributes().disabled).toBeUndefined();
    });

    it('should not be able to add products in empty state', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ products: [], searchTerm: null });

        const createButton = wrapper.find('sw-button-stub');
        expect(createButton.attributes().disabled).toBe('true');
    });

    it('should be able to add products in filled state', async () => {
        const wrapper = await createWrapper([
            'sales_channel.editor',
        ]);
        await flushPromises();

        await wrapper.setData({ products: productsMock, searchTerm: 'Awesome Product' });

        const createButton = wrapper.find('sw-button-stub');
        expect(createButton.attributes().disabled).toBeUndefined();
    });

    it('should not be able to add products in filled state', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ products: productsMock, searchTerm: 'Awesome Product' });

        const createButton = wrapper.find('sw-button-stub');
        expect(createButton.attributes().disabled).toBe('true');
    });

    it('should be able to delete product', async () => {
        const wrapper = await createWrapper([
            'sales_channel.deleter',
        ]);
        await flushPromises();

        await wrapper.setData({ products: productsMock });

        const entityListing = wrapper.find('.sw-sales-channel-detail-products__list');
        expect(entityListing.attributes()['allow-delete']).toBe('true');
    });

    it('should not be able to delete product', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ products: productsMock });

        const entityListing = wrapper.find('.sw-sales-channel-detail-products__list');
        expect(entityListing.attributes()['allow-delete']).toBeUndefined();
    });

    it('should be able to edit product', async () => {
        const wrapper = await createWrapper([
            'product.editor',
        ]);
        await flushPromises();

        await wrapper.setData({ products: productsMock });

        const entityListing = wrapper.find('.sw-sales-channel-detail-products__list');
        expect(entityListing.attributes()['allow-edit']).toBe('true');
    });

    it('should not be able to edit product', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ products: productsMock });

        const entityListing = wrapper.find('.sw-sales-channel-detail-products__list');
        expect(entityListing.attributes()['allow-edit']).toBeUndefined();
    });

    it('should turn on add products modal', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.openAddProductsModal();

        const modal = wrapper.find('sw-sales-channel-products-assignment-modal-stub');

        expect(wrapper.vm.showProductsModal).toBe(true);
        expect(modal.exists()).toBeTruthy();
    });

    it('should add products successful', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.saveProductVisibilities = jest.fn(() => Promise.resolve());

        await wrapper.setData({ products: productsMock });
        await wrapper.vm.onAddProducts([
            { id: '103', active: true, productNumber: '003' },
        ]);

        expect(wrapper.vm.saveProductVisibilities).toHaveBeenCalledWith(
            expect.arrayContaining([
                expect.objectContaining({ productId: '103' }),
            ]),
        );

        wrapper.vm.saveProductVisibilities.mockRestore();
    });

    it('should add products failed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.saveProductVisibilities = jest.fn(() => Promise.resolve());

        await expect(wrapper.vm.onAddProducts([])).rejects.toEqual();

        expect(wrapper.vm.showProductsModal).toBe(false);
        expect(wrapper.vm.saveProductVisibilities).not.toHaveBeenCalled();

        wrapper.vm.saveProductVisibilities.mockRestore();
    });

    it('should save product visibilities successful', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.productVisibilityRepository.saveAll = jest.fn(() => Promise.resolve());

        await wrapper.vm.saveProductVisibilities([]);

        expect(wrapper.vm.productVisibilityRepository.saveAll).not.toHaveBeenCalled();

        wrapper.vm.productVisibilityRepository.saveAll.mockRestore();
    });

    it('should save product visibilities failed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        wrapper.vm.productVisibilityRepository.saveAll = jest.fn(() => {
            return Promise.reject(new Error('Whoops!'));
        });

        const getError = async () => {
            try {
                await wrapper.vm.saveProductVisibilities([
                    {
                        visibility: 30,
                        productId: 'productId',
                        salesChannelId: 'salesChannelId',
                        salesChannel: {},
                        _isNew: true,
                    },
                ]);

                throw new Error('Method should have thrown an error');
            } catch (error) {
                return error;
            }
        };

        expect((await getError()).message).toBe('Whoops!');

        wrapper.vm.productVisibilityRepository.saveAll.mockRestore();
    });

    it('should not be able to delete variants which have inherit visibility', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ products: [...productsMock, ...variantProductMocks] });

        expect(wrapper.vm.isProductRemovable(variantProductMocks[0])).toBe(false);
        expect(wrapper.vm.isProductRemovable(variantProductMocks[1])).toBe(true);
    });

    it('should render loading state when loading product entities', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('sw-loader-stub').exists()).toBe(true);
        expect(wrapper.find('.sw-empty-state').exists()).toBe(false);
    });

    it('should render empty state when products are loaded and empty', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('sw-loader-stub').exists()).toBe(false);
        expect(wrapper.find('.sw-empty-state').exists()).toBe(true);
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.assetFilter).toEqual(expect.any(Function));
    });
});
