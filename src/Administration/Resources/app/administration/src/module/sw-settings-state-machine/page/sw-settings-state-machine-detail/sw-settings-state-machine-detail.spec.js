import { mount } from '@vue/test-utils';

const stateMachineRepository = {
    save: jest.fn(() => Promise.resolve()),
};

const stateMachine = {
    id: '1a2b3c4d5e6f7g8h9i0k',
    name: 'Test',
    technicalName: 'test',
};

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-settings-state-machine-detail', {
            sync: true,
        }),
        {
            global: {
                provide: {
                    repositoryFactory: {
                        create: (name) => {
                            switch (name) {
                                case 'state_machine':
                                    return stateMachineRepository;
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
                    'sw-page': {
                        template: `
                            <div class="sw-page">
                                <slot name="search-bar"></slot>
                                <slot name="smart-bar-back"></slot>
                                <slot name="smart-bar-header"></slot>
                                <slot name="language-switch"></slot>
                                <slot name="smart-bar-actions"></slot>
                                <slot name="side-content"></slot>
                                <slot name="content"></slot>
                                <slot name="sidebar"></slot>
                                <slot></slot>
                            </div>
                        `,
                    },
                    'sw-language-switch': true,
                    'sw-button': true,
                    'sw-button-process': {
                        template: '<div class="sw-button-process"><slot></slot></div>',
                        props: ['disabled'],
                    },
                    'sw-card-view': {
                        template: `
                            <div class="sw-card-view">
                                <slot></slot>
                            </div>
                        `,
                    },
                    'sw-card': true,
                    'sw-card-deprecated': true,
                    'sw-container': true,
                    'sw-text-field': true,
                    'sw-skeleton': true,
                    'sw-settings-state-machine-state-list': true,
                },
            },
            props: {
                stateMachineId: '1a2b3c4d5e6f7g8h9i0k',
            },
        },
    );
}

describe('module/sw-settings-state-machine/page/sw-settings-state-machine-detail', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should allow to save via shortcut', async () => {
        const wrapper = await createWrapper(['state_machine.editor']);
        await flushPromises();

        const saveShortcut = wrapper.vm.$options.shortcuts['SYSTEMKEY+S'];

        expect(saveShortcut.active.call(wrapper.vm)).toBe(true);
    });

    it('should allow to save via button', async () => {
        const wrapper = await createWrapper(['state_machine.editor']);
        await flushPromises();

        const saveButton = wrapper.getComponent('.sw-settings-state-machine-detail__save');

        expect(saveButton.props('disabled')).toBe(false);
    });

    it('should have correct metaInfo', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.setData({
            stateMachine: {
                name: 'Test',
            },
        });

        wrapper.vm.$createTitle = jest.fn(() => 'Title');
        const metaInfo = wrapper.vm.$options.metaInfo.call(wrapper.vm);

        expect(metaInfo.title).toBe('Title');
        expect(wrapper.vm.$createTitle).toHaveBeenNthCalledWith(1, 'Test');

        await wrapper.setData({
            stateMachine: {
                name: null,
            },
        });

        const metaInfoWithoutName = wrapper.vm.$options.metaInfo.call(wrapper.vm);

        expect(metaInfoWithoutName.title).toBe('Title');
    });

    it('should watch for stateMachineId changes', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.loadStateMachine = jest.fn();

        await wrapper.setProps({ stateMachineId: '1a2b3c4d5e6f7g8h9i0l' });

        expect(wrapper.vm.loadStateMachine).toHaveBeenCalled();
    });

    it('should reload state machine on language change', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.loadStateMachine = jest.fn();
        wrapper.vm.$refs.stateMachineStateList.loadStateMachineStates = jest.fn();

        await wrapper.vm.onChangeLanguage();

        expect(wrapper.vm.loadStateMachine).toHaveBeenCalled();
        expect(wrapper.vm.$refs.stateMachineStateList.loadStateMachineStates).toHaveBeenCalled();
    });

    it('should handle onCancel', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.$router.push = jest.fn();

        await wrapper.vm.onCancel();

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({ name: 'sw.settings.state.machine.index' });
    });

    it('should handle onSave', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.loadStateMachine = jest.fn();

        await wrapper.vm.onSave();

        expect(wrapper.vm.stateMachineRepository.save).not.toHaveBeenCalled();
        expect(wrapper.vm.loadStateMachine).not.toHaveBeenCalled();

        await wrapper.setData({
            stateMachine,
        });

        await wrapper.vm.onSave();


        expect(wrapper.vm.stateMachineRepository.save).toHaveBeenCalled();
        expect(wrapper.vm.loadStateMachine).toHaveBeenCalled();

        wrapper.vm.stateMachineRepository.save.mockRestore();
    });

    it('should throw an error when onSave fails', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.setData({
            stateMachine,
        });

        await wrapper.vm.onSave();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();

        wrapper.vm.stateMachineRepository.save.mockRestore();
    });
});
