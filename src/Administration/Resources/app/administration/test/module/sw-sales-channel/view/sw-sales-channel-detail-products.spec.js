import { shallowMount } from '@vue/test-utils';
import state from 'src/module/sw-sales-channel/state/salesChannel.store';
import 'src/module/sw-sales-channel/view/sw-sales-channel-detail-products';

Shopware.State.registerModule('swSalesChannel', state);

const categories = [
    {
        parentId: null,
        active: true,
        name: 'Catalogue #1',
        id: '1'
    },
    {
        parentId: null,
        active: true,
        name: 'Catalogue #2',
        id: '2'
    }
];

describe('src/module/sw-sales-channel/view/sw-sales-channel-detail-products', () => {
    let wrapper;
    const noop = () => {};

    beforeAll(() => {
        Shopware.Service().register('repositoryFactory', () => {
            return {
                create: () => repositoryFactoryMock()
            };
        });
    });

    beforeEach(() => {
        wrapper = shallowMount(
            Shopware.Component.build('sw-sales-channel-detail-products'),
            {
                store: Shopware.State._store,

                propsData: {
                    salesChannel: {},
                    productExport: {},
                    isLoading: false
                },

                stubs: {
                    'router-view': true,
                    'sw-card': true,
                    'sw-container': true,
                    'sw-sales-channel-detail-shop-categories': true,
                    'sw-sales-channel-detail-empty-categories': true,
                    'sw-sales-channel-detail-product-comparison': true
                },

                mocks: {
                    $tc: key => key,
                    $router: { push: noop },
                    $route: { params: { categoryId: 'categoryId' } }
                }
            }
        );
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should loadRootCategories() successfully', async () => {
        await wrapper.setData({ categoryId: '' });

        await wrapper.vm.loadRootCategories();

        await wrapper.vm.addCategories();

        expect(wrapper.vm.categories).toEqual(categories);
    });

    it('should loadActiveCategory() successfully', async () => {
        await wrapper.setData({ categoryId: 'fa636b64b25a4232bb9d12ead7e2dce7' });

        await wrapper.vm.loadActiveCategory();

        expect(wrapper.vm.category).toEqual(categories[0]);
    });

    it('should show error notification when loadActiveCategory() failly', async () => {
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.setData({ categoryId: '' });

        await wrapper.vm.loadActiveCategory();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            title: 'global.default.error',
            message: 'This is a detail error message'
        });

        wrapper.vm.createNotificationError.mockRestore();
    });
});

function repositoryFactoryMock() {
    return {
        get: (categoryId) => {
            return getRepositoryFactoryMock(categoryId);
        },

        search: () => {
            return Promise.resolve(categories);
        }
    };
}

function getRepositoryFactoryMock(categoryId) {
    if (categoryId) {
        return Promise.resolve(categories[0]);
    }

    const exception = {
        response: {
            data: {
                errors: [
                    {
                        code: 'This is an error code',
                        detail: 'This is a detail error message'
                    }
                ]
            }
        }
    };

    return Promise.reject(exception);
}
