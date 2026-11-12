import type { NavigationGuardNext, RouteLocationNormalized } from 'vue-router';
import type { ContentWorkspace, WorkspaceConfiguration } from '../../types/workspace.types';
import type { ExperienceStudioWorkspaceStore } from '../../store/experience-studio-workspace.store';
import { createWorkspaceConfiguration, copyWorkspaceConfiguration } from '../../util/workspace-configuration.util';
import type Repository from 'src/core/data/repository.data';
import type { ContentSystemElementTypeSpecification } from 'src/core/service/api/content-system-element-type.api.service';
import type {
    ContentLayoutDiagnosePayload,
    ContentLayoutDiagnoseResponse,
    ContentLayoutDraftDuplicatePayload,
    ContentLayoutDraftInsertPayload,
    ContentLayoutDraftInsertPresetPayload,
    ContentLayoutDraftMovePayload,
    ContentLayoutDraftMapPropertyPayload,
    ContentLayoutDraftMutationResponse,
    ContentLayoutDraftRemovePayload,
    ContentLayoutDraftUnmapPropertyPayload,
    ContentSystemViolation,
} from 'src/core/service/api/content-system-layout-draft-mutation.api.service';
import type { ContentSystemLayoutPreset } from 'src/core/service/api/content-system-layout-preset.api.service';
import type { ExperienceStudioElementTypeStore } from 'src/module/sw-experience-studio/store/experience-studio-element-type.store';
import type { ExperienceStudioLayoutPresetStore } from 'src/module/sw-experience-studio/store/experience-studio-layout-preset.store';
import type { ExperienceStudioMappingCandidateStore } from 'src/module/sw-experience-studio/store/experience-studio-mapping-candidate.store';
import type { ExperienceStudioStyleOptionStore } from 'src/module/sw-experience-studio/store/experience-studio-style-option.store';
import type { ContentSystemMappingCandidate } from 'src/core/service/api/content-system-mapping-candidate.api.service';

import type { ContentElementNode } from 'src/core/service/content-element.types';
import { getStorefrontSalesChannelCriteria } from 'src/module/sw-experience-studio/util/sales-channel-criteria.util';
import type {
    ContentLayoutEntity,
    ContentLayoutRepository,
} from 'src/module/sw-experience-studio/util/content-layout-repository.util';
import { createContentLayoutRepository } from 'src/module/sw-experience-studio/util/content-layout-repository.util';
import { getPropertyControlType } from 'src/module/sw-experience-studio/util/element-settings.util';
import {
    findElementLocation,
    updateElementPropertiesInLayout,
    updateElementStyleInLayout,
} from 'src/module/sw-experience-studio/util/content-element.util';
import 'src/module/sw-experience-studio/store/experience-studio-editor.store';
import 'src/module/sw-experience-studio/store/experience-studio-element-type.store';
import 'src/module/sw-experience-studio/store/experience-studio-layout-preset.store';
import 'src/module/sw-experience-studio/store/experience-studio-mapping-candidate.store';
import 'src/module/sw-experience-studio/store/experience-studio-style-option.store';
import template from './sw-experience-studio-detail.html.twig';
import './sw-experience-studio-detail.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { cloneDeep } = Shopware.Utils.object;
const DIAGNOSE_DEBOUNCE_MS = 800;
const MUTATION_ERROR_SNIPPETS: Record<string, string> = {
    CONTENT_SYSTEM__PROPERTY_NOT_MAPPABLE: 'sw-experience-studio.detail.mappingMutationErrors.propertyNotMappable',
    CONTENT_SYSTEM__UNKNOWN_MAPPING_PATH: 'sw-experience-studio.detail.mappingMutationErrors.unknownMappingPath',
    CONTENT_SYSTEM__MAPPING_TYPE_MISMATCH: 'sw-experience-studio.detail.mappingMutationErrors.mappingTypeMismatch',
    CONTENT_SYSTEM__PROPERTY_ALIAS_COLLISION: 'sw-experience-studio.detail.mappingMutationErrors.propertyAliasCollision',
    CONTENT_SYSTEM__INVALID_MAPPING_SOURCE: 'sw-experience-studio.detail.mappingMutationErrors.invalidMappingSource',
    CONTENT_SYSTEM__UNKNOWN_ROOT_SOURCE: 'sw-experience-studio.detail.mutationErrors.unknownRootSource',
    CONTENT_SYSTEM__MUTATION_TARGET_NOT_FOUND: 'sw-experience-studio.detail.mutationErrors.targetNotFound',
    CONTENT_SYSTEM__MUTATION_UNKNOWN_TYPE: 'sw-experience-studio.detail.mutationErrors.unknownElementType',
    CONTENT_SYSTEM__MUTATION_CYCLE: 'sw-experience-studio.detail.mutationErrors.invalidStructure',
    CONTENT_SYSTEM__MUTATION_SLOT_REQUIRED: 'sw-experience-studio.detail.mutationErrors.invalidStructure',
    CONTENT_SYSTEM__MUTATION_INVALID_WRAP_TARGETS: 'sw-experience-studio.detail.mutationErrors.invalidStructure',
    CONTENT_SYSTEM__INVALID_LAYOUT_STRUCTURE: 'sw-experience-studio.detail.mutationErrors.invalidStructure',
};

type WorkspaceAction = { type: 'open'; id: string } | { type: 'create'; name: string } | { type: 'delete'; id: string };

type Viewport = 'mobile' | 'tablet-landscape' | 'desktop' | 'custom';

type LayoutMutationResult =
    | false
    | {
          selectedElementId?: string | null;
      };

type AddElementPayload = {
    parentElementId: string | null;
    slotName: string | null;
    anchorElement: HTMLElement | null;
};

type ElementPickerItem = {
    name: string;
    label: string;
    icon: string | null;
    illustration?: string | null;
    category: string | null;
    priority?: number;
    kind?: 'element' | 'preset';
    id?: string;
    description?: string | null;
};

type MoveElementPayload = {
    elementId: string;
    newParentElementId: string | null;
    newSlotName: string | null;
    newIndex: number | null;
};

type LayoutTypeOption = {
    value: string;
    label: string;
    icon: string;
};

type LayoutPreviewContext = {
    entityType: string;
    entityId: string | null;
    salesChannelId: string | null;
};

type ExperienceStudioOpenLayout = {
    id: string;
    layout: ContentLayoutEntity;
    selectedElementId: string | null;
    viewport: Viewport;
    previewEntityId: string | null;
    settingsWidth: number;
    savedState: string;
    isNew: boolean;
    isSaving: boolean;
    saveError: string | null;
};

type ExperienceStudioCanvasFrame = {
    id: string;
    layout: ContentLayoutEntity;
    selectedElementId: string | null;
    viewport: Viewport;
    previewEntityId: string | null;
    isActive: boolean;
};

type ExperienceStudioCanvasRef = {
    openSettingsPanel: () => void;
    closeSettingsPanel: () => void;
    fitCanvasToFrame: () => void;
    fitCanvasToFrames: () => void;
    getWorkspaceConfiguration: () => WorkspaceConfiguration;
};

type DraftMutationOperation =
    | 'insert'
    | 'remove'
    | 'duplicate'
    | 'move'
    | 'insert-preset'
    | 'map-property'
    | 'unmap-property';

type ContentSystemLayoutDraftMutationService = {
    insertElement: (payload: ContentLayoutDraftInsertPayload) => Promise<ContentLayoutDraftMutationResponse>;
    removeElement: (payload: ContentLayoutDraftRemovePayload) => Promise<ContentLayoutDraftMutationResponse>;
    duplicateElement: (payload: ContentLayoutDraftDuplicatePayload) => Promise<ContentLayoutDraftMutationResponse>;
    moveElement: (payload: ContentLayoutDraftMovePayload) => Promise<ContentLayoutDraftMutationResponse>;
    insertPreset: (payload: ContentLayoutDraftInsertPresetPayload) => Promise<ContentLayoutDraftMutationResponse>;
    mapProperty: (payload: ContentLayoutDraftMapPropertyPayload) => Promise<ContentLayoutDraftMutationResponse>;
    unmapProperty: (payload: ContentLayoutDraftUnmapPropertyPayload) => Promise<ContentLayoutDraftMutationResponse>;
    diagnose: (payload: ContentLayoutDiagnosePayload) => Promise<ContentLayoutDiagnoseResponse>;
};

type ContentSystemEntityTypeService = {
    getEntityTypes: () => Promise<string[]>;
};

const DEFAULT_ELEMENT_SETTINGS_WIDTH = 320;
const MIN_ELEMENT_SETTINGS_WIDTH = 320;
const MAX_ELEMENT_SETTINGS_WIDTH = 800;
const RICH_TEXT_ELEMENT_SETTINGS_WIDTH = 640;
const ELEMENT_SETTINGS_RESIZE_STEP = 20;
const MIN_PREVIEW_WIDTH = 320;

