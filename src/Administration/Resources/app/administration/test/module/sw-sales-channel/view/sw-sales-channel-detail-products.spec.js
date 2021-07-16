/* eslint-disable prefer-promise-reject-errors */
import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/view/sw-sales-channel-detail-products';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-sales-channel-detail-products'), {
        stubs: {
            'sw-card': {
                template: `
                    <div class="sw-card">
                        <slot></slot>
                        <slot name="grid"></slot>
                    </div>
                `
            },
            'sw-container': {
                template: `
                    <div class="sw-container">
                        <slot></slot>
                    </div>
                `
            },
            'sw-card-section': {
                template: `
                    <div class="sw-card-section">
                        <slot></slot>
                    </div>
                `
            },
            'sw-entity-listing': {
                props: ['items'],
                template: `
                    <div class="sw-entity-listing">
                        <template v-for="item in items">
                            <slot name="actions" v-bind="{ item }"></slot>
                        </template>
                    </div>
                `
            },
            'sw-empty-state': {
                template: `
                    <div class="sw-empty-state">
                        <slot></slot>
                        <slot name="actions"></slot>
                    </div>
                `
            },
            'sw-pagination': true,
            'sw-simple-search-field': true,
            'sw-button': true,
            'sw-icon': true,
            'sw-sales-channel-products-assignment-modal': true
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
                        }
                    };
                }
            },
            feature: {
                isActive: () => true
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }
        },
        propsData: {
            salesChannel: {}
        }
    });
}

