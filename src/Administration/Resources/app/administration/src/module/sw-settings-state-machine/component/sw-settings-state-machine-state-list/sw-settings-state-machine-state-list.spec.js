import { mount } from '@vue/test-utils';

const stateMachineStateRepository = {
    save: jest.fn(() => Promise.resolve()),
};

const state = {
    id: '1a2b3c4d5e6f7g8h9i0j',
    name: 'state name',
    technicalName: 'state_technical_name',
    stateMachineId: '1a2b3c4d5e6f7g8h9i0k',
};

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-settings-state-machine-state-list', {
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
                    'sw-card': await wrapTestComponent('sw-card'),
                    'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                    'sw-data-grid': await wrapTestComponent('sw-data-grid', {
                        sync: true,
                    }),
                    'sw-ai-copilot-badge': true,
                    'sw-icon': true,
                    'sw-data-grid-skeleton': true,
                    'sw-provide': true,
                    'router-link': true,
                    'sw-button': true,
                    'sw-extension-component-section': true,
                    'sw-loader': true,
                    'sw-checkbox-field': true,
                    'sw-context-button': true,
                    'sw-data-grid-settings': true,
                    'sw-data-grid-column-boolean': true,
                    'sw-data-grid-inline-edit': true,
                    'sw-context-menu-item': true,
                    'sw-settings-state-machine-state-detail': true,
                },
            },
            props: {
                stateMachineId: '1a2b3c4d5e6f7g8h9i0k',
            },
        },
    );
}

describe('module/sw-settings-state-machine/component/sw-settings-state-machine-state-list', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should show correct grid columns', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const grid = wrapper.findComponent('.sw-settings-state-machine-state-list-grid');

        expect(grid.props().columns).toStrictEqual([
            {
                property: 'name',
                dataIndex: 'name',
                label: 'sw-settings-state-machine.state.list.grid.columnName',
                width: '50%',
                inlineEdit: 'string',
            },
            {
                property: 'technicalName',
                dataIndex: 'technicalName',
                label: 'sw-settings-state-machine.state.list.grid.columnTechnicalName',
                width: '50%',
            },
        ]);
    });

    it('should watch for stateMachineId changes', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.loadStateMachineStates = jest.fn();

        await wrapper.setProps({ stateMachineId: '1a2b3c4d5e6f7g8h9i0l' });

        expect(wrapper.vm.loadStateMachineStates).toHaveBeenCalled();
    });

    it('should handle onInlineEditCancel', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.loadStateMachineStates = jest.fn();

        await wrapper.vm.onInlineEditCancel();

        expect(wrapper.vm.loadStateMachineStates).toHaveBeenCalled();
    });

    it('should handle onInlineEditSave', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.loadStateMachineStates = jest.fn(() => wrapper.vm.stateMachineStates.push(state));

        await wrapper.vm.onInlineEditSave(state);

        expect(wrapper.vm.stateMachineStateRepository.save).toHaveBeenCalled();
        expect(wrapper.vm.loadStateMachineStates).toHaveBeenCalled();
        expect(wrapper.vm.stateMachineStates[0]).toEqual(state);
    });

    it('should throw an error when onInlineEditSave fails', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.stateMachineStateRepository.save = jest.fn(() => Promise.reject());
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.onInlineEditSave(state);

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();
    });

    it('should handle showModal and onModalClose', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.loadStateMachineStates = jest.fn();

        wrapper.vm.showModal(state);

        expect(wrapper.vm.currentStateMachineState).toEqual(state);

        wrapper.vm.onModalClose();

        expect(wrapper.vm.currentStateMachineState).toBeNull();
        expect(wrapper.vm.loadStateMachineStates).toHaveBeenCalled();
    });
});
