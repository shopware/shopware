import { mount } from '@vue/test-utils';

const stateMachineStateRepository = {
    save: jest.fn(() => Promise.resolve()),
};

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-settings-state-machine-state-detail', {
            sync: true,
        }),
        {
            global: {
                provide: {
                    repositoryFactory: {
                        create: (name) => {
                            switch (name) {
                                case 'state_machine_state':
                                    return stateMachineStateRepository;
                                default:
                                    throw new Error(`No repository for ${name} configured`);
                            }
                        },
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
                    'sw-container': true,
                    'sw-text-field': true,
                    'sw-button': true,
                },
            },
            props: {
                currentStateMachineState: {
                    id: '1a2b3c4d5e6f7g8h9i0j',
                    name: 'state name',
                    technicalName: 'state_technical_name',
                    stateMachineId: '1a2b3c4d5e6f7g8h9i0k',
                },
            },
        },
    );
}

describe('module/sw-settings-state-machine/component/sw-settings-state-machine-state-detail', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it("should handle onCancel and emit 'modal-close'", async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.onCancel();

        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should handle onSave', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.onSave();

        expect(wrapper.vm.stateMachineStateRepository.save).toHaveBeenCalled();
        expect(wrapper.emitted('modal-close')).toBeTruthy();

        wrapper.vm.stateMachineStateRepository.save.mockRestore();
    });

    it('should throw an error when onSave fails', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.stateMachineStateRepository.save = jest.fn(() => Promise.reject());
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.onSave();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();

        wrapper.vm.stateMachineStateRepository.save.mockRestore();
    });
});
