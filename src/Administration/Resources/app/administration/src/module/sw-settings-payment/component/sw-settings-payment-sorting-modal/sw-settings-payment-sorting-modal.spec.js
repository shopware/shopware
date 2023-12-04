import { mount } from '@vue/test-utils';

/**
 * @package checkout
 */

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-settings-payment-sorting-modal', {
        sync: true,
    }), {
        props: {
            paymentMethods: [
                {
                    id: '1a',
                    position: 1,
                },
                {
                    id: '2b',
                    position: 2,
                },
            ],
        },
        global: {
            renderStubDefaultSlot: true,
            provide: {
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
                repositoryFactory: {
                    create: () => {
                        return {
                            saveAll: () => {
                                return Promise.resolve();
                            },
                        };
                    },
                },
            },
            stubs: {
                'sw-modal': true,
                'sw-sortable-list': true,
                'sw-button': true,
                'sw-button-process': true,
            },
        },
    });
}

describe('module/sw-settings-payment/component/sw-settings-payment-sorting-modal', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should save reordered methods', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.sortedPaymentMethods = [
            wrapper.vm.sortedPaymentMethods[1],
            wrapper.vm.sortedPaymentMethods[0],
        ];

        wrapper.vm.paymentMethodRepository.saveAll = jest.fn(() => Promise.resolve());

        await wrapper.vm.applyChanges();

        expect(wrapper.vm.paymentMethodRepository.saveAll).toHaveBeenCalledWith([
            {
                id: '2b',
                position: 1,
            },
            {
                id: '1a',
                position: 2,
            },
        ], Shopware.Context.api);

        wrapper.vm.paymentMethodRepository.saveAll.mockRestore();
    });
});

