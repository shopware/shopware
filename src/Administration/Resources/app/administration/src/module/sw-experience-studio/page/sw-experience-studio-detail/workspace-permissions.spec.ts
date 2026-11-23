import detailComponent from './index';

const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

/** @sw-package discovery */
describe('Experience Studio workspace permissions', () => {
    it('keeps viewer canvas changes local without scheduling autosave', async () => {
        const vm = {
            allowSave: false,
            workspaceStore: { activeWorkspace: { id: 'shared' }, schedule: jest.fn() },
            $nextTick: jest.fn(),
        };

        await methods.scheduleWorkspaceSetup.call(vm);

        expect(vm.workspaceStore.schedule).not.toHaveBeenCalled();
        expect(vm.$nextTick).not.toHaveBeenCalled();
    });

    it('opens the workspace creation dialog for an editor without layout creation permission', () => {
        const vm = {
            allowSave: true,
            allowCreate: false,
            isWorkspacePickerOpen: true,
            workspaceName: 'Previous name',
            workspaceNameAction: null,
        };

        methods.onWorkspaceCreate.call(vm);

        expect(vm.isWorkspacePickerOpen).toBe(false);
        expect(vm.workspaceName).toBe('');
        expect(vm.workspaceNameAction).toBe('create');
    });

    it.each(['onWorkspaceCreate', 'onWorkspaceRename', 'onWorkspaceDelete'])(
        'does not open a write dialog for a viewer through %s',
        (method) => {
            const vm = {
                allowSave: false,
                allowCreate: false,
                allowDelete: false,
                isWorkspacePickerOpen: true,
                workspaceNameAction: null,
                isDeleteWorkspaceModalOpen: false,
                managedWorkspace: null,
            };

            methods[method].call(vm, { id: 'shared', name: 'Shared workspace' });

            expect(vm.isWorkspacePickerOpen).toBe(true);
            expect(vm.workspaceNameAction).toBeNull();
            expect(vm.isDeleteWorkspaceModalOpen).toBe(false);
            expect(vm.managedWorkspace).toBeNull();
        },
    );

    it.each(['create', 'delete'])('does not execute a viewer workspace %s request', async (type) => {
        const vm = { allowSave: false, allowCreate: false, allowDelete: false, executeWorkspaceAction: jest.fn() };

        await methods.requestWorkspaceAction.call(vm, { type, id: 'shared', name: 'New workspace' });

        expect(vm.executeWorkspaceAction).not.toHaveBeenCalled();
    });

    it('does not submit a rename without editor permission', async () => {
        const vm = {
            allowSave: false,
            workspaceName: 'Changed name',
            workspaceNameAction: 'rename',
            managedWorkspace: { id: 'shared' },
            workspaceStore: { rename: jest.fn() },
        };

        await methods.onWorkspaceNameSubmit.call(vm);

        expect(vm.workspaceStore.rename).not.toHaveBeenCalled();
    });

    it('does not submit a deletion without deleter permission', async () => {
        const vm = { allowDelete: false, managedWorkspace: { id: 'shared' }, requestWorkspaceAction: jest.fn() };

        await methods.onWorkspaceDeleteConfirm.call(vm);

        expect(vm.requestWorkspaceAction).not.toHaveBeenCalled();
    });

    it('allows a viewer to open another shared workspace', async () => {
        const action = { type: 'open', id: 'shared' };
        const vm = {
            allowCreate: false,
            allowDelete: false,
            workspaceStore: { activeWorkspace: null },
            openLayouts: [],
            mutationQueue: Promise.resolve(),
            syncActiveLayoutState: jest.fn(),
            executeWorkspaceAction: jest.fn(),
        };

        await methods.requestWorkspaceAction.call(vm, action);

        expect(vm.executeWorkspaceAction).toHaveBeenCalledWith(action);
    });
});
