/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

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

const productsMock = [
    {
        id: '101',
        active: true,
        name: 'product-101',
        productNumber: '001',
        visibilities: [
            {
                id: '1',
                productId: '101',
                salesChannelId: 'storefrontSalesChannelTypeId',
            },
        ],
    },
    {
        id: '102',
        active: false,
        name: 'product-102',
        productNumber: '002',
        visibilities: [
            {
                id: '2',
                productId: '202',
                salesChannelId: 'storefrontSalesChannelTypeId',
            },
        ],
    },
];
const variantProductMocks = [
    {
        id: '201',
        active: true,
        name: 'product-101.1',
        productNumber: '001.1',
        parentId: '101',
        visibilities: [
            {
                id: '1',
                productId: '101',
                salesChannelId: 'storefrontSalesChannelTypeId',
            },
        ],
    },
    {
        id: '202',
        active: true,
        name: 'product-101.2',
        productNumber: '001.2',
        parentId: '101',
        visibilities: [
            {
                id: '2',
                productId: '202',
                salesChannelId: 'storefrontSalesChannelTypeId',
            },
        ],
    },
];
productsMock.has = (id) => {
    return productsMock.some((item) => {
        return item.id === id;
    });
};

const productMock = {
    visibilities: [
        { id: '01', productId: '101', salesChannelId: 'apiSalesChannelTypeId' },
        {
            id: '02',
            productId: '101',
            salesChannelId: 'storefrontSalesChannelTypeId',
        },
    ],
};

async function createWrapper({ salesChannel, products } = {}) {
    return mount(
        await wrapTestComponent('sw-sales-channel-detail-products', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-card': {
                        template: '<div class="sw-card"><slot></slot><slot name="grid"></slot></div>',
                    },
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
                        props: [
                            'items',
                            'allowEdit',
                            'allowDelete',
                        ],
                        template: `
                        <div class="sw-entity-listing">
                            <template v-for="item in items">
                                <slot name="actions" v-bind="{ item }"></slot>
                            </template>
                        </div>
                    `,
                        data() {
                            return {
                                selection: {},
                            };
                        },
                        methods: {
                            resetSelection() {
                                this.selection = {};
                            },
                        },
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
                    'sw-button': {
                        template: '<button class="sw-button"><slot></slot></button>',
                        props: ['disabled'],
                    },
                    'sw-icon': true,
                    'sw-sales-channel-products-assignment-modal': true,
                    'sw-context-menu-item': true,
                    'sw-extension-component-section': true,
                    'sw-ignore-class': true,
                    'sw-checkbox-field': true,
                    'router-link': true,
                    'sw-product-variant-info': true,
                },
                provide: {
                    repositoryFactory: {
                        create: (entity) => {
                            return {
                                create: async () => {},
                                search: async () => {
                                    if (entity === 'product') {
                                        const entityCollection = products ?? [];
                                        entityCollection.criteria = mockCriteria();
                                        entityCollection.total = products.length;

                                        return entityCollection;
                                    }

                                    return [];
                                },
                                delete: async () => {},
                                syncDeleted: async () => {},
                                saveAll: async () => {},
                            };
                        },
                    },
                },
            },
            props: {
                salesChannel: salesChannel ?? {
                    id: 'storefrontSalesChannelTypeId',
                },
            },
        },
    );
}

