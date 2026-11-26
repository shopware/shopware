import type CriteriaType from 'src/core/data/criteria.data';
import type { WorkspaceConfiguration } from '../../types/workspace.types';
import detailComponent from './index';
import { getStorefrontSalesChannelCriteria } from '../../util/sales-channel-criteria.util';
import { createRestore } from './index.spec/workspace-restoration.fixtures';

const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

describe('Experience Studio workspace preview reference restoration', () => {
    it('rejects a workspace action while the opening workspace waits for entity types', async () => {
        const { editor, target, entityTypeService } = createRestore();
        let resolveLayoutTypes: (types: string[]) => void;
        entityTypeService.getEntityTypes.mockReturnValue(
            new Promise<string[]>((resolve) => {
                resolveLayoutTypes = resolve;
            }),
        );
        const executeWorkspaceAction = jest.fn();
        const workspaceEditor = {
            ...editor,
            openLayouts: [],
            isWorkspaceTransitioning: false,
            isSavingAll: false,
            mutationQueue: Promise.resolve(),
            syncActiveLayoutState: jest.fn(),
            isLayoutDirty: () => false,
            executeWorkspaceAction,
        };

        const open = methods.openWorkspace.call(workspaceEditor, target.id);
        await flushPromises();
        await methods.requestWorkspaceAction.call(workspaceEditor, { type: 'open', id: 'other' });

        expect(executeWorkspaceAction).not.toHaveBeenCalled();

        resolveLayoutTypes!(['product']);
        await open;
    });

    it('replaces saved frame languages unavailable in the selected channel before opening the workspace', async () => {
        const { editor, target } = createRestore();
        const germanLanguageId = 'a'.repeat(32) as EntityKey<'language'>;
        target.configuration.frames[0].languageId = 'unavailable';
        editor.salesChannelRepository.get.mockResolvedValueOnce({
            id: 'channel',
            languageId: germanLanguageId,
            languages: [{ id: germanLanguageId, parentId: null, active: true }],
        });

        await methods.openWorkspace.call(editor, target.id);

        expect(editor.frames[0].languageId).toBe(germanLanguageId);
        expect(target.configuration.frames[0].languageId).toBe('unavailable');
        expect(editor.createNotificationWarning).toHaveBeenCalledTimes(1);
        expect(editor.createNotificationError).not.toHaveBeenCalled();
    });

    it.each(['unassigned', 'inactive'])(
        'opens the workspace with a warning when its preview channel has %s languages',
        async (state) => {
            const { editor, target } = createRestore();
            target.configuration.previewSalesChannelId = 'channel';
            const configuration = Shopware.Utils.object.cloneDeep(target.configuration);
            editor.salesChannelRepository.search.mockResolvedValueOnce({ length: 1, first: () => ({ id: 'channel' }) });
            editor.salesChannelRepository.get.mockResolvedValueOnce({
                id: 'channel',
                languageId: Shopware.Defaults.systemLanguageId,
                languages:
                    state === 'unassigned'
                        ? []
                        : [{ id: Shopware.Defaults.systemLanguageId, parentId: null, active: false }],
            });

            await methods.openWorkspace.call(editor, target.id);

            expect(editor.workspaceError).toBe(false);
            expect(editor.workspaceStore.activate).toHaveBeenCalledWith(target);
            expect(editor.frames).toEqual([
                expect.objectContaining({ id: 'layout-a', layoutId: 'layout-a', previewEntityId: 'saved-entity' }),
            ]);
            expect(editor.previewSalesChannelId).toBeNull();
            expect(editor.previewLanguageContext).toBeNull();
            expect(editor.$router.replace).toHaveBeenCalledWith({
                name: 'sw.experience.studio.workspace',
                params: { workspaceId: target.id },
            });
            expect(target.configuration).toEqual(configuration);
            expect(editor.createNotificationWarning).toHaveBeenCalledWith({
                message: 'sw-experience-studio.workspace.manager.missingReferences',
            });
            expect(editor.createNotificationError).not.toHaveBeenCalled();
        },
    );

    it('falls back to a default channel when the saved preview channel has no active language', async () => {
        const { editor, target } = createRestore();
        target.configuration.previewSalesChannelId = 'channel';
        editor.salesChannelRepository.search
            .mockResolvedValueOnce({ length: 1, first: () => ({ id: 'channel' }) })
            .mockResolvedValueOnce({ length: 1, first: () => ({ id: 'fallback-channel' }) });
        editor.salesChannelRepository.get.mockResolvedValueOnce({
            id: 'channel',
            languageId: Shopware.Defaults.systemLanguageId,
            languages: [],
        });

        await methods.openWorkspace.call(editor, target.id);

        expect(editor.workspaceError).toBe(false);
        expect(editor.previewSalesChannelId).toBe('fallback-channel');
        expect(editor.previewLanguageContext).toEqual(expect.objectContaining({ salesChannelId: 'fallback-channel' }));
        expect(editor.frames[0].languageId).toBe(Shopware.Defaults.systemLanguageId);
        expect(editor.createNotificationWarning).toHaveBeenCalledWith({
            message: 'sw-experience-studio.workspace.manager.missingReferences',
        });
    });

    it('preserves the current workspace when the target channel language lookup fails', async () => {
        const { editor, target, current } = createRestore();
        const frames = editor.frames;
        editor.salesChannelRepository.get.mockRejectedValueOnce(new Error('language lookup failed'));

        await methods.openWorkspace.call(editor, target.id);

        expect(editor.workspaceStore.activeWorkspace).toBe(current);
        expect(editor.frames).toBe(frames);
        expect(editor.resetWorkspaceEditor).not.toHaveBeenCalled();
        expect(editor.createNotificationError).toHaveBeenCalledTimes(1);
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
            expect(editor.frames[0].previewEntityId).toBe(fallback);
            expect(editor.workspaceStore.activate).toHaveBeenCalledWith(target);
            expect(editor.createNotificationWarning).toHaveBeenCalledWith({
                message: 'sw-experience-studio.workspace.manager.missingReferences',
            });
            expect(editor.createNotificationError).not.toHaveBeenCalled();
        },
    );

    it('clears and schedules an empty preview entity without a missing-reference warning for a non-entity frame', async () => {
        const { editor, target, entityRepository } = createRestore();
        editor.layoutRepository.search.mockResolvedValue([
            { id: 'layout-a', name: 'Sidebar', rootSource: 'swag-sidebar', layout: [] },
        ]);
        editor.activeFrameId = 'different-frame';
        editor.previewEntityId = null;

        await methods.openWorkspace.call(editor, target.id);

        expect(editor.frames[0].previewEntityId).toBeNull();
        expect(editor.createNotificationWarning).not.toHaveBeenCalled();
        expect(editor.repositoryFactory.create).not.toHaveBeenCalled();
        expect(entityRepository.searchIds).not.toHaveBeenCalled();

        editor.workspaceStore.activeWorkspace = target;
        await methods.scheduleWorkspaceSetup.call(editor);

        expect(editor.workspaceStore.schedule).toHaveBeenCalledWith(
            expect.objectContaining({
                frames: [expect.objectContaining({ layoutId: 'layout-a', previewEntityId: null })],
            }),
        );
    });

    it('keeps and schedules a saved preview entity without a missing-reference warning when entity types failed to load', async () => {
        const { editor, target, entityRepository, entityTypeService } = createRestore();
        entityTypeService.getEntityTypes.mockRejectedValue(new Error('Entity types unavailable'));
        editor.activeFrameId = 'different-frame';
        editor.previewEntityId = null;
        const scheduledConfiguration: WorkspaceConfiguration = {
            ...Shopware.Utils.object.cloneDeep(target.configuration),
            frames: [{ ...target.configuration.frames[0], previewEntityId: null }],
        };
        editor.$refs.experienceStudioCanvas.getWorkspaceConfiguration.mockReturnValue(scheduledConfiguration);

        await methods.openWorkspace.call(editor, target.id);

        expect(editor.frames[0].previewEntityId).toBe('saved-entity');
        expect(editor.createNotificationWarning).not.toHaveBeenCalled();
        expect(editor.repositoryFactory.create).not.toHaveBeenCalled();
        expect(entityRepository.searchIds).not.toHaveBeenCalled();

        editor.workspaceStore.activeWorkspace = target;
        await methods.scheduleWorkspaceSetup.call(editor);

        expect(editor.workspaceStore.schedule).toHaveBeenCalledWith(
            expect.objectContaining({
                frames: [expect.objectContaining({ layoutId: 'layout-a', previewEntityId: 'saved-entity' })],
            }),
        );
    });

    it('notifies the user when the entity type request fails', async () => {
        const { editor, target, entityTypeService } = createRestore();
        entityTypeService.getEntityTypes.mockRejectedValue(new Error('Entity types unavailable'));

        await methods.openWorkspace.call(editor, target.id);

        expect(editor.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-experience-studio.detail.toolbar.previewEntityTypesLoadError',
        });
    });

    it('retries a failed entity type request when the next workspace opens', async () => {
        const { editor, target, entityRepository, entityTypeService } = createRestore();
        entityTypeService.getEntityTypes
            .mockRejectedValueOnce(new Error('Entity types unavailable'))
            .mockResolvedValueOnce(['product']);
        entityRepository.searchIds.mockResolvedValue({ data: [] });
        await methods.openWorkspace.call(editor, target.id);
        expect(editor.frames[0].previewEntityId).toBe('saved-entity');

        await methods.openWorkspace.call(editor, target.id);

        expect(editor.frames[0].previewEntityId).toBe('default-entity');
    });

    it('fetches the workspace while the entity type request is pending', async () => {
        const { editor, target, entityTypeService } = createRestore();
        let resolveLayoutTypes: (types: string[]) => void;
        entityTypeService.getEntityTypes.mockReturnValue(
            new Promise<string[]>((resolve) => {
                resolveLayoutTypes = resolve;
            }),
        );

        const open = methods.openWorkspace.call(editor, target.id);
        await flushPromises();
        expect(editor.workspaceStore.get).toHaveBeenCalledWith(target.id);

        resolveLayoutTypes!(['product']);
        await open;
    });

    it('restores no frame before the entity type request settles', async () => {
        const { editor, target, entityTypeService } = createRestore();
        const currentFrames = editor.frames;
        let resolveLayoutTypes: (types: string[]) => void;
        entityTypeService.getEntityTypes.mockReturnValue(
            new Promise<string[]>((resolve) => {
                resolveLayoutTypes = resolve;
            }),
        );

        const open = methods.openWorkspace.call(editor, target.id);
        await flushPromises();
        expect(editor.frames).toBe(currentFrames);

        resolveLayoutTypes!(['product']);
        await open;

        expect(editor.frames[0].previewEntityId).toBe('saved-entity');
    });

    // Regression guard: a product frame keeps a saved preview entity when the entity exists.
    it('preserves a saved reference confirmed by a successful lookup', async () => {
        const { editor, target, entityRepository } = createRestore();

        await methods.openWorkspace.call(editor, target.id);

        expect(editor.frames[0].previewEntityId).toBe('saved-entity');
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
        expect(editor.frames[0].previewEntityId).toBe('saved-entity');
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
