import { shallowMount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import swOrderGeneralInfo from 'src/module/sw-order/component/sw-order-general-info';

/**
 * @package customer-order
 */

Shopware.Component.register('sw-order-general-info', swOrderGeneralInfo);

const deleteFn = jest.fn(() => Promise.resolve());
const assignFn = jest.fn(() => Promise.resolve());

const orderMock = {
    orderNumber: 10000,
    orderCustomer: {
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
    return shallowMount(await Shopware.Component.build('sw-order-general-info'), {
        propsData: {
            order: orderMock,
            isLoading: false,
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
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show correct summary header', async () => {
        const summary = wrapper.find('.sw-order-general-info__summary-main-header');

        expect(summary.exists()).toBeTruthy();
        expect(summary.text()).toBe('10000 - John Doe (john@doe.dev)');
    });

    it('should not mutate the original of the order\'s tags when removing tag', async () => {
        const tagsStub = wrapper.find('sw-entity-tag-select-stub');

        expect(tagsStub.exists()).toBeTruthy();

        expect(orderMock.tags).toHaveLength(2);
        expect(wrapper.vm.$data.tagCollection).toHaveLength(2);

        await tagsStub.vm.$emit('item-remove', orderMock.tags[0]);

        expect(deleteFn).toHaveBeenCalledTimes(1);
        expect(orderMock.tags).toHaveLength(2);
        expect(wrapper.vm.$data.tagCollection).toHaveLength(1);
    });

    it('should not mutate the original of the order\'s tags when adding tag', async () => {
        const tagsStub = wrapper.find('sw-entity-tag-select-stub');

        expect(tagsStub.exists()).toBeTruthy();

        expect(orderMock.tags).toHaveLength(2);
        expect(wrapper.vm.$data.tagCollection).toHaveLength(2);

        await tagsStub.vm.$emit('item-add', { id: '333', name: '333' });

        expect(assignFn).toHaveBeenCalledTimes(1);
        expect(orderMock.tags).toHaveLength(2);
        expect(wrapper.vm.$data.tagCollection).toHaveLength(3);
    });
});