describe('src/module/sw-sales-channel/view/sw-sales-channel-detail-products', () => {
    beforeEach(() => {
        global.activeAclRoles = [];
    });

    it('should get products successful', async () => {
        const wrapper = await createWrapper({
            salesChannel: { id: 'apiSalesChannelTypeId' },
            products: productsMock,
        });
        await flushPromises();

        expect(wrapper.getComponent('.sw-entity-listing').props('items')).toEqual(productsMock);
    });

    it('should delete product successful', async () => {
        const wrapper = await createWrapper({
            salesChannel: { id: 'apiSalesChannelTypeId' },
            products: productsMock,
        });
        await wrapper.getComponent('.sw-entity-listing').vm.$emit('selection-change', {
            101: productMock,
        });
        await flushPromises();

        wrapper.vm.productVisibilityRepository.delete = jest.fn(() => Promise.resolve());
        wrapper.vm.getProducts = jest.fn(() => Promise.resolve());

        await wrapper.vm.onDeleteProduct(productMock);

        expect(wrapper.vm.getDeleteId(productMock)).toBe('01');
        expect(wrapper.vm.getProducts).toHaveBeenCalled();

        wrapper.vm.productVisibilityRepository.delete.mockRestore();
        wrapper.vm.getProducts.mockRestore();
    });

    it('should delete product failed', async () => {
        const wrapper = await createWrapper({
            salesChannel: { id: 'apiSalesChannelTypeId' },
            products: productsMock,
        });

        await wrapper.setData({
            searchTerm: 'Awesome Product',
        });

        wrapper.vm.productVisibilityRepository.delete = jest.fn(() => Promise.reject(new Error('Error')));
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.onDeleteProduct(productMock);

        expect(wrapper.vm.getDeleteId(productMock)).toBe('01');
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledTimes(1);

        wrapper.vm.productVisibilityRepository.delete.mockRestore();
        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should get delete id correctly', async () => {
        const wrapper = await createWrapper({
            salesChannel: { id: 'apiSalesChannelTypeId' },
        });

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
        global.activeAclRoles = ['sales_channel.editor'];
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ products: [], searchTerm: null });

        const createButton = wrapper.getComponent('.sw-button');
        expect(createButton.props('disabled')).toBe(false);
    });

    it('should not be able to add products in empty state', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ products: [], searchTerm: null });

        const createButton = wrapper.getComponent('.sw-button');
        expect(createButton.props('disabled')).toBe(true);
    });

    it('should be able to add products in filled state', async () => {
        global.activeAclRoles = ['sales_channel.editor'];
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            products: productsMock,
            searchTerm: 'Awesome Product',
        });

        const createButton = wrapper.getComponent('.sw-button');
        expect(createButton.props('disabled')).toBe(false);
    });

    it('should not be able to add products in filled state', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            products: productsMock,
            searchTerm: 'Awesome Product',
        });

        const createButton = wrapper.getComponent('.sw-button');
        expect(createButton.props('disabled')).toBe(true);
    });

    it('should be able to delete product', async () => {
        global.activeAclRoles = ['sales_channel.deleter'];
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ products: productsMock });

        const entityListing = wrapper.getComponent('.sw-entity-listing');
        expect(entityListing.props('allowDelete')).toBe(true);
    });

    it('should not be able to delete product', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ products: productsMock });

        const entityListing = wrapper.getComponent('.sw-entity-listing');
        expect(entityListing.props('allowDelete')).toBe(false);
    });

    it('should be able to edit product', async () => {
        global.activeAclRoles = ['product.editor'];
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ products: productsMock });

        const entityListing = wrapper.getComponent('.sw-entity-listing');
        expect(entityListing.props('allowEdit')).toBe(true);
    });

    it('should not be able to edit product', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({ products: productsMock });

        const entityListing = wrapper.getComponent('.sw-entity-listing');
        expect(entityListing.props('allowEdit')).toBe(false);
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
        const wrapper = await createWrapper({
            products: [
                ...productsMock,
                ...variantProductMocks,
            ],
        });
        await flushPromises();

        expect(wrapper.vm.isProductRemovable(variantProductMocks[0])).toBe(false);
        expect(wrapper.vm.isProductRemovable(variantProductMocks[1])).toBe(true);
    });

    it('should render loading state when loading product entities', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.getComponent('.sw-card').attributes('is-loading')).toBe('true');
        expect(wrapper.find('.sw-empty-state').exists()).toBe(false);
    });

    it('should render empty state when products are loaded and empty', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.getComponent('.sw-card').attributes('is-loading')).toBeUndefined();
        expect(wrapper.find('.sw-empty-state').exists()).toBe(true);
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.assetFilter).toEqual(expect.any(Function));
    });
});
