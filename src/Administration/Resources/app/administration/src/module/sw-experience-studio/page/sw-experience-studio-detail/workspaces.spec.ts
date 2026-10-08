import detailComponent from './index';
import { createWorkspaceConfiguration } from '../../util/workspace-configuration.util';

const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

describe('Experience Studio workspace transitions', () => {
    it('checks inactive frames for unsaved changes before switching', async () => {
        const action = { type: 'open', id: 'other' };
        const vm = {
            mutationQueue: Promise.resolve(),
            syncActiveLayoutState: jest.fn(),
            openLayouts: [
                { id: 'active', dirty: false },
                { id: 'inactive', dirty: true },
            ],
            isLayoutDirty: (layout: { dirty: boolean }) => layout.dirty,
            executeWorkspaceAction: jest.fn(),
            pendingWorkspaceAction: null,
        };
        await methods.requestWorkspaceAction.call(vm, action);
        expect(vm.pendingWorkspaceAction).toEqual(action);
        expect(vm.executeWorkspaceAction).not.toHaveBeenCalled();
    });

    it('keeps the current workspace and dialog when any layout save fails', async () => {
        const action = { type: 'open', id: 'other' };
        const vm = {
            pendingWorkspaceAction: action,
            saveAllOpenLayouts: jest.fn().mockResolvedValue(false),
            executeWorkspaceAction: jest.fn(),
        };
        await methods.confirmWorkspaceAction.call(vm, true);
        expect(vm.executeWorkspaceAction).not.toHaveBeenCalled();
        expect(vm.pendingWorkspaceAction).toBe(action);
    });

    it('does not switch when flushing canvas setup fails', async () => {
        const action = { type: 'open', id: 'other' };
        const vm = {
            pendingWorkspaceAction: action,
            isWorkspaceTransitioning: false,
            scheduleWorkspaceSetup: jest.fn(),
            workspaceStore: { flush: jest.fn().mockResolvedValue(false) },
            openWorkspace: jest.fn(),
        };
        await methods.executeWorkspaceAction.call(vm, action);
        expect(vm.openWorkspace).not.toHaveBeenCalled();
        expect(vm.pendingWorkspaceAction).toBe(action);
        expect(vm.isWorkspaceTransitioning).toBe(false);
    });

    it('flushes setup before opening the target and clears the completed dialog', async () => {
        const action = { type: 'open', id: 'other' };
        const vm = {
            pendingWorkspaceAction: action,
            workspaceNameAction: 'create',
            isWorkspaceTransitioning: false,
            scheduleWorkspaceSetup: jest.fn(),
            workspaceStore: { flush: jest.fn().mockResolvedValue(true) },
            openWorkspace: jest.fn().mockResolvedValue(undefined),
        };
        await methods.executeWorkspaceAction.call(vm, action);
        expect(vm.openWorkspace).toHaveBeenCalledWith('other');
        expect(vm.workspaceStore.flush.mock.invocationCallOrder[0]).toBeLessThan(
            vm.openWorkspace.mock.invocationCallOrder[0],
        );
        expect(vm.pendingWorkspaceAction).toBeNull();
    });

    it('persists only saved layouts and keeps each frame preview selection', async () => {
        const configuration = createWorkspaceConfiguration();
        configuration.frames = [
            'saved',
            'new',
        ].map((layoutId) => ({ layoutId, x: 0, y: 0, width: 700, height: 900, viewport: 'custom', previewEntityId: null }));
        const schedule = jest.fn();
        const vm = {
            workspaceStore: { activeWorkspace: { id: 'workspace' }, schedule },
            $nextTick: jest.fn().mockResolvedValue(undefined),
            $refs: { experienceStudioCanvas: { getWorkspaceConfiguration: () => configuration } },
            openLayouts: [
                { id: 'saved', isNew: false, viewport: 'custom', previewEntityId: 'preview' },
                { id: 'new', isNew: true },
            ],
            previewSalesChannelId: 'channel',
        };
        await methods.scheduleWorkspaceSetup.call(vm);
        expect(schedule).toHaveBeenCalledWith(
            expect.objectContaining({
                frames: [expect.objectContaining({ layoutId: 'saved', previewEntityId: 'preview' })],
                previewSalesChannelId: 'channel',
            }),
        );
    });

    it('does not replace usable workspace state when loading another workspace fails', async () => {
        const previous = { id: 'previous' };
        const layouts = [{ id: 'dirty' }];
        const vm = {
            workspaceStore: { activeWorkspace: previous, get: jest.fn().mockRejectedValue(new Error('missing')) },
            openLayouts: layouts,
            resetWorkspaceEditor: jest.fn(),
            createNotificationError: jest.fn(),
            $t: (key: string) => key,
        };
        await methods.openWorkspace.call(vm, 'missing');
        expect(vm.workspaceStore.activeWorkspace).toBe(previous);
        expect(vm.openLayouts).toBe(layouts);
        expect(vm.resetWorkspaceEditor).not.toHaveBeenCalled();
    });

    it('resets histories and invalidates pending diagnostics even when layouts are reused', () => {
        const vm = {
            diagnoseTimeoutId: null,
            diagnoseRequestSequence: 3,
            latestDiagnoseRequestId: 3,
            editorStore: { reset: jest.fn() },
            openLayouts: [{ id: 'same-layout', unsaved: true }],
            layout: { id: 'same-layout' },
        };
        methods.resetWorkspaceEditor.call(vm);
        expect(vm.editorStore.reset).toHaveBeenCalledTimes(1);
        expect(vm.latestDiagnoseRequestId).toBe(4);
        expect(vm.openLayouts).toEqual([]);
        expect(vm.layout).toBeNull();
    });
    it('does nothing when the already selected workspace is chosen again', async () => {
        const vm = {
            workspaceStore: { activeWorkspace: { id: 'current' } },
            openLayouts: [],
            syncActiveLayoutState: jest.fn(),
            executeWorkspaceAction: jest.fn(),
        };
        await methods.requestWorkspaceAction.call(vm, { type: 'open', id: 'current' });
        expect(vm.syncActiveLayoutState).not.toHaveBeenCalled();
        expect(vm.executeWorkspaceAction).not.toHaveBeenCalled();
    });

    it('loads saved content in frame order, skips deleted layouts, and restores preview defaults', async () => {
        const configuration = createWorkspaceConfiguration();
        configuration.frames = [
            'saved-b',
            'deleted',
            'saved-a',
        ].map((layoutId) => ({
            layoutId,
            x: 100,
            y: 200,
            width: 700,
            height: 900,
            viewport: 'custom',
            previewEntityId: null,
        }));
        const layouts = [
            { id: 'saved-a', name: 'A' },
            { id: 'saved-b', name: 'B' },
        ];
        const activeWorkspace = { id: 'old' };
        const vm = {
            workspaceStore: {
                activeWorkspace,
                get: jest.fn().mockResolvedValue({ id: 'new', configuration }),
                activate: jest.fn(),
            },
            layoutLoadCriteria: new Shopware.Data.Criteria(1, 25),
            restoreWorkspaceLayouts: methods.restoreWorkspaceLayouts,
            restoreFramePreviewEntity: methods.restoreFramePreviewEntity,
            restoreWorkspaceSalesChannel: methods.restoreWorkspaceSalesChannel,
            layoutRepository: { search: jest.fn().mockResolvedValue(layouts) },
            createOpenLayoutState: (layout: { id: string }) => ({ id: layout.id, layout, isNew: false }),
            resolvePreviewContext: () => null,
            salesChannelRepository: { search: jest.fn().mockResolvedValue({ first: () => ({ id: 'channel' }) }) },
            defaultSalesChannelCriteria: new Shopware.Data.Criteria(1, 1),
            resetWorkspaceEditor: jest.fn(),
            activateLayout: jest.fn(),
            $nextTick: jest.fn().mockResolvedValue(undefined),
            $router: { replace: jest.fn().mockResolvedValue(undefined) },
            createNotificationWarning: jest.fn(),
            createNotificationError: jest.fn(),
            $t: (key: string) => key,
            openLayouts: [] as Array<{ id: string }>,
            workspaceCanvasConfiguration: null,
            previewSalesChannelId: null,
        };
        await methods.openWorkspace.call(vm, 'new');
        expect(vm.createNotificationError).not.toHaveBeenCalled();
        expect(vm.openLayouts.map((item) => item.id)).toEqual([
            'saved-b',
            'saved-a',
        ]);
        expect(vm.activateLayout).toHaveBeenCalledWith('saved-b');
        expect(vm.workspaceCanvasConfiguration).toEqual(configuration);
        expect(vm.previewSalesChannelId).toBe('channel');
        expect(vm.createNotificationWarning).toHaveBeenCalledTimes(1);
    });
});
