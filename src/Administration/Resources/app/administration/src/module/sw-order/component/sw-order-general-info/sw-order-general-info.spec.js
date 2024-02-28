import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

/**
 * @package customer-order
 */

const deleteFn = jest.fn(() => Promise.resolve());
const assignFn = jest.fn(() => Promise.resolve());

const orderMock = {
    id: '123',
    orderNumber: 10000,
    orderCustomer: {
        customerId: 'orderID',
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@doe.dev',
    },
    currency: {
        translated: {
            name: '',
        },
    },
    totalRounding: {
        decimals: 2,
    },
    transactions: [
        {
            stateMachineState: {
                translated: {
                    name: '',
                },
            },
            paymentMethod: {
                translated: {
                    distinguishableName: 'Payment Method',
                },
            },
        },
    ],
    deliveries: [
        {
            stateMachineState: {
                translated: {
                    name: '',
                },
            },
            shippingMethod: {
                translated: {
                    name: '',
                },
            },
        },
    ],
    stateMachineState: {
        translated: {
            name: '',
        },
    },
    tags: [
        {
            id: '111',
            name: '1',
        },
        {
            id: '222',
            name: '2',
        },
    ],
};

orderMock.transactions.last = () => ({
    stateMachineState: {
        translated: {
            name: '',
        },
    },
    paymentMethod: {
        translated: {
            distinguishableName: 'Payment Method',
        },
    },
});

orderMock.deliveries.last = () => ({
    stateMachineState: {
        translated: {
            name: '',
        },
    },
    shippingMethod: {
        translated: {
            name: '',
        },
    },
});

const $route = {
    params: { id: '123' },
};

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-general-info', { sync: true }), {
        props: {
            order: orderMock,
            isLoading: false,
        },
        global: {
            mocks: {
                $route,
            },
            provide: {
                orderStateMachineService: {},
                stateStyleDataProviderService: {
                    getStyle: () => {
                        return {
                            placeholder: {
                                icon: 'small-arrow-small-down',
                                iconStyle: 'sw-order-state__bg-neutral-icon',
                                iconBackgroundStyle: 'sw-order-state__bg-neutral-icon-bg',
                                selectBackgroundStyle: 'sw-order-state__bg-neutral-select',
                                variant: 'neutral',
                                colorCode: '#94a6b8',
                            },
                        };
                    },
                },
                stateMachineService: {
                    getState: () => { return { data: { } }; },
                },
                feature: {
                    isActive: () => true,
                },
                repositoryFactory: {
                    create() {
                        return {
                            search: () => Promise.resolve(new EntityCollection(
                                '',
                                '',
                                Shopware.Context.api,
                                null,
                            )),
                            delete: deleteFn,
                            assign: assignFn,
                        };
                    },
                },
            },
            stubs: {
                'sw-order-state-select-v2': true,
                'sw-entity-tag-select': true,
            },
        },

    });
}

describe('src/module/sw-order/component/sw-order-general-info', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.State.registerModule('swOrderDetail', {
            namespaced: true,
            state: {
                isLoading: false,
                isSavedSuccessful: false,
                versionContext: {},
            },
            mutations: {
                setLoading() {},
            },
        });
    });

    beforeEach(async () => {
        global.repositoryFactoryMock.showError = false;
        wrapper = await createWrapper();
        await flushPromises();

        jest.clearAllMocks();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show correct summary header', async () => {
        const summary = wrapper.find('.sw-order-general-info__summary-main-header');
        const link = wrapper.find('.sw-order-general-info__summary-main-header-link');

        expect(summary.exists()).toBeTruthy();
        expect(link.exists()).toBeTruthy();
        expect(summary.text()).toContain('10000');
        expect(summary.text()).toContain('John Doe');
        expect(summary.text()).toContain('john@doe.dev');
    });

    it('should not mutate the original of the order\'s tags when removing tag', async () => {
        const tagsStub = wrapper.findComponent('sw-entity-tag-select-stub');

        expect(tagsStub.exists()).toBeTruthy();

        expect(orderMock.tags).toHaveLength(2);
        expect(wrapper.vm.$data.tagCollection).toHaveLength(2);

        await tagsStub.vm.$emit('item-remove', orderMock.tags[0]);

        expect(deleteFn).toHaveBeenCalledTimes(1);
        expect(orderMock.tags).toHaveLength(2);
        expect(wrapper.vm.$data.tagCollection).toHaveLength(1);
    });

    it('should not mutate the original of the order\'s tags when adding tag', async () => {
        const tagsStub = wrapper.findComponent('sw-entity-tag-select-stub');

        expect(tagsStub.exists()).toBeTruthy();

        expect(orderMock.tags).toHaveLength(2);
        expect(wrapper.vm.$data.tagCollection).toHaveLength(2);

        await tagsStub.vm.$emit('item-add', { id: '333', name: '333' });

        expect(assignFn).toHaveBeenCalledTimes(1);
        expect(orderMock.tags).toHaveLength(2);
        expect(wrapper.vm.$data.tagCollection).toHaveLength(3);
    });

    it('should not update order.id or call createdComponent when $route does not change', async () => {
        const spyCreatedComponent = jest.spyOn(wrapper.vm, 'createdComponent');

        expect(wrapper.vm.order.id).toBe('123');

        await wrapper.vm.$options.watch.$route.call(wrapper.vm, { params: { id: '123' } }, $route);
        expect(wrapper.vm.order.id).toBe('123');
        expect(spyCreatedComponent).toHaveBeenCalledTimes(0);
    });

    it('should update order.id and call createdComponent when $route changes', async () => {
        const spyCreatedComponent = jest.spyOn(wrapper.vm, 'createdComponent');

        expect(wrapper.vm.order.id).toBe('123');

        await wrapper.vm.$options.watch.$route.call(wrapper.vm, { params: { id: '1234' } }, $route);
        expect(wrapper.vm.order.id).toBe('1234');
        expect(spyCreatedComponent).toHaveBeenCalledTimes(1);
    });
});
