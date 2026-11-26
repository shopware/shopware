import type { WorkspaceConfiguration, WorkspaceFrame } from '../../../types/workspace.types';
import detailComponent from '../index';
import { getStorefrontSalesChannelCriteria } from '../../../util/sales-channel-criteria.util';
import { createWorkspaceConfiguration } from '../../../util/workspace-configuration.util';

const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;

/** @private @sw-package discovery */
export function createRestore() {
    const configuration = createWorkspaceConfiguration();
    const frame: WorkspaceFrame = {
        id: 'layout-a',
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
    const entityTypeService = { getEntityTypes: jest.fn().mockResolvedValue(['product']) };
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
        restoreWorkspacePreviewContext: methods.restoreWorkspacePreviewContext,
        restoreWorkspaceSalesChannel: methods.restoreWorkspaceSalesChannel,
        loadPreviewLanguageContext: methods.loadPreviewLanguageContext,
        applyPreviewLanguageContext: methods.applyPreviewLanguageContext,
        previewLanguageContext: null,
        defaultSalesChannelCriteria: getStorefrontSalesChannelCriteria(1),
        salesChannelRepository: {
            search: jest.fn().mockResolvedValue({ first: () => ({ id: 'channel' }) }),
            get: jest.fn((id: string) =>
                Promise.resolve({
                    id,
                    languageId: Shopware.Defaults.systemLanguageId,
                    languages: [{ id: Shopware.Defaults.systemLanguageId, parentId: null, active: true }],
                }),
            ),
        },
        createOpenLayoutState: methods.createOpenLayoutState,
        createPreviewFrame: methods.createPreviewFrame,
        serializeLayoutState: methods.serializeLayoutState,
        availableLayoutTypes: [] as string[],
        layoutTypeLoadError: null as string | null,
        layoutTypesRequest: null as Promise<void> | null,
        isLoadingLayoutTypes: false,
        ensureLayoutTypesLoaded: methods.ensureLayoutTypesLoaded,
        loadLayoutTypes: methods.loadLayoutTypes,
        entityTypeService: () => entityTypeService,
        getLayoutRootSource: methods.getLayoutRootSource,
        getFirstAssociationEntry: methods.getFirstAssociationEntry,
        resolveAssignedPreviewContext: methods.resolveAssignedPreviewContext,
        resolvePreviewContext: methods.resolvePreviewContext,
        resetWorkspaceEditor: jest.fn(),
        activateFrame: jest.fn(),
        openLayouts: [{ id: 'current-layout' }],
        frames: [
            {
                id: 'current-frame',
                layoutId: 'current-layout',
                previewEntityId: 'current-entity',
                languageId: Shopware.Defaults.systemLanguageId,
            },
        ],
        workspaceCanvasConfiguration: current.configuration,
        previewSalesChannelId: 'current-channel',
        workspaceError: false,
        isWorkspacePickerOpen: false,
        isLoading: false,
        isRestoringWorkspace: false,
        allowSave: true,
        activeFrameId: 'layout-a',
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

    return { editor, current, target, entityRepository, entityTypeService };
}
