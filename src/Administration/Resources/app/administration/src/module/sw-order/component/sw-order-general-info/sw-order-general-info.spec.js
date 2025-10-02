import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import { createPinia, setActivePinia } from 'pinia';

/**
 * @sw-package checkout
 */

const deleteFn = jest.fn(() => Promise.resolve());
const assignFn = jest.fn(() => Promise.resolve());

const orderMock = {
    id: '123',
    orderNumber: 10000,
    updatedAt: null,
    createdAt: null,
    updatedBy: null,
    createdBy: null,
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

async function createWrapper() {
    return mount(await wrapTestComponent('sw-order-general-info', { sync: true }), {
        props: {
            order: orderMock,
            isLoading: false,
        },
        global: {
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
                    getState: () => {
                        return { data: {} };
                    },
                },
                feature: {
                    isActive: () => true,
                },
                repositoryFactory: {
                    create(entityName) {
                        if (entityName !== 'order') {
                            return {
                                search: () => Promise.resolve(new EntityCollection('', '', Shopware.Context.api, null)),
                                delete: deleteFn,
                                assign: assignFn,
                            };
                        }

                        return {
                            search: () =>
                                Promise.resolve(new EntityCollection('', 'order', Shopware.Context.api, null, [orderMock])),
                            delete: deleteFn,
                            assign: assignFn,
                        };
                    },
                },
            },
            stubs: {
                'sw-order-state-select-v2': true,
                'sw-entity-tag-select': true,
                'router-link': {
                    template: '<div><slot></slot></div>',
                },
                'sw-order-state-change-modal': true,
                'sw-time-ago': true,
            },
        },
    });
}

describe('src/module/sw-order/component/sw-order-general-info', () => {
    let wrapper;

    beforeAll(() => {
        global.activeAclRoles = ['order.editor'];
        setActivePinia(createPinia());
    });

    beforeEach(async () => {
        global.repositoryFactoryMock.showError = false;
        wrapper = await createWrapper();
        await flushPromises();

        jest.clearAllMocks();
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

    it("should not mutate the original of the order's tags when removing tag", async () => {
        const tagsStub = wrapper.findComponent('sw-entity-tag-select-stub');

        expect(tagsStub.exists()).toBeTruthy();

        expect(orderMock.tags).toHaveLength(2);
        expect(wrapper.vm.$data.tagCollection).toHaveLength(2);

        await tagsStub.vm.$emit('item-remove', orderMock.tags[0]);

        expect(deleteFn).toHaveBeenCalledTimes(1);
        expect(orderMock.tags).toHaveLength(2);
        expect(wrapper.vm.$data.tagCollection).toHaveLength(1);
    });

    it("should not mutate the original of the order's tags when adding tag", async () => {
        const tagsStub = wrapper.findComponent('sw-entity-tag-select-stub');

        expect(tagsStub.exists()).toBeTruthy();

        expect(orderMock.tags).toHaveLength(2);
        expect(wrapper.vm.$data.tagCollection).toHaveLength(2);

        await tagsStub.vm.$emit('item-add', { id: '333', name: '333' });

        expect(assignFn).toHaveBeenCalledTimes(1);
        expect(orderMock.tags).toHaveLength(2);
        expect(wrapper.vm.$data.tagCollection).toHaveLength(3);
    });

    it('should call createComponent on order id change', async () => {
        const spyCreatedComponent = jest.spyOn(wrapper.vm, 'createdComponent');

        wrapper.vm.$options.watch['order.id'].call(wrapper.vm);

        expect(spyCreatedComponent).toHaveBeenCalledTimes(1);
    });

    it('should output no user if last updated by api', async () => {
        orderMock.updatedBy = null;
        orderMock.createdBy = 'foo';
        orderMock.updatedAt = '2020-01-01T00:00:00.000Z';
        orderMock.createdAt = '2019-01-01T00:00:00.000Z';
        wrapper = await createWrapper();

        expect(wrapper.vm.lastChangedUser).toBeNull();
        expect(wrapper.vm.lastChangedDateTime).toBe('2020-01-01T00:00:00.000Z');
    });

    it('should output user if last updated by user', async () => {
        orderMock.updatedBy = 'bar';
        orderMock.createdBy = 'foo';
        orderMock.updatedAt = '2020-01-01T00:00:00.000Z';
        orderMock.createdAt = '2019-01-01T00:00:00.000Z';
        wrapper = await createWrapper();

        expect(wrapper.vm.lastChangedUser).toBe('bar');
        expect(wrapper.vm.lastChangedDateTime).toBe('2020-01-01T00:00:00.000Z');
    });

    it('should disable state selects on loading', async () => {
        const stateSelects = wrapper.findAll('.sw-order-general-info__order-state');
        expect(stateSelects).toHaveLength(3);

        wrapper.vm.onLeaveModalConfirm([], false);

        expect(Shopware.Store.get('swOrderDetail').isLoading).toBeTruthy();

        Shopware.Store.get('swOrderDetail').savedSuccessful = true;
        wrapper.vm.$options.watch.savedSuccessful.call(wrapper.vm, false, true);

        expect(Shopware.Store.get('swOrderDetail').isLoading).toBeFalsy();

        stateSelects.forEach((select) => {
            // get first child first
            const selectStub = select.find('sw-order-state-select-v2-stub');
            expect(selectStub.attributes('disabled')).toBe('false');
        });
    });
});
