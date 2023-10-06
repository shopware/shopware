import { shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-button-process';
import 'src/app/component/list/sw-sortable-list';
import 'src/app/component/utils/sw-loader';
import swSettingsPaymentSortingModal from 'src/module/sw-settings-payment/component/sw-settings-payment-sorting-modal';
import Entity from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';

/**
 * @package checkout
 */

Shopware.Component.register('sw-settings-payment-sorting-modal', swSettingsPaymentSortingModal);

async function createWrapper(privileges = []) {
    return shallowMount(await Shopware.Component.build('sw-settings-payment-sorting-modal'), {
        propsData: {
            paymentMethods: [
                {
                    active: true,
                    id: '1a',
                    translated: {
                        distinguishableName: '1a',
                    },
                    position: 1,
                },
                {
                    active: true,
                    id: '2b',
                    translated: {
                        distinguishableName: '2b',
                    },
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
                },
            },
            repositoryFactory: {
                create: () => {
                    return {
                        saveAll: jest.fn(() => {
                            return Promise.resolve();
                        }),
                    };
                },
            },
        },
        stubs: {
            'sw-modal': true,
            'sw-icon': true,
            'sw-sortable-list': await Shopware.Component.build('sw-sortable-list'),
            'sw-button': true,
            'sw-button-process': {
                template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>',
            },
            'sw-media-preview-v2': true,
            'sw-loader': true,
        },
    });
}

describe('module/sw-settings-payment/component/sw-settings-payment-sorting-modal', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should save reordered methods', async () => {
        const wrapper = await createWrapper([
            'category.editor',
        ]);
        await flushPromises();

        await wrapper.find('.sw-settings-payment-sorting-modal__save-button').trigger('click');

        expect(wrapper.vm.paymentMethodRepository.saveAll).toHaveBeenCalledWith([
            {
                active: true,
                id: '1a',
                translated: {
                    distinguishableName: '1a',
                },
                position: 1,
            },
            {
                active: true,
                id: '2b',
                translated: {
                    distinguishableName: '2b',
                },
                position: 2,
            },
        ], Shopware.Context.api);
    });

    it('should reorder methods', async () => {
        const wrapper = await createWrapper([
            'category.editor',
        ]);
        await flushPromises();

        const sortableList = wrapper.find('.sw-settings-payment-sorting-modal__payment-method-list');
        sortableList.vm.onDragStart();
        sortableList.vm.onDragEnter(new Entity('1a', null, {
            active: true,
            id: '1a',
            translated: {
                distinguishableName: '1a',
            },
            position: 1,
        }), new Entity('2b', null, {
            active: true,
            id: '2b',
            translated: {
                distinguishableName: '2b',
            },
            position: 2,
        }));
        sortableList.vm.onDrop();

        await wrapper.find('.sw-settings-payment-sorting-modal__save-button').trigger('click');

        expect(wrapper.vm.paymentMethodRepository.saveAll).toHaveBeenCalledWith([
            {
                active: true,
                id: '2b',
                translated: {
                    distinguishableName: '2b',
                },
                position: 1,
            },
            {
                active: true,
                id: '1a',
                translated: {
                    distinguishableName: '1a',
                },
                position: 2,
            },
        ], Shopware.Context.api);
    });

    it('should return filters from filter registry', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.assetFilter).toEqual(expect.any(Function));
    });
});

