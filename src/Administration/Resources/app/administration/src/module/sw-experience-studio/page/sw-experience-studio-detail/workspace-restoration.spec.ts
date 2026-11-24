import type CriteriaType from 'src/core/data/criteria.data';
import type { WorkspaceConfiguration, WorkspaceFrame } from '../../types/workspace.types';
import detailComponent from './index';
import { getStorefrontSalesChannelCriteria } from '../../util/sales-channel-criteria.util';
import { createWorkspaceConfiguration } from '../../util/workspace-configuration.util';

const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

function createRestore() {
    const configuration = createWorkspaceConfiguration();
    const frame: WorkspaceFrame = {
        layoutId: 'layout-a',
        x: 0,
        y: 0,
        width: 1480,
        height: 900,
        viewport: 'desktop',
        previewEntityId: 'saved-entity',
    };
    configuration.frames = [frame];
    const target = { id: 'target', configuration };
    const current = { id: 'current', configuration: createWorkspaceConfiguration() };
    const entityRepository = {
        searchIds: jest.fn().mockResolvedValue({ data: ['saved-entity'] }),
        search: jest.fn().mockResolvedValue({ first: () => ({ id: 'default-entity' }) }),
    };
    const previewEntityId = frame.previewEntityId;
    const editor = {
        workspaceStore: {
            activeWorkspace: current as typeof current | null,
            get: jest.fn().mockResolvedValue(target),
            activate: jest.fn(),
            schedule: jest.fn(),
        },
        repositoryFactory: { create: jest.fn(() => entityRepository) },
        layoutRepository: {
            search: jest.fn().mockResolvedValue([
                { id: 'layout-a', name: 'Products', rootSource: 'product', layout: [] },
            ]),
        },
        layoutLoadCriteria: new Shopware.Data.Criteria(1, 25),
        restoreWorkspaceLayouts: methods.restoreWorkspaceLayouts,
        restoreFramePreviewEntity: methods.restoreFramePreviewEntity,
        restoreWorkspaceSalesChannel: methods.restoreWorkspaceSalesChannel,
        defaultSalesChannelCriteria: getStorefrontSalesChannelCriteria(1),
        salesChannelRepository: { search: jest.fn().mockResolvedValue({ first: () => ({ id: 'channel' }) }) },
        createOpenLayoutState: methods.createOpenLayoutState,
        serializeLayoutState: methods.serializeLayoutState,
        availableLayoutTypes: ['product'],
        layoutTypeLoadError: null as string | null,
        layoutTypesRequest: Promise.resolve(),
        getLayoutRootSource: methods.getLayoutRootSource,
        getFirstAssociationEntry: methods.getFirstAssociationEntry,
        resolveAssignedPreviewContext: methods.resolveAssignedPreviewContext,
        resolvePreviewContext: methods.resolvePreviewContext,
        resetWorkspaceEditor: jest.fn(),
        activateLayout: jest.fn(),
        openLayouts: [{ id: 'current-layout', previewEntityId: 'current-entity' }],
        workspaceCanvasConfiguration: current.configuration,
        previewSalesChannelId: 'current-channel',
        workspaceError: false,
        isWorkspacePickerOpen: false,
        isLoading: false,
        isRestoringWorkspace: false,
        allowSave: true,
        activeLayoutId: 'layout-a',
        currentViewport: 'desktop',
        previewEntityId,
        $refs: {
            experienceStudioCanvas: {
                getWorkspaceConfiguration: jest.fn<WorkspaceConfiguration, []>(() =>
                    Shopware.Utils.object.cloneDeep(configuration),
                ),
            },
        },
        $nextTick: jest.fn().mockResolvedValue(undefined),
        $router: { replace: jest.fn().mockResolvedValue(undefined) },
        createNotificationWarning: jest.fn(),
        createNotificationError: jest.fn(),
        $t: (key: string) => key,
    };

    return { editor, current, target, entityRepository };
}

