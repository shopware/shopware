import { mount } from '@vue/test-utils';

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-settings-tax-provider-sorting-modal', {
        sync: true,
    }), {
        global: {
            provide: {
                repositoryFactory: {
                    create: () => Promise.resolve(),
                },
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
            },
            stubs: {
                'sw-modal': true,
                'sw-button': true,
                'sw-button-process': true,
                'sw-sortable-list': true,
            },
        },
        props: {
            taxProviders: [],
        },
    });
}

describe('module/sw-settings-tax/component/sw-settings-tax-provider-sorting-modal', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();


        expect(wrapper.vm).toBeTruthy();
    });

    it('should be handle onClose and emit \'modal-close\'', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.closeModal();

        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should handle applyChanges', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        wrapper.vm.closeModal();

        wrapper.vm.taxProviderRepository.saveAll = jest.fn(() => Promise.resolve());

        await wrapper.vm.applyChanges([]);

        expect(wrapper.vm.taxProviderRepository.saveAll).toHaveBeenCalledWith([]);
        expect(wrapper.emitted('modal-close')).toBeTruthy();
        expect(wrapper.emitted('modal-save')).toBeTruthy();

        wrapper.vm.taxProviderRepository.saveAll.mockRestore();
    });

    it('should handle onSort', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const sortedItems = ['item-1', 'item-2', 'item-3'];
        wrapper.vm.onSort(sortedItems);

        expect(wrapper.vm.sortedTaxProviders).toEqual(sortedItems);
    });
});
