import detailComponent from './index';

const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

/** @sw-package discovery */
describe('Experience Studio workspace management', () => {
    it('renames the selected list entry and returns to the list without switching the active workspace', async () => {
        const activeWorkspace = { id: 'current', name: 'Current workspace' };
        const workspace = { id: 'other', name: 'Shared workspace' };
        const vm = {
            allowSave: true,
            workspaceStore: { activeWorkspace, rename: jest.fn().mockResolvedValue(undefined) },
            managedWorkspace: null,
            workspaceName: '',
            workspaceNameAction: null,
            isWorkspacePickerOpen: true,
            isWorkspaceTransitioning: false,
            onCancelWorkspaceName: methods.onCancelWorkspaceName,
            createNotificationError: jest.fn(),
        };

        methods.onWorkspaceRename.call(vm, workspace);
        expect(vm.workspaceName).toBe('Shared workspace');
        expect(vm.isWorkspacePickerOpen).toBe(false);
        vm.workspaceName = ' Renamed workspace ';
        await methods.onWorkspaceNameSubmit.call(vm);

        expect(vm.workspaceStore.rename).toHaveBeenCalledWith('other', 'Renamed workspace');
        expect(vm.workspaceStore.activeWorkspace).toBe(activeWorkspace);
        expect(vm.isWorkspacePickerOpen).toBe(true);
        expect(vm.managedWorkspace).toBeNull();
        expect(vm.workspaceNameAction).toBeNull();
        expect(vm.isWorkspaceTransitioning).toBe(false);
        expect(vm.createNotificationError).not.toHaveBeenCalled();
    });

    it('keeps the rename dialog available when saving the new name fails', async () => {
        const workspace = { id: 'other', name: 'Shared workspace' };
        const vm = {
            allowSave: true,
            workspaceStore: { rename: jest.fn().mockRejectedValue(new Error('Save failed')) },
            managedWorkspace: workspace,
            workspaceName: 'New name',
            workspaceNameAction: 'rename',
            isWorkspacePickerOpen: false,
            isWorkspaceTransitioning: false,
            onCancelWorkspaceName: jest.fn(),
            createNotificationError: jest.fn(),
            $t: (key: string) => key,
        };

        await methods.onWorkspaceNameSubmit.call(vm);

        expect(vm.managedWorkspace).toBe(workspace);
        expect(vm.workspaceNameAction).toBe('rename');
        expect(vm.onCancelWorkspaceName).not.toHaveBeenCalled();
        expect(vm.createNotificationError).toHaveBeenCalled();
        expect(vm.isWorkspaceTransitioning).toBe(false);
    });

    it('deletes the selected inactive workspace without touching unsaved layouts in the current workspace', async () => {
        const activeWorkspace = { id: 'current' };
        const layouts = [{ id: 'dirty-layout', dirty: true }];
        const vm = {
            allowDelete: true,
            workspaceStore: { activeWorkspace, remove: jest.fn().mockResolvedValue(undefined), flush: jest.fn() },
            managedWorkspace: null,
            isWorkspacePickerOpen: true,
            isDeleteWorkspaceModalOpen: false,
            isWorkspaceTransitioning: false,
            openLayouts: layouts,
            pendingWorkspaceAction: null,
            requestWorkspaceAction: methods.requestWorkspaceAction,
            executeWorkspaceAction: methods.executeWorkspaceAction,
            syncActiveLayoutState: jest.fn(),
            scheduleWorkspaceSetup: jest.fn(),
            resetWorkspaceEditor: jest.fn(),
            $router: { replace: jest.fn() },
            createNotificationError: jest.fn(),
        };

        methods.onWorkspaceDelete.call(vm, { id: 'other', name: 'Shared workspace' });
        expect(vm.isDeleteWorkspaceModalOpen).toBe(true);
        expect(vm.isWorkspacePickerOpen).toBe(false);
        await methods.onWorkspaceDeleteConfirm.call(vm);

        expect(vm.workspaceStore.remove).toHaveBeenCalledWith('other');
        expect(vm.workspaceStore.activeWorkspace).toBe(activeWorkspace);
        expect(vm.openLayouts).toBe(layouts);
        expect(vm.syncActiveLayoutState).not.toHaveBeenCalled();
        expect(vm.scheduleWorkspaceSetup).not.toHaveBeenCalled();
        expect(vm.workspaceStore.flush).not.toHaveBeenCalled();
        expect(vm.resetWorkspaceEditor).not.toHaveBeenCalled();
        expect(vm.$router.replace).not.toHaveBeenCalled();
        expect(vm.isWorkspacePickerOpen).toBe(true);
        expect(vm.pendingWorkspaceAction).toBeNull();
    });

    it('still asks to save or discard dirty layouts before deleting the active workspace', async () => {
        const action = { type: 'delete', id: 'current' };
        const vm = {
            allowDelete: true,
            workspaceStore: { activeWorkspace: { id: 'current' } },
            mutationQueue: Promise.resolve(),
            openLayouts: [{ id: 'dirty-layout', dirty: true }],
            syncActiveLayoutState: jest.fn(),
            isLayoutDirty: () => true,
            executeWorkspaceAction: jest.fn(),
            pendingWorkspaceAction: null,
        };

        await methods.requestWorkspaceAction.call(vm, action);

        expect(vm.pendingWorkspaceAction).toEqual(action);
        expect(vm.executeWorkspaceAction).not.toHaveBeenCalled();
    });

    it('flushes and closes the active workspace after its deletion is confirmed', async () => {
        const vm = {
            workspaceStore: {
                activeWorkspace: { id: 'current' },
                flush: jest.fn().mockResolvedValue(true),
                remove: jest.fn().mockResolvedValue(undefined),
            },
            scheduleWorkspaceSetup: jest.fn(),
            resetWorkspaceEditor: jest.fn(),
            $router: { replace: jest.fn().mockResolvedValue(undefined) },
            createNotificationError: jest.fn(),
            isWorkspacePickerOpen: false,
            managedWorkspace: { id: 'current' },
        };

        await methods.executeWorkspaceAction.call(vm, { type: 'delete', id: 'current' });

        expect(vm.workspaceStore.remove).toHaveBeenCalledWith('current');
        expect(vm.workspaceStore.flush.mock.invocationCallOrder[0]).toBeLessThan(
            vm.workspaceStore.remove.mock.invocationCallOrder[0],
        );
        expect(vm.resetWorkspaceEditor).toHaveBeenCalledTimes(1);
        expect(vm.$router.replace).toHaveBeenCalledWith({ name: 'sw.experience.studio.index' });
        expect(vm.isWorkspacePickerOpen).toBe(true);
        expect(vm.managedWorkspace).toBeNull();
    });

    it('keeps the canvas and reopens the list when deleting another workspace fails', async () => {
        const activeWorkspace = { id: 'current' };
        const vm = {
            workspaceStore: { activeWorkspace, remove: jest.fn().mockRejectedValue(new Error('Delete failed')) },
            resetWorkspaceEditor: jest.fn(),
            createNotificationError: jest.fn(),
            $t: (key: string) => key,
            isWorkspacePickerOpen: false,
        };

        await methods.executeWorkspaceAction.call(vm, { type: 'delete', id: 'other' });

        expect(vm.workspaceStore.activeWorkspace).toBe(activeWorkspace);
        expect(vm.resetWorkspaceEditor).not.toHaveBeenCalled();
        expect(vm.createNotificationError).toHaveBeenCalled();
        expect(vm.isWorkspacePickerOpen).toBe(true);
    });

    it.each([
        'onCancelWorkspaceName',
        'onCancelWorkspaceDelete',
        'cancelWorkspaceAction',
    ])('returns to the list when a management action is canceled with %s', (method) => {
        const vm = {
            workspaceStore: { activeWorkspace: { id: 'current' } },
            managedWorkspace: { id: 'other' },
            workspaceNameAction: 'rename',
            isWorkspacePickerOpen: false,
            pendingWorkspaceAction: { type: 'delete', id: 'current' },
        };

        methods[method].call(vm);

        expect(vm.isWorkspacePickerOpen).toBe(true);
        expect(vm.managedWorkspace).toBeNull();
    });
});
