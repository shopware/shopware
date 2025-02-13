import { mount } from '@vue/test-utils';

const stateMachines = [
    {
        id: '1a2b3c4d5e6f7g8h9i0k',
        name: 'Test',
        technicalName: 'test',
    },
    {
        id: '1a2b3c4d5e6f7g8h9i0l',
        name: 'Test 2',
        technicalName: 'test-2',
    },
];

const stateMachineRepository = {
    search: () => {
        return Promise.resolve(stateMachines);
    },
    save: (stateMachine) => {
        stateMachines.splice(
            stateMachines.findIndex((s) => s.id === stateMachine.id),
            1,
            stateMachine,
        );
    },
};

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-settings-state-machine-list', {
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
                    'sw-icon': true,
                    'sw-language-switch': true,
                    'sw-card-view': {
                        template: `
                            <div class="sw-card-view">
                                <slot></slot>
                            </div>
                        `,
                    },
                    'sw-card': await wrapTestComponent('sw-card'),
                    'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                    'sw-data-grid': await wrapTestComponent('sw-data-grid', {
                        sync: true,
                    }),
                    'sw-ai-copilot-badge': true,
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
                },
            },
        },
    );
}

describe('module/sw-settings-state-machine/page/sw-settings-state-machine-list', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have correct metaInfo', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.$createTitle = jest.fn(() => 'Title');
        const metaInfo = wrapper.vm.$options.metaInfo.call(wrapper.vm);

        expect(metaInfo.title).toBe('Title');
        expect(wrapper.vm.$createTitle).toHaveBeenCalled();
    });

    it('should show correct grid columns', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const grid = wrapper.findComponent('.sw-settings-state-machine-list-grid');

        expect(grid.props().columns).toStrictEqual([
            {
                property: 'name',
                dataIndex: 'name',
                label: 'sw-settings-state-machine.list.grid.columnName',
                width: '50%',
                inlineEdit: 'string',
                routerLink: 'sw.settings.state.machine.detail',
            },
            {
                property: 'technicalName',
                dataIndex: 'technicalName',
                label: 'sw-settings-state-machine.list.grid.columnTechnicalName',
                width: '50%',
            },
        ]);
    });

    it('should reload state machines on language change', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.loadStateMachines = jest.fn();

        await wrapper.vm.onChangeLanguage();

        expect(wrapper.vm.loadStateMachines).toHaveBeenCalled();
    });

    it('should handle onInlineEditCancel', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.loadStateMachines = jest.fn();

        await wrapper.vm.onInlineEditCancel();

        expect(wrapper.vm.loadStateMachines).toHaveBeenCalled();
    });

    it('should handle onInlineEditSave', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const repositorySave = jest.spyOn(wrapper.vm.stateMachineRepository, 'save');

        const stateMachine = {
            ...wrapper.vm.stateMachines[0],
            name: 'Test 3',
        };

        await wrapper.vm.onInlineEditSave(stateMachine);

        expect(repositorySave).toHaveBeenCalled();
        expect(wrapper.vm.stateMachines.find((currentStateMachine) => currentStateMachine.id === stateMachine.id)).toEqual(
            stateMachine,
        );
    });

    it('should throw an error when onInlineEditSave fails', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.stateMachineRepository.save = jest.fn(() => Promise.reject());
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.onInlineEditSave();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();
    });
});