describe('Experience Studio workspace preview reference restoration', () => {
    it('rejects a workspace action while waiting for entity types before opening a workspace', async () => {
        const { editor, target } = createRestore();
        let resolveLayoutTypes: () => void;
        const layoutTypesRequest = new Promise<void>((resolve) => {
            resolveLayoutTypes = resolve;
        });
        const executeWorkspaceAction = jest.fn();
        const workspaceEditor = {
            ...editor,
            layoutTypesRequest,
            openLayouts: [],
            isWorkspaceTransitioning: false,
            isSavingAll: false,
            mutationQueue: Promise.resolve(),
            syncActiveLayoutState: jest.fn(),
            executeWorkspaceAction,
        };

        const open = methods.openWorkspace.call(workspaceEditor, target.id);
        await methods.requestWorkspaceAction.call(workspaceEditor, { type: 'open', id: 'other' });

        expect(executeWorkspaceAction).not.toHaveBeenCalled();

        resolveLayoutTypes!();
        await open;
    });

    it.each([
        'unavailable',
        'forbidden',
        'default-lookup',
        'repository-creation',
    ])('preserves the current editor and saved configuration after a %s failure', async (failure) => {
        const { editor, current, target, entityRepository } = createRestore();
        const originalLayouts = editor.openLayouts;
        const originalConfiguration = Shopware.Utils.object.cloneDeep(target.configuration);
        if (failure === 'repository-creation') {
            editor.repositoryFactory.create.mockImplementation(() => {
                throw new TypeError('Repository unavailable');
            });
        } else if (failure === 'default-lookup') {
            entityRepository.searchIds.mockResolvedValue({ data: [] });
            entityRepository.search.mockRejectedValue({ response: { status: 503 } });
        } else {
            entityRepository.searchIds.mockRejectedValue({ response: { status: failure === 'forbidden' ? 403 : 503 } });
        }

        await methods.openWorkspace.call(editor, target.id);

        expect(editor.workspaceError).toBe(true);
        expect(editor.workspaceStore.activeWorkspace).toBe(current);
        expect(editor.workspaceStore.activate).not.toHaveBeenCalled();
        expect(editor.resetWorkspaceEditor).not.toHaveBeenCalled();
        expect(editor.openLayouts).toBe(originalLayouts);
        expect(editor.workspaceCanvasConfiguration).toBe(current.configuration);
        expect(editor.previewSalesChannelId).toBe('current-channel');
        expect(target.configuration).toEqual(originalConfiguration);
        expect(editor.$router.replace).not.toHaveBeenCalled();
        expect(editor.createNotificationWarning).not.toHaveBeenCalled();
        expect(editor.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-experience-studio.workspace.manager.loadError',
        });
        expect(editor.isLoading).toBe(false);
        expect(editor.isRestoringWorkspace).toBe(false);
    });

    it('keeps the workspace picker open after an initial preview lookup failure', async () => {
        const { editor, target, entityRepository } = createRestore();
        editor.workspaceStore.activeWorkspace = null;
        entityRepository.searchIds.mockRejectedValue({ response: { status: 503 } });

        await methods.openWorkspace.call(editor, target.id);

        expect(editor.workspaceError).toBe(true);
        expect(editor.isWorkspacePickerOpen).toBe(true);
        expect(editor.workspaceStore.activate).not.toHaveBeenCalled();
        expect(target.configuration.frames[0].previewEntityId).toBe('saved-entity');
    });

    it.each(['default-entity', null])(
        'replaces a saved reference only after a successful lookup confirms it is missing (fallback: %s)',
        async (fallback) => {
            const { editor, target, entityRepository } = createRestore();
            entityRepository.searchIds.mockResolvedValue({ data: [] });
            entityRepository.search.mockResolvedValue({ first: () => (fallback ? { id: fallback } : null) });

            await methods.openWorkspace.call(editor, target.id);

            expect(editor.workspaceError).toBe(false);
            expect(editor.openLayouts[0].previewEntityId).toBe(fallback);
            expect(editor.workspaceStore.activate).toHaveBeenCalledWith(target);
            expect(editor.createNotificationWarning).toHaveBeenCalledWith({
                message: 'sw-experience-studio.workspace.manager.missingReferences',
            });
            expect(editor.createNotificationError).not.toHaveBeenCalled();
        },
    );

    it('clears a saved preview entity without a missing-reference warning for a non-entity frame', async () => {
        const { editor, target, entityRepository } = createRestore();
        editor.layoutRepository.search.mockResolvedValue([
            { id: 'layout-a', name: 'Sidebar', rootSource: 'swag-sidebar', layout: [] },
        ]);

        await methods.openWorkspace.call(editor, target.id);

        expect(editor.openLayouts[0].previewEntityId).toBeNull();
        expect(editor.createNotificationWarning).not.toHaveBeenCalled();
        expect(editor.repositoryFactory.create).not.toHaveBeenCalled();
        expect(entityRepository.searchIds).not.toHaveBeenCalled();
    });

    it('keeps a saved preview entity without a missing-reference warning when entity types failed to load', async () => {
        const { editor, target, entityRepository } = createRestore();
        editor.availableLayoutTypes = [];
        editor.layoutTypeLoadError = 'Failed to load layout types.';

        await methods.openWorkspace.call(editor, target.id);

        expect(editor.openLayouts[0].previewEntityId).toBe('saved-entity');
        expect(editor.createNotificationWarning).not.toHaveBeenCalled();
        expect(editor.repositoryFactory.create).not.toHaveBeenCalled();
        expect(entityRepository.searchIds).not.toHaveBeenCalled();
    });

    it('schedules the saved preview entity after the entity type request fails', async () => {
        const { editor, target } = createRestore();
        editor.availableLayoutTypes = [];
        editor.layoutTypeLoadError = 'Failed to load layout types.';
        editor.activeLayoutId = 'different-layout';
        editor.previewEntityId = null;
        const scheduledConfiguration: WorkspaceConfiguration = {
            ...Shopware.Utils.object.cloneDeep(target.configuration),
            frames: [{ ...target.configuration.frames[0], previewEntityId: null }],
        };
        editor.$refs.experienceStudioCanvas.getWorkspaceConfiguration.mockReturnValue(scheduledConfiguration);

        await methods.openWorkspace.call(editor, target.id);
        editor.workspaceStore.activeWorkspace = target;
        await methods.scheduleWorkspaceSetup.call(editor);

        expect(editor.workspaceStore.schedule).toHaveBeenCalledWith(
            expect.objectContaining({
                frames: [expect.objectContaining({ layoutId: 'layout-a', previewEntityId: 'saved-entity' })],
            }),
        );
    });

    it('waits for the entity type request before loading the workspace', async () => {
        const { editor, target } = createRestore();
        let resolveLayoutTypes: () => void;
        editor.layoutTypesRequest = new Promise<void>((resolve) => {
            resolveLayoutTypes = () => {
                editor.availableLayoutTypes = ['product'];
                resolve();
            };
        });

        const open = methods.openWorkspace.call(editor, target.id);
        await Promise.resolve();
        expect(editor.workspaceStore.get).not.toHaveBeenCalled();

        resolveLayoutTypes!();
        await open;

        expect(editor.openLayouts[0].previewEntityId).toBe('saved-entity');
    });

    // Regression guard: a product frame keeps a saved preview entity when the entity exists.
    it('preserves a saved reference confirmed by a successful lookup', async () => {
        const { editor, target, entityRepository } = createRestore();

        await methods.openWorkspace.call(editor, target.id);

        expect(editor.openLayouts[0].previewEntityId).toBe('saved-entity');
        expect(entityRepository.search).not.toHaveBeenCalled();
        expect(editor.workspaceStore.activate).toHaveBeenCalledWith(target);
        expect(editor.createNotificationWarning).not.toHaveBeenCalled();
        expect(editor.createNotificationError).not.toHaveBeenCalled();
    });

    it('restores the saved selection when retrying after a transient failure', async () => {
        const { editor, target, entityRepository } = createRestore();
        entityRepository.searchIds.mockRejectedValueOnce({ response: { status: 503 } });
        await methods.openWorkspace.call(editor, target.id);
        expect(editor.workspaceError).toBe(true);

        await methods.openWorkspace.call(editor, target.id);

        expect(editor.workspaceError).toBe(false);
        expect(editor.openLayouts[0].previewEntityId).toBe('saved-entity');
        expect(editor.workspaceStore.activate).toHaveBeenCalledTimes(1);
        expect(target.configuration.frames[0].previewEntityId).toBe('saved-entity');
    });
});