type ExperienceStudioDetailState = {
    isWorkspacePickerOpen: boolean;
    workspaceNameAction: 'create' | 'rename' | null;
    workspaceName: string;
    managedWorkspace: ContentWorkspace | null;
    isDeleteWorkspaceModalOpen: boolean;
    pendingWorkspaceAction: WorkspaceAction | null;
    workspaceCanvasConfiguration: WorkspaceConfiguration | null;
    isRestoringWorkspace: boolean;
    isWorkspaceTransitioning: boolean;
    workspaceError: boolean;
    pendingLinkedLayoutId: string | null;
    layout: ContentLayoutEntity | null;
    openLayouts: ExperienceStudioOpenLayout[];
    activeLayoutId: string | null;
    isLayoutPickerOpen: boolean;
    isCreateLayoutModalOpen: boolean;
    pendingCloseLayoutId: string | null;
    isSavingAll: boolean;
    isLoading: boolean;
    isSaveSuccessful: boolean;
    currentViewport: Viewport;
    selectedElementId: string | null;
    previewSalesChannelId: string | null;
    previewEntityId: string | null;
    historyKeydownHandler: ((event: KeyboardEvent) => void) | null;
    isElementPickerOpen: boolean;
    pendingAddElementPayload: AddElementPayload | null;
    pickerAnchorElement: HTMLElement | null;
    pickerTop: number;
    pickerLeft: number;
    mutationQueue: Promise<void>;
    pendingMutationCount: number;
    diagnostics: ContentSystemViolation[];
    showDiagnostics: boolean;
    diagnoseRequestSequence: number;
    latestDiagnoseRequestId: number;
    diagnoseTimeoutId: number | null;
    availableLayoutTypes: string[];
    isLoadingLayoutTypes: boolean;
    layoutTypeLoadError: string | null;
    createWizardName: string;
    createWizardSelectedType: string | null;
    isAssignmentModalOpen: boolean;
    assignmentLayoutId: string | null;
    elementSettingsWidth: number;
    isResizingElementSettings: boolean;
    resizeStartX: number;
    resizeStartWidth: number;
    resizeMoveHandler: ((event: PointerEvent) => void) | null;
    resizeEndHandler: (() => void) | null;
    resizeHandle: HTMLElement | null;
    resizePointerId: number | null;
};

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    data(): ExperienceStudioDetailState {
        return {
            isWorkspacePickerOpen: false,
            workspaceNameAction: null,
            workspaceName: '',
            managedWorkspace: null,
            isDeleteWorkspaceModalOpen: false,
            pendingWorkspaceAction: null,
            workspaceCanvasConfiguration: null,
            isRestoringWorkspace: false,
            isWorkspaceTransitioning: false,
            workspaceError: false,
            pendingLinkedLayoutId: null,
            layout: null,
            openLayouts: [],
            activeLayoutId: null,
            isLayoutPickerOpen: false,
            isCreateLayoutModalOpen: false,
            pendingCloseLayoutId: null,
            isSavingAll: false,
            isLoading: false,
            isSaveSuccessful: false,
            currentViewport: 'desktop',
            selectedElementId: null,
            previewSalesChannelId: null,
            previewEntityId: null,
            historyKeydownHandler: null,
            isElementPickerOpen: false,
            pendingAddElementPayload: null,
            pickerAnchorElement: null,
            pickerTop: 0,
            pickerLeft: 0,
            mutationQueue: Promise.resolve(),
            pendingMutationCount: 0,
            diagnostics: [],
            showDiagnostics: false,
            diagnoseRequestSequence: 0,
            latestDiagnoseRequestId: 0,
            diagnoseTimeoutId: null,
            availableLayoutTypes: [],
            isLoadingLayoutTypes: false,
            layoutTypeLoadError: null,
            createWizardName: '',
            createWizardSelectedType: null,
            isAssignmentModalOpen: false,
            assignmentLayoutId: null,
            elementSettingsWidth: DEFAULT_ELEMENT_SETTINGS_WIDTH,
            isResizingElementSettings: false,
            resizeStartX: 0,
            resizeStartWidth: DEFAULT_ELEMENT_SETTINGS_WIDTH,
            resizeMoveHandler: null,
            resizeEndHandler: null,
            resizeHandle: null,
            resizePointerId: null,
        };
    },

    computed: {
        workspaceStore(): ExperienceStudioWorkspaceStore {
            return Shopware.Store.get('experienceStudioWorkspace' as never) as ExperienceStudioWorkspaceStore;
        },
        layoutRepository(): ContentLayoutRepository {
            return createContentLayoutRepository(this.repositoryFactory.create('content_layout'));
        },

        salesChannelRepository(): Repository<'sales_channel'> {
            return this.repositoryFactory.create('sales_channel');
        },

        defaultSalesChannelCriteria() {
            return getStorefrontSalesChannelCriteria(1);
        },

        defaultPreviewEntityCriteria() {
            return new Criteria(1, 1);
        },

        layoutId(): string {
            return this.activeLayoutId ?? '';
        },

        changedLayouts(): Array<{ id: string; name: string }> {
            return this.openLayouts
                .filter((openLayout) => this.isLayoutDirty(openLayout))
                .map((openLayout) => ({ id: openLayout.id, name: openLayout.layout.name ?? '' }));
        },

        openLayoutColumns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-experience-studio.workspace.layouts.title'),
                    sortable: false,
                    allowResize: false,
                },
            ];
        },

        canvasFrames(): ExperienceStudioCanvasFrame[] {
            return this.openLayouts.map((openLayout) => ({
                id: openLayout.id,
                layout: openLayout.layout,
                selectedElementId:
                    openLayout.id === this.activeLayoutId ? this.selectedElementId : openLayout.selectedElementId,
                viewport: openLayout.id === this.activeLayoutId ? this.currentViewport : openLayout.viewport,
                previewEntityId: openLayout.id === this.activeLayoutId ? this.previewEntityId : openLayout.previewEntityId,
                isActive: openLayout.id === this.activeLayoutId,
            }));
        },

        layoutPickerOpenIds(): string[] {
            return this.openLayouts.map((openLayout) => openLayout.id);
        },

        assignmentLayout(): ExperienceStudioOpenLayout | null {
            return this.openLayouts.find((openLayout) => openLayout.id === this.assignmentLayoutId) ?? null;
        },

        assignmentRootSource(): string | null {
            return this.assignmentLayout ? this.getLayoutRootSource(this.assignmentLayout.layout) : null;
        },

        layoutLoadCriteria() {
            const criteria = new Criteria(1, 1);

            criteria.addAssociation('productContentLayouts');
            criteria.addAssociation('categoryContentLayouts');
            criteria.addAssociation('landingPageContentLayouts');

            return criteria;
        },

        resolvedPreviewContext(): LayoutPreviewContext | null {
            return this.resolvePreviewContext(this.layout);
        },

        previewEntityType(): LayoutPreviewContext['entityType'] | null {
            return this.resolvedPreviewContext?.entityType ?? null;
        },

        allowSave(): boolean {
            return this.acl.can('experience_studio.editor');
        },

        layoutTypeOptions(): LayoutTypeOption[] {
            return this.availableLayoutTypes.map((entityType) => {
                const snippetKey = `sw-experience-studio.createWizard.layoutTypes.${entityType}`;

                return {
                    value: entityType,
                    label: this.$te(snippetKey) ? this.$t(snippetKey) : entityType,
                    icon: this.getLayoutTypeIcon(entityType),
                };
            });
        },

        editorStore() {
            return Shopware.Store.get('experienceStudioEditor');
        },

        elementTypeStore() {
            return Shopware.Store.get('experienceStudioElementType' as never) as ExperienceStudioElementTypeStore;
        },

        styleOptionStore() {
            return Shopware.Store.get('experienceStudioStyleOption' as never) as ExperienceStudioStyleOptionStore;
        },

        layoutPresetStore() {
            return Shopware.Store.get('experienceStudioLayoutPreset' as never) as ExperienceStudioLayoutPresetStore;
        },

        mappingCandidateStore() {
            return Shopware.Store.get('experienceStudioMappingCandidate' as never) as ExperienceStudioMappingCandidateStore;
        },

        mappingCandidates(): ContentSystemMappingCandidate[] {
            return this.mappingCandidateStore.getByRootSource(this.getLayoutRootSource(this.layout));
        },

        canUndo(): boolean {
            return this.editorStore.canUndo;
        },

        canRedo(): boolean {
            return this.editorStore.canRedo;
        },

        selectedElement(): ContentElementNode | null {
            if (!this.layout || !this.selectedElementId) {
                return null;
            }

            const layoutElements = this.layout.layout;
            const location = findElementLocation(layoutElements, this.selectedElementId);

            if (!location) {
                return null;
            }

            return location.elements[location.index] ?? null;
        },

        selectedElementType(): ContentSystemElementTypeSpecification | null {
            if (!this.selectedElement) {
                return null;
            }

            return this.elementTypeStore.getByName(this.selectedElement.component);
        },

        selectedElementHasRichText(): boolean {
            return Object.values(this.selectedElementType?.properties ?? {}).some(
                (property) => getPropertyControlType(property) === 'richtext',
            );
        },

        availablePickerElements(): ElementPickerItem[] {
            const payload = this.pendingAddElementPayload;
            const availableTypes = this.getAvailableTypesForPayload(payload);

            const elementItems: ElementPickerItem[] = availableTypes.map((typeSpecification) => ({
                name: typeSpecification.name,
                label: typeSpecification.label,
                icon: typeSpecification.icon,
                illustration: typeSpecification.illustration,
                category: typeSpecification.category,
                priority: typeSpecification.priority,
                kind: 'element',
            }));

            if (!payload) {
                return elementItems;
            }

            const allowedComponents = new Set(availableTypes.map((typeSpecification) => typeSpecification.name));

            const presetItems: ElementPickerItem[] = this.layoutPresetStore.allPresets
                .filter((preset) => this.isPresetAllowedForPayload(preset, payload, allowedComponents))
                .map((preset) => ({
                    name: preset.id,
                    label: preset.name,
                    icon: preset.icon,
                    illustration: preset.illustration,
                    category: preset.category,
                    priority: preset.priority,
                    kind: 'preset',
                    id: preset.id,
                    description: preset.description,
                }));

            return [
                ...elementItems,
                ...presetItems,
            ];
        },

        selectedElementViolations(): ContentSystemViolation[] {
            if (!this.showDiagnostics || this.selectedElementId === null) {
                return [];
            }

            return this.diagnostics.filter((violation) => violation.elementId === this.selectedElementId);
        },
    },

    watch: {
        'workspaceStore.workspaceMissing'(missing: boolean): void {
            if (missing) {
                this.isWorkspacePickerOpen = true;
            }
        },
        layoutPickerOpenIds(): void {
            void this.scheduleWorkspaceSetup();
        },
        previewSalesChannelId(): void {
            void this.scheduleWorkspaceSetup();
        },
        previewEntityId(): void {
            void this.scheduleWorkspaceSetup();
        },
        currentViewport(): void {
            void this.scheduleWorkspaceSetup();
        },
        selectedElementId(): void {
            this.adjustElementSettingsWidth();
        },

        selectedElementType(): void {
            this.adjustElementSettingsWidth();
        },
    },

    created(): void {
        Shopware.Store.get('adminMenu').collapseSidebar();
        this.historyKeydownHandler = (event: KeyboardEvent): void => {
            this.onHistoryKeydown(event);
        };
        this.pendingLinkedLayoutId = (this.$route.params.id as string | undefined) ?? null;
        const workspaceId = this.$route.params.workspaceId as string | undefined;
        if (workspaceId) {
            void this.openWorkspace(workspaceId);
        } else {
            this.workspaceStore.activate(null);
            this.isWorkspacePickerOpen = true;
        }
        void this.loadElementTypes();
        void this.loadStyleOptions();
        void this.loadLayoutPresets();
        void this.loadLayoutTypes();
        void this.loadMappingCandidates();
    },

    mounted(): void {
        if (this.historyKeydownHandler) {
            document.addEventListener('keydown', this.historyKeydownHandler);
        }
    },

    beforeRouteLeave(_to: RouteLocationNormalized, _from: RouteLocationNormalized, next: NavigationGuardNext): void {
        void this.scheduleWorkspaceSetup()
            .then(() => this.workspaceStore.flush())
            .then(
                (saved) => next(saved ? undefined : false),
                () => next(false),
            );
    },

    beforeRouteUpdate(to: RouteLocationNormalized): boolean {
        const id = to.params.workspaceId as string | undefined;
        if (id && id !== this.workspaceStore.activeWorkspace?.id) {
            void this.requestWorkspaceAction({ type: 'open', id });
            return false;
        }
        const layoutId = to.params.id as string | undefined;
        if (to.name === 'sw.experience.studio.detail' && layoutId && this.workspaceStore.activeWorkspace) {
            void this.openLayoutById(layoutId);
            return false;
        }
        return true;
    },

    beforeUnmount(): void {
        void this.scheduleWorkspaceSetup().then(() => this.workspaceStore.flush());
        if (this.diagnoseTimeoutId !== null) {
            window.clearTimeout(this.diagnoseTimeoutId);
        }

        if (this.historyKeydownHandler) {
            document.removeEventListener('keydown', this.historyKeydownHandler);
        }

        this.stopElementSettingsResize();

        this.editorStore.reset();
    },

    methods: {
        async scheduleWorkspaceSetup(): Promise<void> {
            const workspaceId = this.workspaceStore?.activeWorkspace?.id;
            if (!workspaceId || this.isRestoringWorkspace) {
                return;
            }
            await this.$nextTick();
            if (workspaceId !== this.workspaceStore.activeWorkspace?.id || this.isRestoringWorkspace) {
                return;
            }
            const canvas = this.$refs.experienceStudioCanvas as ExperienceStudioCanvasRef | undefined;
            if (!canvas) {
                return;
            }
            const configuration = canvas.getWorkspaceConfiguration();
            configuration.previewSalesChannelId = this.previewSalesChannelId;
            configuration.frames = this.openLayouts
                .filter((item) => !item.isNew)
                .flatMap((item) => {
                    const frame = configuration.frames.find((candidate) => candidate.layoutId === item.id);
                    return frame
                        ? [
                              {
                                  ...frame,
                                  viewport: item.id === this.activeLayoutId ? this.currentViewport : item.viewport,
                                  previewEntityId:
                                      item.id === this.activeLayoutId ? this.previewEntityId : item.previewEntityId,
                              },
                          ]
                        : [];
                });
            this.workspaceStore.schedule(configuration);
        },

        async openWorkspace(id: string): Promise<void> {
            this.isLoading = true;
            this.workspaceError = false;
            try {
                const workspace = await this.workspaceStore.get(id);
                const configuration = copyWorkspaceConfiguration(workspace.configuration);
                const { openLayouts, missingReferences } = await this.restoreWorkspaceLayouts(configuration);
                const { salesChannelId, missingReference: missingSalesChannel } = await this.restoreWorkspaceSalesChannel(
                    configuration.previewSalesChannelId,
                );
                this.isRestoringWorkspace = true;
                this.resetWorkspaceEditor();
                this.workspaceCanvasConfiguration = configuration;
                this.workspaceStore.activate(workspace);
                this.openLayouts = openLayouts;
                this.previewSalesChannelId = salesChannelId;
                if (openLayouts[0]) {
                    this.activateLayout(openLayouts[0].id);
                }
                this.isWorkspacePickerOpen = false;
                await this.$nextTick();
                this.isRestoringWorkspace = false;
                await this.$router.replace({ name: 'sw.experience.studio.workspace', params: { workspaceId: id } });
                if (missingReferences || missingSalesChannel) {
                    this.createNotificationWarning({
                        message: this.$t('sw-experience-studio.workspace.manager.missingReferences'),
                    });
                }
                if (this.pendingLinkedLayoutId) {
                    const linkedId = this.pendingLinkedLayoutId;
                    this.pendingLinkedLayoutId = null;
                    await this.openLayoutById(linkedId);
                }
            } catch {
                this.workspaceError = true;
                if (!this.workspaceStore.activeWorkspace) {
                    this.isWorkspacePickerOpen = true;
                }
                this.createNotificationError({ message: this.$t('sw-experience-studio.workspace.manager.loadError') });
            } finally {
                this.isLoading = false;
                this.isRestoringWorkspace = false;
            }
        },

        async restoreWorkspaceLayouts(configuration: WorkspaceConfiguration): Promise<{
            openLayouts: ExperienceStudioOpenLayout[];
            missingReferences: boolean;
        }> {
            const criteria = this.layoutLoadCriteria;
            const ids = configuration.frames.map((frame) => frame.layoutId);
            criteria.setIds(ids);
            criteria.setLimit(Math.max(1, ids.length));
            const layouts = ids.length ? await this.layoutRepository.search(criteria, Shopware.Context.api) : [];
            const byId = new Map<string, ContentLayoutEntity>(
                layouts.map((layout) => [
                    layout.id,
                    layout,
                ]),
            );
            const openLayouts = configuration.frames.flatMap((frame) => {
                const layout = byId.get(frame.layoutId);
                return layout
                    ? [
                          {
                              ...this.createOpenLayoutState(layout),
                              viewport: frame.viewport,
                              previewEntityId: frame.previewEntityId,
                          },
                      ]
                    : [];
            });
            let missingReferences = openLayouts.length !== configuration.frames.length;
            for (const item of openLayouts) {
                if (await this.restoreFramePreviewEntity(item)) {
                    missingReferences = true;
                }
            }
            return { openLayouts, missingReferences };
        },

        async restoreFramePreviewEntity(item: ExperienceStudioOpenLayout): Promise<boolean> {
            let missingReference = false;
            const entityType = this.resolvePreviewContext(item.layout)?.entityType;
            if (
                !entityType ||
                [
                    'none',
                    'header',
                    'footer',
                ].includes(entityType)
            ) {
                item.previewEntityId = null;
                return false;
            }
            const entityRepository = this.repositoryFactory.create(entityType as keyof EntitySchema.EntityKeys);
            if (item.previewEntityId) {
                const entityCriteria = new Criteria(1, 1);
                entityCriteria.setIds([item.previewEntityId]);
                const found = await entityRepository.searchIds(entityCriteria, Shopware.Context.api);
                if (!found.data.includes(item.previewEntityId)) {
                    item.previewEntityId = null;
                    missingReference = true;
                }
            }
            if (!item.previewEntityId) {
                const defaults = await entityRepository.search(new Criteria(1, 1), Shopware.Context.api);
                item.previewEntityId = defaults.first()?.id ?? null;
            }

            return missingReference;
        },

        async restoreWorkspaceSalesChannel(savedSalesChannelId: string | null): Promise<{
            salesChannelId: string | null;
            missingReference: boolean;
        }> {
            let missingReference = false;
            let salesChannelId = savedSalesChannelId;
            if (salesChannelId) {
                const channelCriteria = getStorefrontSalesChannelCriteria(1);
                channelCriteria.addFilter(Criteria.equals('id', salesChannelId));
                const channels = await this.salesChannelRepository.search(channelCriteria, Shopware.Context.api);
                if (!channels.length) {
                    salesChannelId = null;
                    missingReference = true;
                }
            }
            if (!salesChannelId) {
                const channels = await this.salesChannelRepository.search(
                    this.defaultSalesChannelCriteria,
                    Shopware.Context.api,
                );
                salesChannelId = channels.first()?.id ?? null;
            }
            return { salesChannelId, missingReference };
        },

        resetWorkspaceEditor(): void {
            if (this.diagnoseTimeoutId !== null) {
                window.clearTimeout(this.diagnoseTimeoutId);
            }
            this.diagnoseTimeoutId = null;
            this.latestDiagnoseRequestId = ++this.diagnoseRequestSequence;
            this.editorStore.reset();
            this.openLayouts = [];
            this.activeLayoutId = null;
            this.layout = null;
            this.selectedElementId = null;
            this.currentViewport = 'desktop';
            this.previewEntityId = null;
            this.diagnostics = [];
            this.showDiagnostics = false;
            this.isLayoutPickerOpen = false;
            this.isElementPickerOpen = false;
            this.isAssignmentModalOpen = false;
            this.pendingCloseLayoutId = null;
        },

        async requestWorkspaceAction(action: WorkspaceAction): Promise<void> {
            if (
                this.isWorkspaceTransitioning ||
                this.isSavingAll ||
                this.isLoading ||
                this.openLayouts.some((item) => item.isSaving)
            ) {
                return;
            }
            if (action.type === 'open' && action.id === this.workspaceStore?.activeWorkspace?.id) {
                return;
            }
            if (action.type === 'delete' && action.id !== this.workspaceStore.activeWorkspace?.id) {
                await this.executeWorkspaceAction(action);
                return;
            }
            this.isWorkspaceTransitioning = true;
            try {
                await this.mutationQueue;
                this.syncActiveLayoutState();
                if (this.openLayouts.some((item) => this.isLayoutDirty(item))) {
                    this.pendingWorkspaceAction = action;
                    return;
                }
                await this.executeWorkspaceAction(action);
            } finally {
                this.isWorkspaceTransitioning = false;
            }
        },

        async confirmWorkspaceAction(save: boolean): Promise<void> {
            const action = this.pendingWorkspaceAction;
            if (!action || this.isWorkspaceTransitioning) {
                return;
            }
            if (save && !(await this.saveAllOpenLayouts())) {
                return;
            }
            await this.executeWorkspaceAction(action);
        },

        cancelWorkspaceAction(): void {
            if (this.pendingWorkspaceAction?.type === 'delete') {
                this.managedWorkspace = null;
                this.isWorkspacePickerOpen = true;
            }
            this.pendingWorkspaceAction = null;
        },

        async executeWorkspaceAction(action: WorkspaceAction): Promise<void> {
            this.isWorkspaceTransitioning = true;
            try {
                const deletingInactiveWorkspace =
                    action.type === 'delete' && action.id !== this.workspaceStore.activeWorkspace?.id;
                if (!deletingInactiveWorkspace) {
                    await this.scheduleWorkspaceSetup();
                    if (!(await this.workspaceStore.flush())) {
                        return;
                    }
                }
                if (action.type === 'delete') {
                    await this.workspaceStore.remove(action.id);
                    if (!deletingInactiveWorkspace) {
                        this.resetWorkspaceEditor();
                        this.workspaceCanvasConfiguration = createWorkspaceConfiguration();
                        await this.$router.replace({ name: 'sw.experience.studio.index' });
                    }
                    this.isWorkspacePickerOpen = true;
                } else {
                    const id = action.type === 'create' ? (await this.workspaceStore.create(action.name)).id : action.id;
                    await this.openWorkspace(id);
                    if (this.workspaceError) {
                        return;
                    }
                }
                this.pendingWorkspaceAction = null;
                this.workspaceNameAction = null;
                this.isDeleteWorkspaceModalOpen = false;
                this.managedWorkspace = null;
            } catch {
                if (action.type === 'delete') {
                    this.isWorkspacePickerOpen = true;
                }
                this.createNotificationError({ message: this.$t('sw-experience-studio.workspace.manager.actionError') });
            } finally {
                this.isWorkspaceTransitioning = false;
            }
        },

        onWorkspaceRename(workspace: ContentWorkspace): void {
            this.isWorkspacePickerOpen = false;
            this.managedWorkspace = workspace;
            this.workspaceName = workspace.name;
            this.workspaceNameAction = 'rename';
        },

        onWorkspaceDelete(workspace: ContentWorkspace): void {
            this.isWorkspacePickerOpen = false;
            this.managedWorkspace = workspace;
            this.isDeleteWorkspaceModalOpen = true;
        },

        onCancelWorkspaceName(): void {
            this.isWorkspacePickerOpen = this.workspaceNameAction === 'rename' || !this.workspaceStore.activeWorkspace;
            this.workspaceNameAction = null;
            this.managedWorkspace = null;
        },

        onCancelWorkspaceDelete(): void {
            this.isDeleteWorkspaceModalOpen = false;
            this.managedWorkspace = null;
            this.isWorkspacePickerOpen = true;
        },

        async onWorkspaceDeleteConfirm(): Promise<void> {
            if (!this.managedWorkspace) {
                return;
            }
            const id = this.managedWorkspace.id;
            this.isDeleteWorkspaceModalOpen = false;
            await this.requestWorkspaceAction({ type: 'delete', id });
        },

        async onWorkspaceNameSubmit(): Promise<void> {
            const name = this.workspaceName.trim();
            if (!name || name.length > 255 || this.isWorkspaceTransitioning) {
                return;
            }
            if (this.workspaceNameAction === 'create') {
                await this.requestWorkspaceAction({ type: 'create', name });
                return;
            }
            const id = this.managedWorkspace?.id;
            if (!id) {
                return;
            }
            this.isWorkspaceTransitioning = true;
            try {
                await this.workspaceStore.rename(id, name);
                this.onCancelWorkspaceName();
            } catch {
                this.createNotificationError({ message: this.$t('sw-experience-studio.workspace.manager.actionError') });
            } finally {
                this.isWorkspaceTransitioning = false;
            }
        },

        onFrameUndo(layoutId: string): void {
            if (!this.allowSave || !this.editorStore.canUndoLayout(layoutId)) {
                return;
            }
            this.activateLayout(layoutId);
            this.onUndo();
            this.syncActiveLayoutState();
        },

        onFrameRedo(layoutId: string): void {
            if (!this.allowSave || !this.editorStore.canRedoLayout(layoutId)) {
                return;
            }
            this.activateLayout(layoutId);
            this.onRedo();
            this.syncActiveLayoutState();
        },

        createOpenLayoutState(layout: ContentLayoutEntity, isNew = false): ExperienceStudioOpenLayout {
            return {
                id: layout.id,
                layout,
                selectedElementId: null,
                viewport: 'desktop',
                previewEntityId: null,
                settingsWidth: DEFAULT_ELEMENT_SETTINGS_WIDTH,
                savedState: this.serializeLayoutState(layout),
                isNew,
                isSaving: false,
                saveError: null,
            };
        },

        serializeLayoutState(layout: ContentLayoutEntity): string {
            return JSON.stringify({
                name: layout.name,
                rootSource: this.getLayoutRootSource(layout),
                layout: layout.layout,
            });
        },

        isLayoutDirty(openLayout: ExperienceStudioOpenLayout): boolean {
            return openLayout.isNew || this.serializeLayoutState(openLayout.layout) !== openLayout.savedState;
        },

        syncActiveLayoutState(): void {
            const openLayout = this.openLayouts.find((item) => item.id === this.activeLayoutId);

            if (!openLayout) {
                return;
            }

            openLayout.layout = this.layout ?? openLayout.layout;
            openLayout.selectedElementId = this.selectedElementId;
            openLayout.viewport = this.currentViewport;
            openLayout.previewEntityId = this.previewEntityId;
            openLayout.settingsWidth = this.elementSettingsWidth;
        },

        async openLayoutById(layoutId: string): Promise<void> {
            const existingLayout = this.openLayouts.find((item) => item.id === layoutId);

            if (existingLayout) {
                this.activateLayout(layoutId);
                this.isLayoutPickerOpen = false;

                return;
            }

            this.isLoading = true;

            try {
                const layout = await this.layoutRepository.get(layoutId, Shopware.Context.api, this.layoutLoadCriteria);

                if (!layout) {
                    return;
                }

                const isFirstOpenLayout = this.openLayouts.length === 0;
                this.openLayouts.push(this.createOpenLayoutState(layout));
                this.activateLayout(layout.id);
                this.applyPreviewContextDefaults();
                await this.loadDefaultPreviewEntity();
                this.syncActiveLayoutState();
                this.isLayoutPickerOpen = false;
                await this.$nextTick();
                const canvas = this.$refs.experienceStudioCanvas as ExperienceStudioCanvasRef | undefined;

                if (isFirstOpenLayout) {
                    canvas?.fitCanvasToFrame();
                } else {
                    canvas?.fitCanvasToFrames();
                }
                void this.diagnoseLayout();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-experience-studio.workspace.layoutPicker.loadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        activateLayout(layoutId: string): void {
            const openLayout = this.openLayouts.find((item) => item.id === layoutId);

            if (!openLayout || this.activeLayoutId === layoutId) {
                return;
            }

            this.selectedElementId = null;
            this.syncActiveLayoutState();
            openLayout.selectedElementId = null;
            this.activeLayoutId = layoutId;
            this.layout = openLayout.layout;
            this.currentViewport = openLayout.viewport;
            this.previewEntityId = openLayout.previewEntityId;
            this.elementSettingsWidth = openLayout.settingsWidth;
            const canvas = this.$refs.experienceStudioCanvas as ExperienceStudioCanvasRef | undefined;
            canvas?.closeSettingsPanel();
            this.editorStore.initialize(layoutId);
            this.showDiagnostics = false;
            void this.diagnoseLayout();
        },

        openLayoutPicker(): void {
            this.isLayoutPickerOpen = true;
        },

        onLayoutPickerSelect(layoutId: string): void {
            void this.openLayoutById(layoutId);
        },

        onOpenCreateLayout(): void {
            this.createWizardName = '';
            this.createWizardSelectedType = null;
            this.isCreateLayoutModalOpen = true;
        },

        onCreateLayoutModalCancel(): void {
            this.isCreateLayoutModalOpen = false;
        },

        closeLayout(layoutId: string): void {
            const openLayout = this.openLayouts.find((item) => item.id === layoutId);

            if (!openLayout) {
                return;
            }

            this.syncActiveLayoutState();

            if (this.isLayoutDirty(openLayout)) {
                this.pendingCloseLayoutId = layoutId;
                return;
            }

            this.removeOpenLayout(layoutId);
        },

        removeOpenLayout(layoutId: string): void {
            const index = this.openLayouts.findIndex((item) => item.id === layoutId);

            if (index === -1) {
                return;
            }

            this.openLayouts.splice(index, 1);
            this.editorStore.removeLayout(layoutId);
            this.pendingCloseLayoutId = null;

            if (this.activeLayoutId === layoutId) {
                const nextLayout = this.openLayouts[Math.max(0, index - 1)] ?? this.openLayouts[0];
                this.activeLayoutId = null;

                if (nextLayout) {
                    this.activateLayout(nextLayout.id);
                } else {
                    this.layout = null;
                    this.selectedElementId = null;
                    this.isLayoutPickerOpen = !this.workspaceStore?.activeWorkspace;
                }
            }
        },

        async onSaveAndCloseLayout(): Promise<void> {
            const layoutId = this.pendingCloseLayoutId;

            if (layoutId && (await this.saveOpenLayout(layoutId))) {
                this.removeOpenLayout(layoutId);
            }
        },

        onDiscardCloseLayout(): void {
            if (this.pendingCloseLayoutId) {
                this.removeOpenLayout(this.pendingCloseLayoutId);
            }
        },

        onCancelCloseLayout(): void {
            this.pendingCloseLayoutId = null;
        },

        adjustElementSettingsWidth(): void {
            this.setElementSettingsWidth(
                this.selectedElementHasRichText ? RICH_TEXT_ELEMENT_SETTINGS_WIDTH : this.elementSettingsWidth,
            );
        },

        getElementSettingsMaxWidth(): number {
            const workspace = this.$refs.workspace as HTMLElement | undefined;
            const workspaceWidth = workspace?.getBoundingClientRect().width ?? Number.POSITIVE_INFINITY;

            // Reserve the sidebar, separator and preview columns of the workspace grid.
            return Math.max(
                MIN_ELEMENT_SETTINGS_WIDTH,
                Math.min(MAX_ELEMENT_SETTINGS_WIDTH, workspaceWidth - 280 - 8 - MIN_PREVIEW_WIDTH),
            );
        },

        setElementSettingsWidth(width: number): void {
            this.elementSettingsWidth = Math.max(
                MIN_ELEMENT_SETTINGS_WIDTH,
                Math.min(this.getElementSettingsMaxWidth(), width),
            );
        },

        onElementSettingsResizeKeydown(event: KeyboardEvent): void {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
                return;
            }

            event.preventDefault();
            const delta = event.key === 'ArrowLeft' ? ELEMENT_SETTINGS_RESIZE_STEP : -ELEMENT_SETTINGS_RESIZE_STEP;
            this.setElementSettingsWidth(this.elementSettingsWidth + delta);
        },

        onViewportChange(viewport: Viewport): void {
            this.currentViewport = viewport;
        },

        onFrameViewportChange(layoutId: string, viewport: Viewport): void {
            const openLayout = this.openLayouts.find((item) => item.id === layoutId);

            if (!openLayout) {
                return;
            }

            openLayout.viewport = viewport;

            if (layoutId === this.activeLayoutId) {
                this.currentViewport = viewport;
            }
        },

        onSettingsPanelClose(): void {
            this.elementSettingsWidth = MIN_ELEMENT_SETTINGS_WIDTH;
        },

        canManageLayoutAssignments(openLayout: ExperienceStudioOpenLayout): boolean {
            if (openLayout.isNew) {
                return false;
            }

            const rootSource = this.getLayoutRootSource(openLayout.layout);

            return rootSource === 'product' || rootSource === 'category' || rootSource === 'landing_page';
        },

        onOpenAssignmentModal(layoutId: string): void {
            this.assignmentLayoutId = layoutId;
            this.isAssignmentModalOpen = true;
        },

        onCloseAssignmentModal(): void {
            this.isAssignmentModalOpen = false;
            this.assignmentLayoutId = null;
        },

        onElementSettingsResizeStart(event: PointerEvent): void {
            event.preventDefault();
            const resizeHandle = event.currentTarget as HTMLElement | null;

            if (resizeHandle?.setPointerCapture) {
                resizeHandle.setPointerCapture(event.pointerId);
            }

            this.resizeHandle = resizeHandle;
            this.resizePointerId = event.pointerId;
            this.resizeStartX = event.clientX;
            this.resizeStartWidth = this.elementSettingsWidth;
            this.isResizingElementSettings = true;
            this.resizeMoveHandler = (moveEvent: PointerEvent): void => {
                this.onElementSettingsResizeMove(moveEvent);
            };
            this.resizeEndHandler = (): void => {
                this.stopElementSettingsResize();
            };
            document.addEventListener('pointermove', this.resizeMoveHandler);
            document.addEventListener('pointerup', this.resizeEndHandler, { once: true });
            document.addEventListener('pointercancel', this.resizeEndHandler);
            window.addEventListener('blur', this.resizeEndHandler);
        },

        onElementSettingsResizeMove(event: PointerEvent): void {
            if (!this.isResizingElementSettings) {
                return;
            }

            const width = this.resizeStartWidth - (event.clientX - this.resizeStartX);

            this.setElementSettingsWidth(width);
        },

        stopElementSettingsResize(): void {
            if (this.resizeMoveHandler) {
                document.removeEventListener('pointermove', this.resizeMoveHandler);
            }

            if (this.resizeEndHandler) {
                document.removeEventListener('pointerup', this.resizeEndHandler);
                document.removeEventListener('pointercancel', this.resizeEndHandler);
                window.removeEventListener('blur', this.resizeEndHandler);
            }

            if (
                this.resizeHandle &&
                this.resizePointerId !== null &&
                this.resizeHandle.hasPointerCapture(this.resizePointerId)
            ) {
                this.resizeHandle.releasePointerCapture(this.resizePointerId);
            }

            this.resizeMoveHandler = null;
            this.resizeEndHandler = null;
            this.resizeHandle = null;
            this.resizePointerId = null;
            this.isResizingElementSettings = false;
        },

        async loadDefaultPreviewEntity(): Promise<void> {
            if (this.previewEntityId) {
                return;
            }

            const previewEntityType = this.previewEntityType;
            const layoutId = this.layout?.id;
            const workspaceId = this.workspaceStore?.activeWorkspace?.id;

            if (!previewEntityType) {
                return;
            }

            try {
                const repository = this.repositoryFactory.create(previewEntityType as keyof EntitySchema.EntityKeys);
                const entities = await repository.search(this.defaultPreviewEntityCriteria, Shopware.Context.api);
                const firstEntity = entities.first();

                if (
                    this.layout?.id === layoutId &&
                    this.workspaceStore?.activeWorkspace?.id === workspaceId &&
                    !this.previewEntityId &&
                    firstEntity?.id
                ) {
                    this.previewEntityId = firstEntity.id;
                }
            } catch {
                // Keep preview entity empty when no default entity can be loaded.
            }
        },

        applyPreviewContextDefaults(): void {
            const resolvedPreviewContext = this.resolvedPreviewContext;

            if (!this.previewEntityId && resolvedPreviewContext?.entityId) {
                this.previewEntityId = resolvedPreviewContext.entityId;
            }

            if (!this.previewSalesChannelId && resolvedPreviewContext?.salesChannelId) {
                this.previewSalesChannelId = resolvedPreviewContext.salesChannelId;
            }
        },

        onCreateWizardNameChange(name: string): void {
            this.createWizardName = name;
        },

        onCreateWizardTypeChange(type: string | null): void {
            this.createWizardSelectedType = type;
        },

        getLayoutTypeIcon(entityType: string): string {
            const iconByEntityType: Record<string, string> = {
                product: 'regular-products',
                category: 'regular-sitemap',
                landing_page: 'regular-dashboard',
            };

            return iconByEntityType[entityType] ?? 'regular-file';
        },

        onCreateWizardComplete(payload: { name: string; type: string }): void {
            if (!this.workspaceStore.activeWorkspace) {
                return;
            }

            const isFirstOpenLayout = this.openLayouts.length === 0;
            const layout = this.layoutRepository.create(Shopware.Context.api);
            layout.name = payload.name;
            layout.rootSource = payload.type;
            layout.version = '1.0.0';
            layout.layout = [];
            const openLayout = this.createOpenLayoutState(layout, true);
            this.openLayouts.push(openLayout);
            this.isCreateLayoutModalOpen = false;
            this.activateLayout(layout.id);
            this.createWizardName = payload.name;
            this.createWizardSelectedType = payload.type;
            this.applyPreviewContextDefaults();
            void this.loadDefaultPreviewEntity();
            this.editorStore.initialize(layout.id);
            this.syncActiveLayoutState();
            void this.$nextTick(() => {
                const canvas = this.$refs.experienceStudioCanvas as ExperienceStudioCanvasRef | undefined;

                if (isFirstOpenLayout) {
                    canvas?.fitCanvasToFrame();
                } else {
                    canvas?.fitCanvasToFrames();
                }
            });
        },

        getLayoutRootSource(layout: Entity<'content_layout'> | null): string | null {
            const rootSource = (layout as Entity<'content_layout'> & { rootSource?: unknown })?.rootSource;

            return typeof rootSource === 'string' && rootSource.length > 0 ? rootSource : null;
        },

        getFirstAssociationEntry(association: unknown): Record<string, unknown> | null {
            if (!association) {
                return null;
            }

            const firstMethod = (association as { first?: () => unknown }).first;

            if (typeof firstMethod === 'function') {
                return (firstMethod.call(association) as Record<string, unknown> | null) ?? null;
            }

            if (Array.isArray(association)) {
                return (association[0] as Record<string, unknown> | undefined) ?? null;
            }

            return null;
        },

        resolveAssignedPreviewContext(layout: Entity<'content_layout'> | null): LayoutPreviewContext | null {
            if (!layout) {
                return null;
            }

            const productAssignment = this.getFirstAssociationEntry(layout.productContentLayouts);

            if (productAssignment?.productId) {
                return {
                    entityType: 'product',
                    entityId: productAssignment.productId as string,
                    salesChannelId: (productAssignment.salesChannelId as string | null) ?? null,
                };
            }

            const categoryAssignment = this.getFirstAssociationEntry(layout.categoryContentLayouts);

            if (categoryAssignment?.categoryId) {
                return {
                    entityType: 'category',
                    entityId: categoryAssignment.categoryId as string,
                    salesChannelId: (categoryAssignment.salesChannelId as string | null) ?? null,
                };
            }

            const landingPageAssignment = this.getFirstAssociationEntry(layout.landingPageContentLayouts);

            if (landingPageAssignment?.landingPageId) {
                return {
                    entityType: 'landing_page',
                    entityId: landingPageAssignment.landingPageId as string,
                    salesChannelId: (landingPageAssignment.salesChannelId as string | null) ?? null,
                };
            }

            return null;
        },

        resolvePreviewContext(layout: Entity<'content_layout'> | null): LayoutPreviewContext | null {
            if (!layout) {
                return null;
            }

            const assignedContext = this.resolveAssignedPreviewContext(layout);
            const rootSource = this.getLayoutRootSource(layout);

            if (!rootSource) {
                return assignedContext;
            }

            if (assignedContext?.entityType === rootSource) {
                return {
                    entityType: rootSource,
                    entityId: assignedContext.entityId,
                    salesChannelId: assignedContext.salesChannelId,
                };
            }

            return {
                entityType: rootSource,
                entityId: null,
                salesChannelId: null,
            };
        },

        async loadElementTypes(): Promise<void> {
            await this.elementTypeStore.loadTypes();
        },

        async loadStyleOptions(): Promise<void> {
            await this.styleOptionStore.loadStyleOptions();
        },

        async loadLayoutPresets(): Promise<void> {
            await this.layoutPresetStore.loadPresets();
        },

        async loadMappingCandidates(): Promise<void> {
            // Custom-field candidates are runtime configuration and may have changed since the store was first
            // populated elsewhere in the Administration.
            await this.mappingCandidateStore.loadMappingCandidates(true);
        },

        entityTypeService(): ContentSystemEntityTypeService {
            return Shopware.Service('contentSystemEntityTypeService') as ContentSystemEntityTypeService;
        },

        async loadLayoutTypes(): Promise<void> {
            this.isLoadingLayoutTypes = true;
            this.layoutTypeLoadError = null;

            try {
                this.availableLayoutTypes = await this.entityTypeService().getEntityTypes();
            } catch {
                this.layoutTypeLoadError = 'Failed to load layout types.';
                this.availableLayoutTypes = [];
            } finally {
                this.isLoadingLayoutTypes = false;
            }
        },

        onPreviewSalesChannelChange(salesChannelId: string | null): void {
            if (!salesChannelId) {
                return;
            }

            this.previewSalesChannelId = salesChannelId;
        },

        onFramePreviewEntityChange(layoutId: string, entityId: string | null): void {
            const openLayout = this.openLayouts.find((item) => item.id === layoutId);

            if (!openLayout) {
                return;
            }

            openLayout.previewEntityId = entityId;

            if (layoutId === this.activeLayoutId) {
                this.previewEntityId = entityId;
            }
            if (this.workspaceStore?.activeWorkspace) {
                void this.scheduleWorkspaceSetup();
            }
        },

        onFrameElementSelect(layoutId: string, elementId: string | null): void {
            this.activateLayout(layoutId);
            this.onElementSelect(elementId);
        },

        onElementSelect(elementId: string | null): void {
            this.selectedElementId = elementId;
            this.syncActiveLayoutState();

            if (!elementId) {
                return;
            }

            const canvas = this.$refs.experienceStudioCanvas as ExperienceStudioCanvasRef | undefined;
            canvas?.openSettingsPanel();
        },

        onAddElement(payload: AddElementPayload): void {
            this.pendingAddElementPayload = payload;
            this.pickerAnchorElement = payload.anchorElement;
            this.isElementPickerOpen = true;
        },

        onCloseElementPicker(): void {
            this.isElementPickerOpen = false;
            this.pendingAddElementPayload = null;
            this.pickerAnchorElement = null;
        },

        async onSelectElementType(component: string): Promise<void> {
            const payload = this.pendingAddElementPayload;

            if (!payload) {
                this.onCloseElementPicker();
                return;
            }

            if (payload.parentElementId !== null) {
                if (!payload.slotName || !this.layout) {
                    this.onCloseElementPicker();
                    return;
                }

                const parentLocation = findElementLocation(this.layout.layout, payload.parentElementId);
                const parentElement = parentLocation ? parentLocation.elements[parentLocation.index] : null;

                if (!parentElement) {
                    this.onCloseElementPicker();
                    return;
                }

                if (!this.canInsertIntoSlot(parentElement.component, payload.slotName, component, parentElement)) {
                    this.createNotificationInfo({
                        message: this.$t('sw-experience-studio.detail.sidebarTree.addElementNotAllowed'),
                    });
                    this.onCloseElementPicker();
                    return;
                }
            }

            const insertPayload: Omit<ContentLayoutDraftInsertPayload, 'layout' | 'rootSource'> = {
                type: component,
            };

            if (payload.parentElementId !== null) {
                insertPayload.parentElementId = payload.parentElementId;
                insertPayload.slot = payload.slotName;
            }

            await this.executeStructuralDraftMutation(
                'insert',
                insertPayload,
                (response) => response.affectedElementIds[0] ?? this.selectedElementId,
            );

            this.onCloseElementPicker();
        },

        async onSelectPreset(presetId: string): Promise<void> {
            const payload = this.pendingAddElementPayload;

            if (!payload || !this.layout) {
                this.onCloseElementPicker();
                return;
            }

            const insertPresetPayload: Omit<ContentLayoutDraftInsertPresetPayload, 'layout' | 'rootSource'> = {
                presetId,
            };

            if (payload.parentElementId !== null) {
                insertPresetPayload.parentElementId = payload.parentElementId;
                insertPresetPayload.slot = payload.slotName;
            }

            await this.executeStructuralDraftMutation(
                'insert-preset',
                insertPresetPayload,
                (response) => response.affectedElementIds[0] ?? this.selectedElementId,
            );

            this.onCloseElementPicker();
        },

        applyLayoutMutation(mutator: (layout: ContentElementNode[]) => LayoutMutationResult): void {
            if (!this.layout || !this.allowSave) {
                return;
            }

            const layoutElements = this.layout.layout;
            const workingLayout = cloneDeep(layoutElements);
            const result = mutator(workingLayout);

            if (result === false) {
                return;
            }

            this.editorStore.pushToHistory(layoutElements, this.selectedElementId);
            this.layout.layout = workingLayout;
            this.showDiagnostics = false;

            if (result.selectedElementId !== undefined) {
                this.selectedElementId = result.selectedElementId;
            }

            this.scheduleDiagnoseLayout();
        },

        async onDuplicateElement(elementId: string): Promise<void> {
            if (!this.layout || !this.allowSave) {
                return;
            }

            await this.executeStructuralDraftMutation(
                'duplicate',
                {
                    elementId,
                },
                (response) => response.affectedElementIds[0] ?? this.selectedElementId,
            );
        },

        async onDeleteElement(elementId: string): Promise<void> {
            if (!this.layout || !this.allowSave) {
                return;
            }

            await this.executeStructuralDraftMutation(
                'remove',
                {
                    elementId,
                },
                (response) => {
                    if (!this.selectedElementId) {
                        return null;
                    }

                    const selectedLocation = findElementLocation(response.layout, this.selectedElementId);

                    return selectedLocation ? this.selectedElementId : null;
                },
            );
        },

        async onMoveElement(payload: MoveElementPayload): Promise<void> {
            if (!this.layout || !this.allowSave) {
                return;
            }

            const layoutElements = this.layout.layout;
            const normalizedMoveIndex = this.normalizeMoveIndex(layoutElements, payload);

            await this.executeStructuralDraftMutation(
                'move',
                {
                    elementId: payload.elementId,
                    newParentId: payload.newParentElementId,
                    newSlot: payload.newSlotName,
                    index: normalizedMoveIndex,
                },
                () => payload.elementId,
            );
        },

        normalizeMoveIndex(layout: ContentElementNode[], payload: MoveElementPayload): number | null {
            if (payload.newIndex === null || payload.newIndex === undefined) {
                return null;
            }

            const sourceLocation = findElementLocation(layout, payload.elementId);

            if (!sourceLocation) {
                return payload.newIndex;
            }

            const targetElements = this.resolveMoveTargetElements(layout, payload.newParentElementId, payload.newSlotName);

            if (!targetElements || sourceLocation.elements !== targetElements) {
                return payload.newIndex;
            }

            if (sourceLocation.index < payload.newIndex) {
                return payload.newIndex - 1;
            }

            return payload.newIndex;
        },

        resolveMoveTargetElements(
            layout: ContentElementNode[],
            newParentElementId: string | null,
            newSlotName: string | null,
        ): ContentElementNode[] | null {
            if (newParentElementId === null) {
                return layout;
            }

            if (!newSlotName) {
                return null;
            }

            const targetParentLocation = findElementLocation(layout, newParentElementId);
            const targetParentElement = targetParentLocation
                ? targetParentLocation.elements[targetParentLocation.index]
                : null;

            if (!targetParentElement) {
                return null;
            }

            return targetParentElement.slots?.[newSlotName] ?? [];
        },

        validateMoveTarget(payload: MoveElementPayload): boolean {
            if (!this.layout) {
                return false;
            }

            const layoutElements = this.layout.layout;
            const draggedLocation = findElementLocation(layoutElements, payload.elementId);
            const draggedElement = draggedLocation ? draggedLocation.elements[draggedLocation.index] : null;

            if (!draggedElement) {
                return false;
            }

            if (payload.newParentElementId === null) {
                return true;
            }

            if (!payload.newSlotName) {
                return false;
            }

            const targetParentLocation = findElementLocation(layoutElements, payload.newParentElementId);
            const targetParentElement = targetParentLocation
                ? targetParentLocation.elements[targetParentLocation.index]
                : null;

            if (!targetParentElement) {
                return false;
            }

            if (this.isElementInSubtree(draggedElement, payload.newParentElementId)) {
                return false;
            }

            return this.canInsertIntoSlot(
                targetParentElement.component,
                payload.newSlotName,
                draggedElement.component,
                targetParentElement,
                payload.elementId,
            );
        },

        onElementSettingsChange(payload: { elementId: string; properties: Record<string, unknown> }): void {
            this.applyLayoutMutation((layout) => {
                return updateElementPropertiesInLayout(layout, payload.elementId, payload.properties) ? {} : false;
            });
        },

        onElementStyleChange(payload: { elementId: string; style: Record<string, unknown> }): void {
            this.applyLayoutMutation((layout) => {
                return updateElementStyleInLayout(layout, payload.elementId, payload.style) ? {} : false;
            });
        },

        async onElementMappingChange(payload: {
            elementId: string;
            propertyKey: string;
            source: { type: string; id: string; config?: Record<string, unknown>; path?: string } | null;
            contextType: 'single' | 'collection' | null;
        }): Promise<void> {
            if (!this.layout) {
                return;
            }

            const rootSource = this.resolveMutationRootSource();
            if (payload.source !== null && rootSource === null) {
                this.createNotificationError({
                    message: this.$t('sw-experience-studio.detail.mappingMutationErrors.missingRootSource'),
                });

                return;
            }

            await this.executeStructuralDraftMutation(
                payload.source === null ? 'unmap-property' : 'map-property',
                {
                    elementId: payload.elementId,
                    propertyKey: payload.propertyKey,
                    ...(payload.source === null ? {} : { source: payload.source }),
                },
                () => payload.elementId,
            );
        },

        draftMutationService(): ContentSystemLayoutDraftMutationService {
            return Shopware.Service('contentSystemLayoutDraftMutationService') as ContentSystemLayoutDraftMutationService;
        },

        async diagnoseLayout(): Promise<void> {
            if (this.diagnoseTimeoutId !== null) {
                window.clearTimeout(this.diagnoseTimeoutId);
                this.diagnoseTimeoutId = null;
            }

            if (!this.layout) {
                this.diagnostics = [];

                return;
            }

            const requestId = this.diagnoseRequestSequence + 1;
            this.diagnoseRequestSequence = requestId;
            this.latestDiagnoseRequestId = requestId;

            try {
                const response = await this.draftMutationService().diagnose({
                    layout: cloneDeep(this.layout.layout),
                    rootSource: this.resolveMutationRootSource(),
                });

                if (requestId === this.latestDiagnoseRequestId) {
                    this.diagnostics = response.diagnostics.violations;
                }
            } catch {
                if (requestId === this.latestDiagnoseRequestId) {
                    this.diagnostics = [];
                    this.createNotificationError({
                        message: this.$t('sw-experience-studio.detail.messageDiagnoseError'),
                    });
                }
            }
        },

        scheduleDiagnoseLayout(): void {
            if (this.diagnoseTimeoutId !== null) {
                window.clearTimeout(this.diagnoseTimeoutId);
            }

            // A pending edit makes any currently running diagnosis stale before its response can land.
            this.diagnoseRequestSequence += 1;
            this.latestDiagnoseRequestId = this.diagnoseRequestSequence;
            this.diagnoseTimeoutId = window.setTimeout(() => {
                this.diagnoseTimeoutId = null;
                void this.diagnoseLayout();
            }, DIAGNOSE_DEBOUNCE_MS);
        },

        resolveMutationRootSource(): string | null {
            return this.getLayoutRootSource(this.layout);
        },

        extractMutationErrorCodes(error: unknown): string[] {
            const responseErrors = (
                error as {
                    response?: {
                        data?: {
                            errors?: Array<{ code?: unknown }>;
                        };
                    };
                }
            ).response?.data?.errors;

            if (!Array.isArray(responseErrors)) {
                return [];
            }

            return responseErrors
                .map((item) => (typeof item.code === 'string' ? item.code : null))
                .filter((code): code is string => code !== null);
        },

        notifyMutationError(codes: string[]): void {
            const errorSnippet = codes
                .map((code) => MUTATION_ERROR_SNIPPETS[code])
                .find((snippet): snippet is string => typeof snippet === 'string');

            this.createNotificationError({
                message: this.$t(errorSnippet ?? 'sw-experience-studio.detail.mutationErrors.generic'),
            });
        },

        createDraftMutationPayload(
            layout: ContentElementNode[],
            operationPayload: Record<string, unknown>,
            rootSource?: string | null,
        ): Record<string, unknown> {
            return {
                // Working-tree layout data crossing an outbound boundary is cloned at the call site.
                layout: cloneDeep(layout),
                rootSource: rootSource === undefined ? this.resolveMutationRootSource() : rootSource,
                ...operationPayload,
            };
        },

        async requestDraftMutation(
            operation: DraftMutationOperation,
            layout: ContentElementNode[],
            operationPayload: Record<string, unknown>,
            rootSource?: string | null,
        ): Promise<ContentLayoutDraftMutationResponse> {
            const service = this.draftMutationService();
            const payload = this.createDraftMutationPayload(layout, operationPayload, rootSource);

            if (operation === 'insert') {
                return service.insertElement(payload as ContentLayoutDraftInsertPayload);
            }

            if (operation === 'remove') {
                return service.removeElement(payload as ContentLayoutDraftRemovePayload);
            }

            if (operation === 'move') {
                return service.moveElement(payload as ContentLayoutDraftMovePayload);
            }

            if (operation === 'insert-preset') {
                return service.insertPreset(payload as ContentLayoutDraftInsertPresetPayload);
            }

            if (operation === 'map-property') {
                return service.mapProperty(payload as ContentLayoutDraftMapPropertyPayload);
            }

            if (operation === 'unmap-property') {
                return service.unmapProperty(payload as ContentLayoutDraftUnmapPropertyPayload);
            }

            return service.duplicateElement(payload as ContentLayoutDraftDuplicatePayload);
        },

        async executeStructuralDraftMutation(
            operation: DraftMutationOperation,
            operationPayload: Record<string, unknown>,
            resolveSelectedElementId: (response: ContentLayoutDraftMutationResponse) => string | null,
        ): Promise<void> {
            if (!this.layout || !this.allowSave) {
                return;
            }

            const layout = this.layout;
            const rootSource = this.resolveMutationRootSource();
            this.pendingMutationCount += 1;
            this.isLoading = true;

            const mutation = this.mutationQueue.then(async () => {
                const openLayout = this.openLayouts.find((item) => item.layout === layout);
                if (!openLayout || !this.allowSave) {
                    return;
                }

                const currentLayout = cloneDeep(layout.layout);
                const previousSelectedElementId =
                    this.layout === layout ? this.selectedElementId : openLayout.selectedElementId;

                try {
                    const response = await this.requestDraftMutation(operation, currentLayout, operationPayload, rootSource);
                    if (!this.openLayouts.includes(openLayout)) {
                        return;
                    }

                    this.editorStore.pushToHistory(currentLayout, previousSelectedElementId, layout.id);
                    layout.layout = response.layout;
                    if (this.layout !== layout) {
                        return;
                    }

                    this.selectedElementId = resolveSelectedElementId(response);
                    this.showDiagnostics = false;
                    this.diagnoseRequestSequence += 1;
                    this.latestDiagnoseRequestId = this.diagnoseRequestSequence;
                    this.diagnostics = response.diagnostics.violations;
                } catch (error) {
                    this.notifyMutationError(this.extractMutationErrorCodes(error));
                }
            });

            this.mutationQueue = mutation
                .catch(() => undefined)
                .finally(() => {
                    this.pendingMutationCount -= 1;
                    this.isLoading = this.pendingMutationCount > 0;
                });

            await mutation;
        },

        isPresetAllowedForPayload(
            preset: ContentSystemLayoutPreset,
            payload: AddElementPayload,
            allowedComponents: Set<string>,
        ): boolean {
            if (payload.parentElementId === null) {
                return true;
            }

            return preset.payload.every((rootElement) => allowedComponents.has(rootElement.component));
        },

        getAvailableTypesForPayload(payload: AddElementPayload | null): ContentSystemElementTypeSpecification[] {
            const allTypes = this.elementTypeStore.allTypes;

            if (!payload) {
                return [];
            }

            if (payload.parentElementId === null) {
                return allTypes;
            }

            if (!payload.slotName || !this.layout) {
                return [];
            }

            const parentLocation = findElementLocation(this.layout.layout, payload.parentElementId);
            const parentElement = parentLocation ? parentLocation.elements[parentLocation.index] : null;

            if (!parentElement) {
                return [];
            }

            const parentType = this.elementTypeStore.getByName(parentElement.component);
            const slotDefinition = parentType?.slots.find((slot) => slot.name === payload.slotName);

            if (!slotDefinition) {
                return allTypes;
            }

            const existingElements = parentElement.slots?.[payload.slotName] ?? [];

            if (slotDefinition.maxElements !== null && existingElements.length >= slotDefinition.maxElements) {
                return [];
            }

            if (slotDefinition.allowList.length === 0) {
                return allTypes;
            }

            return slotDefinition.allowList
                .map((typeName) => this.elementTypeStore.getByName(typeName))
                .filter((type): type is ContentSystemElementTypeSpecification => type !== null);
        },

        canInsertIntoSlot(
            parentComponent: string,
            slotName: string,
            childComponent: string,
            parentElement: ContentElementNode,
            ignoreElementId: string | null = null,
        ): boolean {
            const parentType = this.elementTypeStore.getByName(parentComponent);
            const slotDefinition = parentType?.slots.find((slot) => slot.name === slotName);

            if (!slotDefinition) {
                return true;
            }

            const existingElements = (parentElement.slots?.[slotName] ?? []).filter((element) => {
                return ignoreElementId === null || element.id !== ignoreElementId;
            });

            if (slotDefinition.maxElements !== null && existingElements.length >= slotDefinition.maxElements) {
                return false;
            }

            if (slotDefinition.allowList.length === 0) {
                return true;
            }

            return slotDefinition.allowList.includes(childComponent);
        },

        isElementInSubtree(element: ContentElementNode, soughtElementId: string): boolean {
            if (element.id === soughtElementId) {
                return true;
            }

            for (const slotElements of Object.values(element.slots ?? {})) {
                for (const childElement of slotElements) {
                    if (this.isElementInSubtree(childElement, soughtElementId)) {
                        return true;
                    }
                }
            }

            return false;
        },

        onUndo(): void {
            if (!this.layout || !this.canUndo) {
                return;
            }

            const layoutElements = this.layout.layout;
            const previousEntry = this.editorStore.undo(layoutElements, this.selectedElementId);

            if (!previousEntry) {
                return;
            }

            this.layout.layout = previousEntry.layout;
            this.selectedElementId = previousEntry.selectedElementId;
            this.showDiagnostics = false;
            this.scheduleDiagnoseLayout();
        },

        onRedo(): void {
            if (!this.layout || !this.canRedo) {
                return;
            }

            const layoutElements = this.layout.layout;
            const nextEntry = this.editorStore.redo(layoutElements, this.selectedElementId);

            if (!nextEntry) {
                return;
            }

            this.layout.layout = nextEntry.layout;
            this.selectedElementId = nextEntry.selectedElementId;
            this.showDiagnostics = false;
            this.scheduleDiagnoseLayout();
        },

        onHistoryKeydown(event: KeyboardEvent): void {
            if (!this.allowSave || this.pendingWorkspaceAction || this.isWorkspaceTransitioning) {
                return;
            }

            const target = event.target;

            if (
                target instanceof HTMLInputElement ||
                target instanceof HTMLTextAreaElement ||
                (target instanceof HTMLElement && target.isContentEditable)
            ) {
                return;
            }

            const isModifierPressed = event.ctrlKey || event.metaKey;

            if (!isModifierPressed) {
                return;
            }

            if (event.key === 'z' && !event.shiftKey) {
                event.preventDefault();
                this.onUndo();
                return;
            }

            if ((event.key === 'z' && event.shiftKey) || event.key === 'y') {
                event.preventDefault();
                this.onRedo();
            }
        },

        onDiscardLayout(layoutId: string): void {
            const openLayout = this.openLayouts.find((item) => item.id === layoutId);
            if (
                !openLayout ||
                !this.allowSave ||
                this.isLoading ||
                this.isSavingAll ||
                this.isWorkspaceTransitioning ||
                this.pendingMutationCount > 0 ||
                this.openLayouts.some((item) => item.isSaving) ||
                !this.isLayoutDirty(openLayout)
            ) {
                return;
            }

            const isActive = this.activeLayoutId === layoutId;
            if (isActive) {
                const canvas = this.$refs.experienceStudioCanvas as ExperienceStudioCanvasRef | undefined;
                canvas?.closeSettingsPanel();
            }
            if (openLayout.isNew) {
                this.removeOpenLayout(layoutId);
                return;
            }

            const savedLayout = JSON.parse(openLayout.savedState) as Pick<
                ContentLayoutEntity,
                'name' | 'rootSource' | 'layout'
            >;
            Object.assign(openLayout.layout, savedLayout);
            openLayout.selectedElementId = null;
            openLayout.saveError = null;
            this.editorStore.removeLayout(layoutId);

            if (isActive) {
                this.layout = openLayout.layout;
                this.selectedElementId = null;
                this.showDiagnostics = false;
                this.diagnostics = [];
                this.editorStore.initialize(layoutId);
                void this.diagnoseLayout();
            }
        },

        async saveAllOpenLayouts(): Promise<boolean> {
            if (!this.allowSave || this.isSavingAll) {
                return false;
            }

            this.syncActiveLayoutState();
            const dirtyLayouts = this.openLayouts.filter((openLayout) => this.isLayoutDirty(openLayout));

            if (dirtyLayouts.length === 0) {
                return true;
            }

            this.isSavingAll = true;
            await Promise.all(dirtyLayouts.map((openLayout) => this.saveOpenLayout(openLayout.id)));
            this.isSavingAll = false;

            const failures = dirtyLayouts.filter((item) => item.saveError);

            if (failures.length) {
                this.createNotificationError({
                    message: this.$t('sw-experience-studio.workspace.savePartialFailure', {
                        layouts: failures.map((item) => item.layout.name).join(', '),
                    }),
                });
            } else {
                this.createNotificationSuccess({
                    message: this.$t('sw-experience-studio.workspace.saved'),
                });
            }
            return failures.length === 0;
        },

        async saveOpenLayout(layoutId: string): Promise<boolean> {
            const openLayout = this.openLayouts.find((item) => item.id === layoutId);

            if (!openLayout || !this.allowSave) {
                return false;
            }

            if (!openLayout.layout.name?.trim() || !this.getLayoutRootSource(openLayout.layout)) {
                openLayout.saveError = this.$t('sw-experience-studio.createWizard.missingFields');
                return false;
            }

            openLayout.isSaving = true;
            openLayout.saveError = null;

            try {
                openLayout.layout.layout = cloneDeep(openLayout.layout.layout);
                const savedState = this.serializeLayoutState(openLayout.layout);
                const savedDraft = cloneDeep(openLayout.layout.getDraft());
                await this.layoutRepository.save(openLayout.layout, Shopware.Context.api);
                openLayout.savedState = savedState;
                openLayout.isNew = false;
                // The write succeeded even if reloading fails; subsequent edits must use PATCH.
                openLayout.layout._isNew = false;
                Object.assign(openLayout.layout.getOrigin(), savedDraft);
                await this.reloadSavedLayout(openLayout, savedState);
                if (this.workspaceStore?.activeWorkspace) {
                    void this.scheduleWorkspaceSetup();
                }

                return true;
            } catch (error) {
                const detail = this.extractApiErrorDetail(error);
                openLayout.saveError = detail ?? this.$t('sw-experience-studio.detail.messageSaveError');

                return false;
            } finally {
                openLayout.isSaving = false;
            }
        },

        async reloadSavedLayout(openLayout: ExperienceStudioOpenLayout, savedState: string): Promise<void> {
            try {
                const reloaded = await this.layoutRepository.get(
                    openLayout.id,
                    Shopware.Context.api,
                    this.layoutLoadCriteria,
                );
                if (!reloaded) {
                    throw new Error('The saved content layout was not returned by the repository.');
                }

                // Mutations retain the current entity; drain even operations added while awaiting the queue.
                while (this.pendingMutationCount > 0) {
                    await this.mutationQueue;
                }

                const saved = JSON.parse(savedState) as Pick<ContentLayoutEntity, 'name' | 'rootSource' | 'layout'>;
                const current = JSON.parse(this.serializeLayoutState(openLayout.layout)) as typeof saved;
                openLayout.savedState = this.serializeLayoutState(reloaded);
                for (const key of ['name', 'rootSource', 'layout'] as const) {
                    if (JSON.stringify(current[key]) !== JSON.stringify(saved[key])) {
                        Object.assign(reloaded, { [key]: current[key] });
                    }
                }
                openLayout.layout = reloaded;
                if (this.activeLayoutId === openLayout.id) {
                    this.layout = reloaded;
                }
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-experience-studio.detail.messageReloadError'),
                });
            }
        },

        extractApiErrorDetail(error: unknown): string | null {
            const responseErrors = (
                error as {
                    response?: {
                        data?: {
                            errors?: Array<{ detail?: unknown }>;
                        };
                    };
                }
            ).response?.data?.errors;

            if (!Array.isArray(responseErrors)) {
                return null;
            }

            const detail = responseErrors.find((item) => typeof item.detail === 'string' && item.detail.trim())?.detail;

            return typeof detail === 'string' ? detail : null;
        },
    },
});