describe('src/module/sw-sales-channel/view/sw-sales-channel-detail-products', () => {
    const productsMock = [
        { id: '101', active: true, productNumber: '001' },
        { id: '102', active: false, productNumber: '002' }
    ];
    productsMock.has = (id) => {
        return productsMock.some((item) => {
            return item.id === id;
        });
    };

    const productMock = { visibilities: [
        { id: '01', productId: '101', salesChannelId: 'apiSalesChannelTypeId' },
        { id: '02', productId: '101', salesChannelId: 'storefrontSalesChannelTypeId' }
    ] };

    const $refsMock = { entityListing: {
        deleteId: null,
        closeModal: () => false,
        selection: {
            101: productMock
        }
    } };

    it('should be a Vue.js component', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should get products successful', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.productRepository.search = jest.fn(() => Promise.resolve(productsMock));

        await wrapper.setProps({ salesChannel: { id: 'apiSalesChannelTypeId' } });

        expect(wrapper.vm.products).toEqual(productsMock);
        wrapper.vm.productRepository.search.mockRestore();
    });

    it('should get products failed', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.productRepository.search = jest.fn(() => Promise.reject());

        await wrapper.setProps({ salesChannel: { id: 'storefrontSalesChannelTypeId' } });

        expect(wrapper.vm.products).toEqual([]);
        wrapper.vm.productRepository.search.mockRestore();
    });

    it('should delete product successful', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
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
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
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
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
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
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
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
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setProps({ salesChannel: { id: 'apiSalesChannelTypeId' } });

        const deleteId = wrapper.vm.getDeleteId(productMock);

        expect(deleteId).toBe('01');
    });

    it('should get products when changing search term', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.getProducts = jest.fn();

        await wrapper.setData({
            page: 2
        });

        expect(wrapper.vm.page).toEqual(2);

        await wrapper.vm.onChangeSearchTerm('Awesome Product');

        expect(wrapper.vm.searchTerm).toBe('Awesome Product');
        expect(wrapper.vm.productCriteria.term).toBe('Awesome Product');
        expect(wrapper.vm.page).toEqual(1);
        expect(wrapper.vm.getProducts).toHaveBeenCalledTimes(1);
        wrapper.vm.getProducts.mockRestore();
    });

    it('should get products when changing page', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.getProducts = jest.fn();

        await wrapper.vm.onChangePage({ page: 2, limit: 25 });

        expect(wrapper.vm.page).toBe(2);
        expect(wrapper.vm.limit).toBe(25);
        expect(wrapper.vm.getProducts).toHaveBeenCalledTimes(1);
        wrapper.vm.getProducts.mockRestore();
    });

    it('should be able to add products in empty state', async () => {
        const wrapper = createWrapper([
            'sales_channel.editor'
        ]);
        await wrapper.vm.$nextTick();

        await wrapper.setData({ products: [], searchTerm: null });

        const createButton = wrapper.find('sw-button-stub');
        expect(createButton.attributes().disabled).toBe(undefined);
    });

    it('should not be able to add products in empty state', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({ products: [], searchTerm: null });

        const createButton = wrapper.find('sw-button-stub');
        expect(createButton.attributes().disabled).toBe('true');
    });

    it('should be able to add products in filled state', async () => {
        const wrapper = createWrapper([
            'sales_channel.editor'
        ]);
        await wrapper.vm.$nextTick();

        await wrapper.setData({ products: productsMock, searchTerm: 'Awesome Product' });

        const createButton = wrapper.find('sw-button-stub');
        expect(createButton.attributes().disabled).toBe(undefined);
    });

    it('should not be able to add products in filled state', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({ products: productsMock, searchTerm: 'Awesome Product' });

        const createButton = wrapper.find('sw-button-stub');
        expect(createButton.attributes().disabled).toBe('true');
    });

    it('should be able to delete product', async () => {
        const wrapper = createWrapper([
            'sales_channel.deleter'
        ]);
        await wrapper.vm.$nextTick();

        await wrapper.setData({ products: productsMock });

        const entityListing = wrapper.find('.sw-sales-channel-detail-products__list');
        expect(entityListing.attributes()['allow-delete']).toBe('true');
    });

    it('should not be able to delete product', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({ products: productsMock });

        const entityListing = wrapper.find('.sw-sales-channel-detail-products__list');
        expect(entityListing.attributes()['allow-delete']).toBe(undefined);
    });

    it('should be able to edit product', async () => {
        const wrapper = createWrapper([
            'product.editor'
        ]);
        await wrapper.vm.$nextTick();

        await wrapper.setData({ products: productsMock });

        const entityListing = wrapper.find('.sw-sales-channel-detail-products__list');
        expect(entityListing.attributes()['allow-edit']).toBe('true');
    });

    it('should not be able to edit product', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.setData({ products: productsMock });

        const entityListing = wrapper.find('.sw-sales-channel-detail-products__list');
        expect(entityListing.attributes()['allow-edit']).toBe(undefined);
    });

    it('should turn on add products modal', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        await wrapper.vm.openAddProductsModal();

        const modal = wrapper.find('sw-sales-channel-products-assignment-modal-stub');

        expect(wrapper.vm.showProductsModal).toBe(true);
        expect(modal.exists()).toBeTruthy();
    });

    it('should add products successful', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.saveProductVisibilities = jest.fn(() => Promise.resolve());

        await wrapper.setData({ products: productsMock });
        await wrapper.vm.onAddProducts([
            { id: '103', active: true, productNumber: '003' }
        ]);

        expect(wrapper.vm.saveProductVisibilities).toHaveBeenCalledWith(
            expect.arrayContaining([
                expect.objectContaining({ productId: '103' })
            ])
        );

        wrapper.vm.saveProductVisibilities.mockRestore();
    });

    it('should add products failed', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.saveProductVisibilities = jest.fn(() => Promise.resolve());

        wrapper.vm.onAddProducts([]);

        expect(wrapper.vm.showProductsModal).toBe(false);
        expect(wrapper.vm.saveProductVisibilities).not.toBeCalled();

        wrapper.vm.saveProductVisibilities.mockRestore();
    });

    it('should save product visibilities successful', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.productVisibilityRepository.saveAll = jest.fn(() => Promise.resolve());

        await wrapper.vm.saveProductVisibilities([]);

        expect(wrapper.vm.productVisibilityRepository.saveAll).not.toBeCalled();

        wrapper.vm.productVisibilityRepository.saveAll.mockRestore();
    });

    it('should save product visibilities failed', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        wrapper.vm.productVisibilityRepository.saveAll = jest.fn(() => {
            return Promise.reject(new Error('Whoops!'));
        });

        await wrapper.vm.saveProductVisibilities([
            {
                visibility: 30,
                productId: 'productId',
                salesChannelId: 'salesChannelId',
                salesChannel: {},
                _isNew: true
            }
        ]).catch((error) => {
            expect(error.message).toBe('Whoops!');
        });

        wrapper.vm.productVisibilityRepository.saveAll.mockRestore();
    });
});