describe('Experience Studio workspace sales-channel restoration', () => {
    function channelSearch(ids: string[]) {
        return jest.fn((criteria: CriteriaType) => {
            const matching = ids.filter((id) =>
                criteria.filters.every((filter) => filter.type !== 'equals' || filter.field !== 'id' || filter.value === id),
            );

            return Promise.resolve({ length: matching.length, first: () => (matching[0] ? { id: matching[0] } : null) });
        });
    }

    it('restores different saved channels without accumulating ID filters across workspaces', async () => {
        const { editor, target } = createRestore();
        target.configuration.previewSalesChannelId = 'channel-a';
        const second = {
            ...target,
            id: 'second',
            configuration: { ...target.configuration, previewSalesChannelId: 'channel-b' },
        };
        editor.workspaceStore.get.mockResolvedValueOnce(target).mockResolvedValueOnce(second);
        const search = channelSearch(['channel-a', 'channel-b']);
        editor.salesChannelRepository.search = search;
        const defaultFilters = Shopware.Utils.object.cloneDeep(editor.defaultSalesChannelCriteria.filters);

        await methods.openWorkspace.call(editor, target.id);
        expect(editor.previewSalesChannelId).toBe('channel-a');
        await methods.openWorkspace.call(editor, second.id);

        expect(editor.previewSalesChannelId).toBe('channel-b');
        expect(editor.workspaceError).toBe(false);
        expect(search).toHaveBeenCalledTimes(2);
        expect(search.mock.calls[0][0]).not.toBe(search.mock.calls[1][0]);
        expect(editor.defaultSalesChannelCriteria.filters).toEqual(defaultFilters);
        expect(editor.createNotificationWarning).not.toHaveBeenCalled();
    });

    it('selects an available default channel when the saved channel has been deleted', async () => {
        const { editor, target } = createRestore();
        target.configuration.previewSalesChannelId = 'deleted-channel';
        const search = channelSearch(['available-channel']);
        editor.salesChannelRepository.search = search;

        await methods.openWorkspace.call(editor, target.id);

        expect(editor.previewSalesChannelId).toBe('available-channel');
        expect(editor.workspaceError).toBe(false);
        expect(search).toHaveBeenCalledTimes(2);
        expect(search.mock.calls[0][0]).not.toBe(editor.defaultSalesChannelCriteria);
        expect(search.mock.calls[1][0]).toBe(editor.defaultSalesChannelCriteria);
        expect(editor.defaultSalesChannelCriteria.filters).toEqual(getStorefrontSalesChannelCriteria(1).filters);
        expect(editor.createNotificationWarning).toHaveBeenCalledWith({
            message: 'sw-experience-studio.workspace.manager.missingReferences',
        });
    });

    it('uses an unfiltered default for a workspace without a saved channel after restoring another workspace', async () => {
        const { editor, target } = createRestore();
        target.configuration.previewSalesChannelId = 'channel-b';
        const second = {
            ...target,
            id: 'second',
            configuration: { ...target.configuration, previewSalesChannelId: null },
        };
        editor.workspaceStore.get.mockResolvedValueOnce(target).mockResolvedValueOnce(second);
        editor.salesChannelRepository.search = channelSearch(['channel-a', 'channel-b']);
        await methods.openWorkspace.call(editor, target.id);
        expect(editor.previewSalesChannelId).toBe('channel-b');

        await methods.openWorkspace.call(editor, second.id);

        expect(editor.previewSalesChannelId).toBe('channel-a');
        expect(editor.workspaceError).toBe(false);
        expect(editor.createNotificationWarning).not.toHaveBeenCalled();
    });
});
