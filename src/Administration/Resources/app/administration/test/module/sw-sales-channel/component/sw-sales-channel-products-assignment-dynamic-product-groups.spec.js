import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-sales-channel/component/sw-sales-channel-products-assignment-dynamic-product-groups';

const productStreamsMock = [
    {
        id: 1,
        name: 'Low prices'
    },
    {
        id: 2,
        name: 'Standard prices'
    },
    {
        id: 3,
        name: 'High prices'
    }
];
productStreamsMock.total = 3;

const productsMock = [
    {
        id: 1,
        name: 'Gaming chair'
    },
    {
        id: 2,
        name: 'Gaming desk'
    }
];

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-sales-channel-products-assignment-dynamic-product-groups'), {
        stubs: {
            'sw-alert': true,
            'sw-card': true
        },
        provide: {
            repositoryFactory: {
                create: () => {
                    return {
                        search: () => {
                            return Promise.resolve();
                        },
                        get: () => {
                            return Promise.resolve();
                        }
                    };
                }
            }
        },
        propsData: {
            salesChannel: {
                id: 1,
                name: 'Headless'
            }
        }
    });
}

describe('src/module/sw-sales-channel/component/sw-sales-channel-products-assignment-dynamic-product-groups', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.JS component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should get product streams when component got created', () => {
        wrapper.vm.getProductStreams = jest.fn(() => {
            return Promise.resolve();
        });

        wrapper.vm.createdComponent();

        expect(wrapper.vm.getProductStreams).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.productStreamColumns).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ property: 'name' })
            ])
        );

        wrapper.vm.getProductStreams.mockRestore();
    });

    it('should get product streams successful', async () => {
        wrapper.vm.productStreamRepository.search = jest.fn(() => {
            return Promise.resolve(productStreamsMock);
        });

        await wrapper.vm.getProductStreams();

        expect(wrapper.vm.productStreams).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ name: 'Low prices' }),
                expect.objectContaining({ name: 'Standard prices' }),
                expect.objectContaining({ name: 'High prices' })
            ])
        );
        expect(wrapper.vm.total).toEqual(3);

        wrapper.vm.productStreamRepository.search.mockRestore();
    });

    it('should get product streams failed', async () => {
        wrapper.vm.productStreamRepository.search = jest.fn(() => {
            return Promise.reject();
        });

        await wrapper.vm.getProductStreams();

        expect(wrapper.vm.productStreams).toEqual(
            expect.arrayContaining([])
        );
        expect(wrapper.vm.total).toEqual(0);

        wrapper.vm.productStreamRepository.search.mockRestore();
    });

    it('should get product streams when searching', async () => {
        wrapper.vm.getProductStreams = jest.fn(() => {
            return Promise.resolve();
        });

        await wrapper.setData({
            page: 2
        });

        expect(wrapper.vm.page).toEqual(2);

        await wrapper.vm.onSearch('Standard prices');

        expect(wrapper.vm.term).toBe('Standard prices');
        expect(wrapper.vm.page).toEqual(1);
        expect(wrapper.vm.getProductStreams).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.productStreamCriteria.term).toBe('Standard prices');

        wrapper.vm.getProductStreams.mockRestore();
    });

    it('should get product streams when paginating', () => {
        wrapper.vm.getProductStreams = jest.fn(() => {
            return Promise.resolve();
        });

        wrapper.vm.onPaginate({ page: 2, limit: 5 });

        expect(wrapper.vm.page).toBe(2);
        expect(wrapper.vm.limit).toBe(5);
        expect(wrapper.vm.getProductStreams).toHaveBeenCalledTimes(1);

        wrapper.vm.getProductStreams.mockRestore();
    });

    it('should open product stream correctly', () => {
        window.open = jest.fn();
        wrapper.vm.$router.resolve = jest.fn(() => ({ href: 'href' }));

        wrapper.vm.onOpen(productStreamsMock[1]);

        expect(wrapper.vm.$router.resolve).toHaveBeenCalledWith(
            expect.objectContaining({
                name: 'sw.product.stream.detail',
                params: expect.objectContaining({ id: 2 })
            })
        );
        expect(window.open).toHaveBeenCalledWith('href', '_blank');

        wrapper.vm.$router.resolve.mockRestore();
        window.open.mockClear();
    });

    it('should call to get products from product streams when selecting product streams', async () => {
        wrapper.vm.getProductsFromProductStreams = jest.fn(() => {
            return Promise.resolve(productsMock);
        });

        await wrapper.vm.onSelect({ 1: productStreamsMock[0] });

        expect(wrapper.vm.getProductsFromProductStreams).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted()['selection-change'][0]).toEqual(
            expect.arrayContaining([productsMock, 'groupProducts'])
        );

        wrapper.vm.getProductsFromProductStreams.mockRestore();
    });

    it('should call to show error notification when selecting product streams', async () => {
        wrapper.vm.createNotificationError = jest.fn();
        wrapper.vm.getProductsFromProductStreams = jest.fn(() => {
            return Promise.reject(new Error('Whoops!'));
        });

        await wrapper.vm.onSelect({ 1: productStreamsMock[0] });

        expect(wrapper.vm.getProductsFromProductStreams).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith(
            expect.objectContaining({ message: 'Whoops!' })
        );

        wrapper.vm.getProductsFromProductStreams.mockRestore();
        wrapper.vm.createNotificationError.mockRestore();
    });

    it('should exit the function when selecting product streams', () => {
        wrapper.vm.onSelect({});

        expect(wrapper.emitted()['selection-change'][0]).toEqual(
            expect.arrayContaining([[], 'groupProducts'])
        );
    });

    it('should get products from product streams successful', async () => {
        wrapper.vm.getProducts = jest.fn(() => {
            return Promise.resolve(productsMock);
        });

        await wrapper.vm.getProductsFromProductStreams({ 1: productStreamsMock[0] }).then((values) => {
            expect(values.flat()).toEqual(
                expect.arrayContaining([
                    expect.objectContaining({ name: 'Gaming chair' }),
                    expect.objectContaining({ name: 'Gaming desk' })
                ])
            );
        });

        wrapper.vm.getProducts.mockRestore();
    });

    it('should get products from product streams failed', async () => {
        wrapper.vm.getProducts = jest.fn(() => {
            return Promise.reject(new Error('Whoops!'));
        });

        await wrapper.vm.getProductsFromProductStreams({ 1: productStreamsMock[0] }).catch((error) => {
            expect(error.message).toBe('Whoops!');
        });

        wrapper.vm.getProducts.mockRestore();
    });

    it('should get product stream filter successful', async () => {
        const productStreamFilterMock = { operator: 'OR', queries: [], type: 'multi' };

        wrapper.vm.productStreamRepository.get = jest.fn(() => {
            return Promise.resolve({
                apiFilter: [
                    productStreamFilterMock
                ]
            });
        });

        await wrapper.vm.getProductStreamFilter(1);

        expect(wrapper.vm.productStreamFilter).toEqual(
            expect.arrayContaining([
                expect.objectContaining(productStreamFilterMock)
            ])
        );

        wrapper.vm.productStreamRepository.get.mockRestore();
    });

    it('should get product stream filter failed', async () => {
        wrapper.vm.productStreamRepository.get = jest.fn(() => {
            return Promise.reject(new Error('Whoops!'));
        });

        await wrapper.vm.getProductStreamFilter(1).catch((error) => {
            expect(error.message).toBe('Whoops!');
        });

        expect(wrapper.vm.productStreamFilter).toEqual(
            expect.arrayContaining([])
        );

        wrapper.vm.productStreamRepository.get.mockRestore();
    });

    it('should get products successful', async () => {
        wrapper.vm.productRepository.search = jest.fn(() => {
            return Promise.resolve(productsMock);
        });

        await wrapper.vm.getProducts().then((products) => {
            expect(products).toEqual(
                expect.arrayContaining([
                    expect.objectContaining({ name: 'Gaming chair' }),
                    expect.objectContaining({ name: 'Gaming desk' })
                ])
            );
        });

        wrapper.vm.productRepository.search.mockRestore();
    });

    it('should get products failed', async () => {
        wrapper.vm.productRepository.search = jest.fn(() => {
            return Promise.reject(new Error('Whoops!'));
        });

        await wrapper.vm.getProducts().catch((error) => {
            expect(error.message).toBe('Whoops!');
        });

        wrapper.vm.productRepository.search.mockRestore();
    });
});
