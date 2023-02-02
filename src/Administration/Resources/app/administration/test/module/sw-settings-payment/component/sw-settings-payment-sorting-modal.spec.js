import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-payment/component/sw-settings-payment-sorting-modal';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-settings-payment-sorting-modal'), {
        propsData: {
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
        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            },
            repositoryFactory: {
                create: () => {
                    return {
                        saveAll: () => {
                            return Promise.resolve();
                        },
                    };
                }
            }
        },
        stubs: {
            'sw-modal': true,
            'sw-sortable-list': true,
            'sw-button': true,
            'sw-button-process': true,
        }
    });
}

describe('module/sw-settings-payment/component/sw-settings-payment-sorting-modal', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should save reordered methods', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.sortedPaymentMethods = [
            wrapper.vm.sortedPaymentMethods[1],
            wrapper.vm.sortedPaymentMethods[0],
        ];

        wrapper.vm.paymentMethodRepository.saveAll = jest.fn(() => Promise.resolve());

        await wrapper.vm.applyChanges();

        expect(wrapper.vm.paymentMethodRepository.saveAll).toBeCalledWith([
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

