/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';

async function getError(method, ...args) {
    try {
        await method(...args);

        throw new Error('Method should have thrown an error');
    } catch (error) {
        return error;
    }
}

const productStreamsMock = [
    {
        id: 1,
        name: 'Low prices',
    },
    {
        id: 2,
        name: 'Standard prices',
    },
    {
        id: 3,
        name: 'High prices',
    },
];
productStreamsMock.total = 3;

const productsMock = [
    {
        id: 1,
        name: 'Gaming chair',
    },
    {
        id: 2,
        name: 'Gaming desk',
    },
];

let repositoryFactoryMock;

async function createWrapper() {
    repositoryFactoryMock = {
        search: () => {
            return Promise.resolve();
        },
        get: () => {
            return Promise.resolve();
        },
    };

    return mount(await wrapTestComponent('sw-sales-channel-products-assignment-dynamic-product-groups', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template: '<div><slot></slot><slot name="grid"></slot></div>',
                },
                'sw-card-section': true,
                'sw-simple-search-field': true,
                'sw-empty-state': true,
                'sw-entity-listing': true,
                'sw-pagination': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return repositoryFactoryMock;
                    },
                },
            },
        },
        props: {
            salesChannel: {
                id: 1,
                name: 'Headless',
            },
            containerStyle: {},
        },
    });
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-products-assignment-dynamic-product-groups', () => {
    it('should get product streams when component got created', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.getProductStreams = jest.fn(() => {
            return Promise.resolve();
        });

        wrapper.vm.createdComponent();

        expect(wrapper.vm.getProductStreams).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.productStreamColumns).toEqual(
            expect.arrayContaining([expect.objectContaining({ property: 'name' })]),
        );

        wrapper.vm.getProductStreams.mockRestore();
    });

    it('should get product streams successful', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.productStreamRepository.search = jest.fn(() => {
            return Promise.resolve(productStreamsMock);
        });

        await wrapper.vm.getProductStreams();

        expect(wrapper.vm.productStreams).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ name: 'Low prices' }),
                expect.objectContaining({ name: 'Standard prices' }),
                expect.objectContaining({ name: 'High prices' }),
            ]),
        );
        expect(wrapper.vm.total).toBe(3);

        wrapper.vm.productStreamRepository.search.mockRestore();
    });

    it('should get product streams failed', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.productStreamRepository.search = jest.fn(() => {
            return Promise.reject();
        });

        await wrapper.vm.getProductStreams();

        expect(wrapper.vm.productStreams).toEqual(expect.arrayContaining([]));
        expect(wrapper.vm.total).toBe(0);

        wrapper.vm.productStreamRepository.search.mockRestore();
    });

    it('should get product streams when searching', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.getProductStreams = jest.fn(() => {
            return Promise.resolve();
        });

        await wrapper.setData({
            page: 2,
        });

        expect(wrapper.vm.page).toBe(2);

        await wrapper.vm.onSearch('Standard prices');

        expect(wrapper.vm.term).toBe('Standard prices');
        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.getProductStreams).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.productStreamCriteria.term).toBe('Standard prices');

        wrapper.vm.getProductStreams.mockRestore();
    });

    it('should get product streams when paginating', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.getProductStreams = jest.fn(() => {
            return Promise.resolve();
        });

        wrapper.vm.onPaginate({ page: 2, limit: 5 });

        expect(wrapper.vm.page).toBe(2);
        expect(wrapper.vm.limit).toBe(5);
        expect(wrapper.vm.getProductStreams).toHaveBeenCalledTimes(1);

        wrapper.vm.getProductStreams.mockRestore();
    });

    it('should open product stream correctly', async () => {
        const wrapper = await createWrapper();

        window.open = jest.fn();
        wrapper.vm.$router.resolve = jest.fn(() => ({ href: 'href' }));

        wrapper.vm.onOpen(productStreamsMock[1]);

        expect(wrapper.vm.$router.resolve).toHaveBeenCalledWith(
            expect.objectContaining({
                name: 'sw.product.stream.detail',
                params: expect.objectContaining({ id: 2 }),
            }),
        );
        expect(window.open).toHaveBeenCalledWith('href', '_blank');

        wrapper.vm.$router.resolve.mockRestore();
        window.open.mockClear();
    });

    it('should emit the products together with the selected product stream ids and their total', async () => {
        const wrapper = await createWrapper();

        const products = [...productsMock];
        products.total = 1024;
        wrapper.vm.getProductsFromProductStreams = jest.fn(() => {
            return Promise.resolve(products);
        });

        await wrapper.vm.onSelect({ 1: productStreamsMock[0], 2: productStreamsMock[1] });

        expect(wrapper.vm.getProductsFromProductStreams).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted()['selection-change'][0]).toEqual([
            products,
            'groupProducts',
            { ids: ['1', '2'], total: 1024 },
        ]);

        wrapper.vm.getProductsFromProductStreams.mockRestore();
    });

    it('should call to show error notification when selecting product streams', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.getProductsFromProductStreams = jest.fn(() => {
            return Promise.reject(new Error('Whoops!'));
        });

        await wrapper.vm.onSelect({ 1: productStreamsMock[0] });

        expect(wrapper.vm.getProductsFromProductStreams).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith(expect.objectContaining({ message: 'Whoops!' }));

        wrapper.vm.getProductsFromProductStreams.mockRestore();
        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should pass each product stream filter to the product search and sum the totals', async () => {
        const wrapper = await createWrapper();

        const filters = {
            1: [{ type: 'equals', field: 'name', value: 'first' }],
            2: [{ type: 'equals', field: 'name', value: 'second' }],
        };
        const results = {
            first: Object.assign([{ id: 'a' }], { total: 600 }),
            second: Object.assign([{ id: 'b' }], { total: 424 }),
        };

        wrapper.vm.productStreamRepository.get = jest.fn((id) => Promise.resolve({ apiFilter: filters[id] }));
        wrapper.vm.getProducts = jest.fn((filter) => Promise.resolve(results[filter[0].value]));

        const products = await wrapper.vm.getProductsFromProductStreams({
            1: productStreamsMock[0],
            2: productStreamsMock[1],
        });

        expect([...products]).toEqual([{ id: 'a' }, { id: 'b' }]);
        expect(products.total).toBe(1024);
        expect(wrapper.vm.getProducts).toHaveBeenNthCalledWith(1, filters[1]);
        expect(wrapper.vm.getProducts).toHaveBeenNthCalledWith(2, filters[2]);
        expect(wrapper.emitted()['product-loading']).toEqual([[true], [false]]);

        wrapper.vm.productStreamRepository.get.mockRestore();
        wrapper.vm.getProducts.mockRestore();
    });

    it('should build the product criteria from the given product stream filter', async () => {
        const wrapper = await createWrapper();

        const streamFilter = [{ type: 'equals', field: 'productNumber', value: 'SW1000' }];

        wrapper.vm.productRepository.search = jest.fn(() => Promise.resolve([]));

        await wrapper.vm.getProducts(streamFilter);

        const [criteria] = wrapper.vm.productRepository.search.mock.calls[0];
        expect(criteria.filters).toEqual([
            streamFilter[0],
            expect.objectContaining({
                type: 'not',
                queries: [expect.objectContaining({ field: 'product.visibilities.salesChannelId', value: 1 })],
            }),
        ]);

        wrapper.vm.productRepository.search.mockRestore();
    });

    it('should exit the function when selecting product streams', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.onSelect({});

        expect(wrapper.emitted()['selection-change'][0]).toEqual(expect.arrayContaining([[], 'groupProducts']));
    });

    it('should get products from product streams successful', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.getProducts = jest.fn(() => {
            return Promise.resolve(productsMock);
        });

        await wrapper.vm.getProductsFromProductStreams({ 1: productStreamsMock[0] }).then((values) => {
            expect(values.flat()).toEqual(
                expect.arrayContaining([
                    expect.objectContaining({ name: 'Gaming chair' }),
                    expect.objectContaining({ name: 'Gaming desk' }),
                ]),
            );
        });

        wrapper.vm.getProducts.mockRestore();
    });

    it('should get products from product streams failed', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.getProducts = jest.fn(() => {
            return Promise.reject(new Error('Whoops!'));
        });

        expect(
            (
                await getError(wrapper.vm.getProductsFromProductStreams, {
                    1: productStreamsMock[0],
                })
            ).message,
        ).toBe('Whoops!');

        wrapper.vm.getProducts.mockRestore();
    });

    it('should get product stream filter successful', async () => {
        const wrapper = await createWrapper();

        const productStreamFilterMock = {
            operator: 'OR',
            queries: [],
            type: 'multi',
        };

        wrapper.vm.productStreamRepository.get = jest.fn(() => {
            return Promise.resolve({
                apiFilter: [productStreamFilterMock],
            });
        });

        await wrapper.vm.getProductStreamFilter(1);

        expect(wrapper.vm.productStreamFilter).toEqual(
            expect.arrayContaining([expect.objectContaining(productStreamFilterMock)]),
        );

        wrapper.vm.productStreamRepository.get.mockRestore();
    });

    it('should get product stream filter failed', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.productStreamRepository.get = jest.fn(() => {
            throw new Error('Whoops!');
        });

        expect((await getError(wrapper.vm.getProductStreamFilter, 1)).message).toBe('Whoops!');

        expect(wrapper.vm.productStreamFilter).toEqual(expect.arrayContaining([]));

        wrapper.vm.productStreamRepository.get.mockRestore();
    });

    it('should get products successful', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.productRepository.search = jest.fn(() => {
            return Promise.resolve(productsMock);
        });

        await wrapper.vm.getProducts().then((products) => {
            expect(products).toEqual(
                expect.arrayContaining([
                    expect.objectContaining({ name: 'Gaming chair' }),
                    expect.objectContaining({ name: 'Gaming desk' }),
                ]),
            );
        });

        wrapper.vm.productRepository.search.mockRestore();
    });

    it('should get products failed', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.productRepository.search = jest.fn(() => {
            throw new Error('Whoops!');
        });

        expect((await getError(wrapper.vm.getProducts)).message).toBe('Whoops!');

        wrapper.vm.productRepository.search.mockRestore();
    });

    it('should not load the visibilities of the products, because only their ids are assigned', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.productRepository.search = jest.fn(() => Promise.resolve(productsMock));

        await wrapper.vm.getProducts();

        const criteria = wrapper.vm.productRepository.search.mock.calls[0][0];
        expect(criteria.hasAssociation('visibilities')).toBe(false);
        // products that are already assigned to the sales channel are still excluded
        expect(criteria.filters).toContainEqual(
            expect.objectContaining({
                type: 'not',
                queries: [expect.objectContaining({ field: 'product.visibilities.salesChannelId', value: 1 })],
            }),
        );

        wrapper.vm.productRepository.search.mockRestore();
    });
});
