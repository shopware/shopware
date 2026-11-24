import type { NavigationGuardNext, RouteLocationNormalized } from 'vue-router';
import type { LayoutTreeItem } from '../../component/sw-experience-studio-layout-tree';
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
import {
    anchorLanguageId,
    getPropertyControlType,
    withLanguageEntry,
} from 'src/module/sw-experience-studio/util/element-settings.util';
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
import { translateContentSystemText } from '../../util/content-system-translation.util';
import { getLayoutTranslationFields, type LayoutTranslationField } from '../../util/layout-translation.util';
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
    CONTENT_SYSTEM__BINDING_ROOT_SOURCE_NOT_SCOPED: 'sw-experience-studio.detail.mutationErrors.bindingRootSourceNotScoped',
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
    savedState: string;
    isNew: boolean;
    isSaving: boolean;
    saveError: string | null;
};

type ExperienceStudioPreviewFrame = {
    id: string;
    layoutId: string;
    selectedElementId: string | null;
    viewport: Viewport;
    previewEntityId: string | null;
    languageId?: string;
};

type ExperienceStudioCanvasFrame = {
    id: string;
    label: string;
    languageLabel: string;
    ariaLabel: string;
    layout: ContentLayoutEntity;
    selectedElementId: string | null;
    viewport: Viewport;
    previewEntityId: string | null;
    languageId: string;
    isActive: boolean;
};

type ExperienceStudioCanvasRef = {
    openSettingsPanel: (showStructure?: boolean) => void;
    closeSettingsPanel: () => void;
    closePanel: () => void;
    fitCanvasToFrame: () => void;
    fitCanvasToFrames: () => void;
    getWorkspaceConfiguration: () => WorkspaceConfiguration;
    copyFrameState: (sourceFrameId: string, frameId: string) => void;
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
    frames: ExperienceStudioPreviewFrame[];
    activeFrameId: string | null;
    languageParents: Record<string, string | null>;
    previewLanguageContext: {
        salesChannelId: string;
        defaultLanguageId: string;
        languageIds: string[];
        languageNames: Record<string, string>;
        languageParents: Record<string, string | null>;
    } | null;
    previewLanguageRequestSequence: number;
    isLayoutPickerOpen: boolean;
    isCreateLayoutModalOpen: boolean;
    pendingCloseLayoutId: string | null;
    isSavingAll: boolean;
    isLoading: boolean;
    isSaveSuccessful: boolean;
    currentViewport: Viewport;
    selectedElementId: string | null;
    previewSalesChannelId: string | null;
    settingsPanelMode: 'element' | 'translations';
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
            frames: [],
            activeFrameId: null,
            languageParents: {},
            previewLanguageContext: null,
            previewLanguageRequestSequence: 0,
            isLayoutPickerOpen: false,
            isCreateLayoutModalOpen: false,
            pendingCloseLayoutId: null,
            isSavingAll: false,
            isLoading: false,
            isSaveSuccessful: false,
            currentViewport: 'desktop',
            selectedElementId: null,
            settingsPanelMode: 'element',
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

        activeLayoutId(): string | null {
            return this.frames.find((frame) => frame.id === this.activeFrameId)?.layoutId ?? null;
        },

        activeLanguageId(): string {
            return this.frames.find((frame) => frame.id === this.activeFrameId)?.languageId ?? anchorLanguageId();
        },

        activeLanguageName(): string {
            const languageId = this.activeLanguageId;
            return this.previewLanguageContext?.languageNames[languageId] ?? '';
        },

        previewLanguageOptions(): Array<{ value: string; label: string }> {
            const context = this.previewLanguageContext;
            if (!this.isPreviewLanguageContextReady || !context) {
                return [];
            }
            return context.languageIds
                .map((value) => ({ value, label: context.languageNames[value] ?? value }))
                .sort((first, second) => first.label.localeCompare(second.label));
        },

        isPreviewLanguageContextReady(): boolean {
            return (
                this.previewLanguageContext !== null &&
                this.previewLanguageContext.salesChannelId === this.previewSalesChannelId
            );
        },

        frameContexts(): Record<string, apiContext> {
            return Object.fromEntries(
                this.frames.map((frame) => {
                    const languageId = frame.languageId ?? anchorLanguageId();
                    const name = this.previewLanguageContext?.languageNames[languageId];
                    const parentId = this.languageParents[languageId];
                    return [
                        frame.id,
                        {
                            ...Shopware.Context.api,
                            languageId: languageId as EntityKey<'language'>,
                            language: name
                                ? { name, parentId: parentId ? (parentId as EntityKey<'language'>) : undefined }
                                : null,
                        },
                    ] as const;
                }),
            );
        },

        changedLayouts(): Array<{ id: string; name: string }> {
            return this.openLayouts
                .filter((openLayout) => this.isLayoutDirty(openLayout))
                .map((openLayout) => ({ id: openLayout.id, name: openLayout.layout.name ?? '' }));
        },

        layoutTreeItems(): LayoutTreeItem[] {
            return this.openLayouts.map((document) => ({
                id: document.id,
                name: document.layout.name ?? '',
                isDirty: this.isLayoutDirty(document),
                saveError: document.saveError,
                canManageAssignments: this.canManageLayoutAssignments(document),
            }));
        },

        canvasFrames(): ExperienceStudioCanvasFrame[] {
            return this.frames.flatMap((frame) => {
                const document = this.openLayouts.find((item) => item.id === frame.layoutId);
                if (!document) {
                    return [];
                }
                const isActive = frame.id === this.activeFrameId;
                const languageId = frame.languageId ?? anchorLanguageId();
                const languageLabel = this.previewLanguageContext?.languageNames[languageId] ?? '';
                return [
                    {
                        id: frame.id,
                        label: document.layout.name ?? '',
                        languageLabel,
                        ariaLabel: [this.previewFrameAriaLabel(frame.id), languageLabel].filter(Boolean).join(' · '),
                        layout: document.layout,
                        selectedElementId: isActive ? this.selectedElementId : frame.selectedElementId,
                        viewport: isActive ? this.currentViewport : frame.viewport,
                        previewEntityId: isActive ? this.previewEntityId : frame.previewEntityId,
                        languageId,
                        isActive,
                    },
                ];
            });
        },

        layoutPickerOpenIds(): string[] {
            return this.openLayouts.map((openLayout) => openLayout.id);
        },

        previewFrameIds(): string[] {
            return this.frames.map((frame) => frame.id);
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

        allowCreate(): boolean {
            return this.acl.can('experience_studio.creator');
        },

        allowDelete(): boolean {
            return this.acl.can('experience_studio.deleter');
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

        translationFields(): LayoutTranslationField[] {
            return getLayoutTranslationFields(this.layout?.layout ?? [], (name) => this.elementTypeStore.getByName(name));
        },

        translationPanelHasRichText(): boolean {
            return (
                this.settingsPanelMode === 'translations' &&
                this.translationFields.some(
                    ({ field }) => field.property.inlineMappable || getPropertyControlType(field.property) === 'richtext',
                )
            );
        },

        settingsPanelTitle(): string {
            return this.$t(
                this.settingsPanelMode === 'translations'
                    ? 'sw-experience-studio.detail.translationSettings.title'
                    : 'sw-experience-studio.detail.canvas.settings',
            );
        },

        availablePickerElements(): ElementPickerItem[] {
            const payload = this.pendingAddElementPayload;
            const availableTypes = this.getAvailableTypesForPayload(payload);

            const elementItems: ElementPickerItem[] = availableTypes.map((typeSpecification) => ({
                name: typeSpecification.name,
                label: translateContentSystemText(
                    typeSpecification.labelSnippet,
                    typeSpecification.label,
                    this.$te,
                    this.$t,
                ),
                icon: typeSpecification.icon,
                illustration: typeSpecification.illustration,
                category: typeSpecification.category,
                priority: typeSpecification.priority,
                kind: 'element',
            }));

            if (!payload) {
                return elementItems;
            }

            const allowedComponents = new Set(
                this.getStructurallyAvailableTypesForPayload(payload).map((typeSpecification) => typeSpecification.name),
            );

            const presetItems: ElementPickerItem[] = this.layoutPresetStore.allPresets
                .filter((preset) => this.isPresetAllowedForPayload(preset, payload, allowedComponents))
                .map((preset) => ({
                    name: preset.id,
                    label: translateContentSystemText(preset.nameSnippet, preset.name, this.$te, this.$t),
                    icon: preset.icon,
                    illustration: preset.illustration,
                    category: preset.category,
                    priority: preset.priority,
                    kind: 'preset',
                    id: preset.id,
                    description:
                        preset.description === null
                            ? null
                            : translateContentSystemText(preset.descriptionSnippet, preset.description, this.$te, this.$t),
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
        'workspaceStore.saveError'(hasError: boolean): void {
            if (hasError) {
                this.notifyWorkspaceSaveError();
            }
        },
        'workspaceStore.workspaceMissing'(missing: boolean): void {
            if (missing) {
                this.isWorkspacePickerOpen = true;
            }
        },
        previewFrameIds(): void {
            void this.scheduleWorkspaceSetup();
        },
        previewSalesChannelId(): void {
            void this.refreshPreviewLanguages();
        },
        previewEntityId(): void {
            void this.scheduleWorkspaceSetup();
        },
        currentViewport(): void {
            void this.scheduleWorkspaceSetup();
        },
        // Different previews can select the same element, leaving the element watchers unchanged.
        activeFrameId(): void {
            this.adjustElementSettingsWidth();
        },
        selectedElementId(): void {
            if (this.settingsPanelMode !== 'translations') {
                this.adjustElementSettingsWidth();
            }
        },

        selectedElementType(): void {
            if (this.settingsPanelMode !== 'translations') {
                this.adjustElementSettingsWidth();
            }
        },
        translationPanelHasRichText(): void {
            if (this.settingsPanelMode === 'translations') {
                this.adjustElementSettingsWidth();
            }
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
        notifyWorkspaceSaveError(): void {
            this.createNotificationError({
                message: this.$t('sw-experience-studio.workspace.manager.saveError'),
                autoClose: false,
                actions: [
                    {
                        label: this.$t('sw-experience-studio.workspace.manager.retry'),
                        method: () => {
                            void this.workspaceStore.flush().then((saved) => {
                                if (!saved) {
                                    this.notifyWorkspaceSaveError();
                                }
                            });
                        },
                    },
                ],
            });
        },

        async scheduleWorkspaceSetup(): Promise<void> {
            const workspaceId = this.workspaceStore?.activeWorkspace?.id;
            if (!this.allowSave || !workspaceId || this.isRestoringWorkspace) {
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
            configuration.frames = this.frames
                .filter((item) => this.openLayouts.some((document) => document.id === item.layoutId && !document.isNew))
                .flatMap((item) => {
                    const frame = configuration.frames.find((candidate) => candidate.id === item.id);
                    return frame
                        ? [
                              {
                                  ...frame,
                                  viewport: item.id === this.activeFrameId ? this.currentViewport : item.viewport,
                                  previewEntityId:
                                      item.id === this.activeFrameId ? this.previewEntityId : item.previewEntityId,
                                  languageId: item.languageId ?? anchorLanguageId(),
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
                const { openLayouts, frames, missingReferences } = await this.restoreWorkspaceLayouts(configuration);
                const { salesChannelId, missingReference: missingSalesChannel } = await this.restoreWorkspaceSalesChannel(
                    configuration.previewSalesChannelId,
                );
                const languageContext = await this.loadPreviewLanguageContext(salesChannelId);
                const missingLanguageContext = salesChannelId !== null && languageContext === null;
                this.isRestoringWorkspace = true;
                this.resetWorkspaceEditor();
                const missingLanguages = this.applyPreviewLanguageContext(languageContext, frames);
                this.workspaceCanvasConfiguration = configuration;
                this.workspaceStore.activate(workspace);
                this.openLayouts = openLayouts;
                this.frames = frames;
                this.previewSalesChannelId = languageContext?.salesChannelId ?? null;
                if (frames[0]) {
                    this.activateFrame(frames[0].id);
                }
                this.isWorkspacePickerOpen = false;
                await this.$nextTick();
                this.isRestoringWorkspace = false;
                await this.$router.replace({ name: 'sw.experience.studio.workspace', params: { workspaceId: id } });
                if (missingReferences || missingSalesChannel || missingLanguages || missingLanguageContext) {
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
            frames: ExperienceStudioPreviewFrame[];
            missingReferences: boolean;
        }> {
            const criteria = this.layoutLoadCriteria;
            const ids = [...new Set(configuration.frames.map((frame) => frame.layoutId))];
            criteria.setIds(ids);
            criteria.setLimit(Math.max(1, ids.length));
            const layouts = ids.length ? await this.layoutRepository.search(criteria, Shopware.Context.api) : [];
            const byId = new Map<string, ContentLayoutEntity>(
                layouts.map((layout) => [
                    layout.id,
                    layout,
                ]),
            );
            const frames = configuration.frames.flatMap((frame) => {
                const layout = byId.get(frame.layoutId);
                return layout
                    ? [
                          {
                              ...this.createPreviewFrame(layout.id, frame.id),
                              viewport: frame.viewport,
                              previewEntityId: frame.previewEntityId,
                              languageId: frame.languageId,
                          },
                      ]
                    : [];
            });
            const openLayouts = ids.flatMap((id) => {
                const layout = byId.get(id);
                return layout ? [this.createOpenLayoutState(layout)] : [];
            });
            let missingReferences = frames.length !== configuration.frames.length;
            for (const frame of frames) {
                const layout = byId.get(frame.layoutId);
                if (layout && (await this.restoreFramePreviewEntity(frame, layout))) {
                    missingReferences = true;
                }
            }
            return { openLayouts, frames, missingReferences };
        },

        async loadPreviewLanguageContext(
            salesChannelId: string | null,
        ): Promise<ExperienceStudioDetailState['previewLanguageContext']> {
            if (!salesChannelId) {
                return null;
            }
            const criteria = new Criteria(1, 1);
            criteria.addAssociation('languages');
            const salesChannel = await this.salesChannelRepository.get(
                salesChannelId as EntityKey<'sales_channel'>,
                Shopware.Context.api,
                criteria,
            );
            const languages = salesChannel?.languages?.filter((language) => language.active) ?? [];
            const defaultLanguage = languages.find((language) => language.id === salesChannel?.languageId) ?? languages[0];
            if (!defaultLanguage) {
                return null;
            }
            return {
                salesChannelId,
                defaultLanguageId: defaultLanguage.id,
                languageIds: languages.map((language) => language.id),
                languageNames: Object.fromEntries(languages.map((language) => [language.id, language.name] as const)),
                languageParents: Object.fromEntries(
                    languages.map((language) => [language.id, language.parentId ?? null] as const),
                ),
            };
        },

        applyPreviewLanguageContext(
            context: ExperienceStudioDetailState['previewLanguageContext'],
            frames?: ExperienceStudioPreviewFrame[],
        ): boolean {
            this.previewLanguageContext = context;
            if (!context) {
                return false;
            }
            Object.assign(this.languageParents, context.languageParents);
            let missingReference = false;
            (frames ?? this.frames).forEach((frame) => {
                if (!frame.languageId) {
                    frame.languageId = context.languageIds.includes(anchorLanguageId())
                        ? anchorLanguageId()
                        : context.defaultLanguageId;
                    return;
                }
                if (!context.languageIds.includes(frame.languageId)) {
                    frame.languageId = context.defaultLanguageId;
                    missingReference = true;
                }
            });
            return missingReference;
        },

        async refreshPreviewLanguages(): Promise<void> {
            const salesChannelId = this.previewSalesChannelId;
            const requestId = ++this.previewLanguageRequestSequence;
            if (this.previewLanguageContext?.salesChannelId === salesChannelId) {
                this.applyPreviewLanguageContext(this.previewLanguageContext);
                void this.scheduleWorkspaceSetup();
                return;
            }
            try {
                const context = await this.loadPreviewLanguageContext(salesChannelId);
                if (requestId !== this.previewLanguageRequestSequence || salesChannelId !== this.previewSalesChannelId) {
                    return;
                }
                if (salesChannelId && !context) {
                    this.previewSalesChannelId = this.previewLanguageContext?.salesChannelId ?? null;
                    this.createNotificationWarning({
                        message: this.$t('sw-experience-studio.workspace.manager.missingReferences'),
                    });
                    return;
                }
                this.applyPreviewLanguageContext(context);
                void this.scheduleWorkspaceSetup();
            } catch {
                if (requestId !== this.previewLanguageRequestSequence || salesChannelId !== this.previewSalesChannelId) {
                    return;
                }
                if (this.previewLanguageContext) {
                    this.previewSalesChannelId = this.previewLanguageContext.salesChannelId;
                }
                this.createNotificationError({
                    message: this.$t('sw-experience-studio.detail.toolbar.previewLanguageLoadError'),
                });
            }
        },

        async restoreFramePreviewEntity(item: ExperienceStudioPreviewFrame, layout: ContentLayoutEntity): Promise<boolean> {
            let missingReference = false;
            const entityType = this.resolvePreviewContext(layout)?.entityType;
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
            this.previewLanguageRequestSequence += 1;
            this.previewLanguageContext = null;
            if (this.diagnoseTimeoutId !== null) {
                window.clearTimeout(this.diagnoseTimeoutId);
            }
            this.diagnoseTimeoutId = null;
            this.latestDiagnoseRequestId = ++this.diagnoseRequestSequence;
            this.editorStore.reset();
            this.openLayouts = [];
            this.frames = [];
            this.activeFrameId = null;
            this.layout = null;
            this.selectedElementId = null;
            this.currentViewport = 'desktop';
            this.settingsPanelMode = 'element';
            this.previewEntityId = null;
            this.diagnostics = [];
            this.showDiagnostics = false;
            this.isLayoutPickerOpen = false;
            this.isElementPickerOpen = false;
            this.isAssignmentModalOpen = false;
            this.pendingCloseLayoutId = null;
        },

        async requestWorkspaceAction(action: WorkspaceAction): Promise<void> {
            const allowedActions = { open: true, create: this.allowSave, delete: this.allowDelete };
            if (!allowedActions[action.type]) {
                return;
            }
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

        onWorkspaceCreate(): void {
            if (!this.allowSave) {
                return;
            }
            this.isWorkspacePickerOpen = false;
            this.workspaceName = '';
            this.workspaceNameAction = 'create';
        },

        onWorkspaceRename(workspace: ContentWorkspace): void {
            if (!this.allowSave) {
                return;
            }
            this.isWorkspacePickerOpen = false;
            this.managedWorkspace = workspace;
            this.workspaceName = workspace.name;
            this.workspaceNameAction = 'rename';
        },

        onWorkspaceDelete(workspace: ContentWorkspace): void {
            if (!this.allowDelete) {
                return;
            }
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
            if (!this.allowDelete || !this.managedWorkspace) {
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
            if (!this.allowSave) {
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

        onFrameUndo(frameId: string): void {
            const frame = this.frames.find((item) => item.id === frameId);
            if (!frame || !this.allowSave || !this.editorStore.canUndoLayout(frame.layoutId)) {
                return;
            }
            this.activateFrame(frameId);
            this.onUndo();
            this.syncActiveLayoutState();
        },

        onFrameRedo(frameId: string): void {
            const frame = this.frames.find((item) => item.id === frameId);
            if (!frame || !this.allowSave || !this.editorStore.canRedoLayout(frame.layoutId)) {
                return;
            }
            this.activateFrame(frameId);
            this.onRedo();
            this.syncActiveLayoutState();
        },

        createOpenLayoutState(layout: ContentLayoutEntity, isNew = false): ExperienceStudioOpenLayout {
            return {
                id: layout.id,
                layout,
                savedState: this.serializeLayoutState(layout),
                isNew,
                isSaving: false,
                saveError: null,
            };
        },

        createPreviewFrame(layoutId: string, id = Shopware.Utils.createId()): ExperienceStudioPreviewFrame {
            return {
                id,
                layoutId,
                selectedElementId: null,
                viewport: 'desktop',
                previewEntityId: null,
                languageId:
                    this.previewLanguageContext?.salesChannelId === this.previewSalesChannelId
                        ? (this.previewLanguageContext?.defaultLanguageId ?? anchorLanguageId())
                        : anchorLanguageId(),
            };
        },

        layoutFrames(layoutId: string): ExperienceStudioPreviewFrame[] {
            return this.frames.filter((frame) => frame.layoutId === layoutId);
        },

        getLayoutFrameId(layoutId: string): string {
            return (
                this.frames.find((frame) => frame.layoutId === layoutId && frame.id === this.activeFrameId)?.id ??
                this.frames.find((frame) => frame.layoutId === layoutId)?.id ??
                ''
            );
        },

        previewFrameAriaLabel(frameId: string): string {
            const frame = this.frames.find((item) => item.id === frameId);
            const document = this.openLayouts.find((item) => item.id === frame?.layoutId);
            if (!frame || !document) {
                return '';
            }
            const viewportLabels: Record<Viewport, string> = {
                mobile: 'sw-experience-studio.detail.toolbar.viewportMobile',
                'tablet-landscape': 'sw-experience-studio.detail.toolbar.viewportTablet',
                desktop: 'sw-experience-studio.detail.toolbar.viewportDesktop',
                custom: 'sw-experience-studio.detail.canvas.customViewport',
            };
            return this.$t('sw-experience-studio.detail.canvas.previewLabel', {
                name: document.layout.name,
                viewport: this.$t(viewportLabels[frame.id === this.activeFrameId ? this.currentViewport : frame.viewport]),
                number: this.layoutFrames(frame.layoutId).findIndex((item) => item.id === frameId) + 1,
            });
        },

        async openAnotherPreview(sourceFrameId: string): Promise<void> {
            this.syncActiveLayoutState();
            const source = this.frames.find((frame) => frame.id === sourceFrameId);
            if (!source || this.isWorkspaceTransitioning) {
                return;
            }
            const frame = {
                ...this.createPreviewFrame(source.layoutId),
                viewport: source.viewport,
                previewEntityId: source.previewEntityId,
                languageId: source.languageId ?? anchorLanguageId(),
            };
            const canvas = this.$refs.experienceStudioCanvas as ExperienceStudioCanvasRef | undefined;
            canvas?.closeSettingsPanel();
            canvas?.copyFrameState(source.id, frame.id);
            this.frames.push(frame);
            this.activateFrame(frame.id);
            await this.loadDefaultPreviewEntity();
            await this.$nextTick();
            canvas?.fitCanvasToFrames();
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
            const frame = this.frames.find((item) => item.id === this.activeFrameId);

            if (!frame) {
                return;
            }

            frame.selectedElementId = this.selectedElementId;
            frame.viewport = this.currentViewport;
            frame.previewEntityId = this.previewEntityId;
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

                // Another open request may have completed while this layout was loading.
                if (this.openLayouts.some((item) => item.id === layoutId)) {
                    this.activateLayout(layoutId);
                    this.isLayoutPickerOpen = false;
                    return;
                }

                const isFirstOpenLayout = this.openLayouts.length === 0;
                this.openLayouts.push(this.createOpenLayoutState(layout));
                this.frames.push(this.createPreviewFrame(layout.id));
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
            this.activateFrame(this.getLayoutFrameId(layoutId));
        },

        activateFrame(frameId: string): void {
            const frame = this.frames.find((item) => item.id === frameId);
            const openLayout = this.openLayouts.find((item) => item.id === frame?.layoutId);

            if (!frame || !openLayout || this.activeFrameId === frameId) {
                return;
            }

            // Settings belong to the current activation, including previews of the same layout.
            this.selectedElementId = null;
            this.settingsPanelMode = 'element';
            frame.selectedElementId = null;
            this.syncActiveLayoutState();
            this.activeFrameId = frameId;
            this.layout = openLayout.layout;
            this.currentViewport = frame.viewport;
            this.previewEntityId = frame.previewEntityId;
            this.elementSettingsWidth = DEFAULT_ELEMENT_SETTINGS_WIDTH;
            const canvas = this.$refs.experienceStudioCanvas as ExperienceStudioCanvasRef | undefined;
            canvas?.closeSettingsPanel();
            this.editorStore.initialize(frame.layoutId);
            this.showDiagnostics = false;
            void this.diagnoseLayout();
        },

        deactivateFrame(): void {
            this.activeFrameId = null;
            this.layout = null;
            this.selectedElementId = null;
            this.settingsPanelMode = 'element';
            this.editorStore.activeLayoutId = null;
            this.diagnostics = [];
            this.showDiagnostics = false;
            const canvas = this.$refs.experienceStudioCanvas as ExperienceStudioCanvasRef | undefined;
            canvas?.closePanel();
        },

        openLayoutPicker(): void {
            this.isLayoutPickerOpen = true;
        },

        onLayoutPickerSelect(layoutId: string): void {
            const frameId = this.getLayoutFrameId(layoutId);
            if (frameId) {
                void this.openAnotherPreview(frameId);
                this.isLayoutPickerOpen = false;
                return;
            }
            void this.openLayoutById(layoutId);
        },

        onOpenCreateLayout(): void {
            if (!this.allowCreate) {
                return;
            }
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

        closeFrame(frameId: string): void {
            const frame = this.frames.find((item) => item.id === frameId);
            if (!frame) {
                return;
            }
            if (this.layoutFrames(frame.layoutId).length === 1) {
                this.closeLayout(frame.layoutId);
                return;
            }
            this.syncActiveLayoutState();
            this.frames = this.frames.filter((item) => item.id !== frameId);
            if (this.activeFrameId === frameId) {
                this.deactivateFrame();
            }
        },

        removeOpenLayout(layoutId: string): void {
            const index = this.openLayouts.findIndex((item) => item.id === layoutId);

            if (index === -1) {
                return;
            }

            this.openLayouts.splice(index, 1);
            this.frames = this.frames.filter((frame) => frame.layoutId !== layoutId);
            this.editorStore.removeLayout(layoutId);
            this.pendingCloseLayoutId = null;

            if (this.layout?.id === layoutId) {
                this.deactivateFrame();

                if (this.openLayouts.length === 0) {
                    this.isLayoutPickerOpen = this.isLayoutPickerOpen || !this.workspaceStore?.activeWorkspace;
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
            const hasRichText =
                this.settingsPanelMode === 'translations'
                    ? this.translationPanelHasRichText
                    : this.selectedElementHasRichText;
            this.setElementSettingsWidth(hasRichText ? RICH_TEXT_ELEMENT_SETTINGS_WIDTH : this.elementSettingsWidth);
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

        onFrameViewportChange(frameId: string, viewport: Viewport): void {
            const frame = this.frames.find((item) => item.id === frameId);

            if (!frame) {
                return;
            }

            this.activateFrame(frameId);
            frame.viewport = viewport;

            if (frameId === this.activeFrameId) {
                this.currentViewport = viewport;
            }
        },

        onSettingsPanelClose(): void {
            this.elementSettingsWidth = MIN_ELEMENT_SETTINGS_WIDTH;
            this.settingsPanelMode = 'element';
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
            const frame = this.frames.find((item) => item.id === this.activeFrameId);
            const workspaceId = this.workspaceStore?.activeWorkspace?.id;

            if (!frame || !previewEntityType) {
                return;
            }

            try {
                const repository = this.repositoryFactory.create(previewEntityType as keyof EntitySchema.EntityKeys);
                const entities = await repository.search(this.defaultPreviewEntityCriteria, Shopware.Context.api);
                const firstEntity = entities.first();

                if (
                    this.workspaceStore?.activeWorkspace?.id === workspaceId &&
                    this.frames.includes(frame) &&
                    !frame.previewEntityId &&
                    firstEntity?.id
                ) {
                    frame.previewEntityId = firstEntity.id;
                    if (this.activeFrameId === frame.id) {
                        this.previewEntityId = firstEntity.id;
                    }
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
            if (!this.allowCreate || !this.workspaceStore.activeWorkspace) {
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
            this.frames.push(this.createPreviewFrame(layout.id));
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

            if (salesChannelId === this.previewSalesChannelId) {
                void this.refreshPreviewLanguages();
            } else {
                this.previewSalesChannelId = salesChannelId;
            }
        },

        onFramePreviewEntityChange(frameId: string, entityId: string | null): void {
            const frame = this.frames.find((item) => item.id === frameId);

            if (!frame) {
                return;
            }

            this.activateFrame(frameId);
            frame.previewEntityId = entityId;

            if (frameId === this.activeFrameId) {
                this.previewEntityId = entityId;
            }
            if (this.workspaceStore?.activeWorkspace) {
                void this.scheduleWorkspaceSetup();
            }
        },

        onFrameLanguageChange(frameId: string, languageId: string | null): void {
            const frame = this.frames.find((item) => item.id === frameId);
            if (
                !frame ||
                !languageId ||
                this.previewLanguageContext?.salesChannelId !== this.previewSalesChannelId ||
                !this.previewLanguageContext?.languageIds.includes(languageId)
            ) {
                return;
            }
            frame.languageId = languageId;
            this.activateFrame(frameId);
            void this.scheduleWorkspaceSetup();
        },

        onFrameElementSelect(frameId: string, elementId: string | null): void {
            this.activateFrame(frameId);
            this.onElementSelect(elementId);
        },

        onFrameTranslate(frameId: string): void {
            this.activateFrame(frameId);
            if (this.activeFrameId !== frameId) {
                return;
            }
            this.settingsPanelMode = 'translations';
            this.elementSettingsWidth = DEFAULT_ELEMENT_SETTINGS_WIDTH;
            this.adjustElementSettingsWidth();
            const canvas = this.$refs.experienceStudioCanvas as ExperienceStudioCanvasRef | undefined;
            canvas?.openSettingsPanel(false);
        },

        onTreeElementSelect(elementId: string): void {
            this.onElementSelect(elementId);
            this.scrollActivePreviewToElement(elementId);
        },

        scrollActivePreviewToElement(elementId: string): void {
            if (!this.activeFrameId) {
                return;
            }
            const preview = this.$refs[`preview-${this.activeFrameId}`] as
                | { scrollToElement: (id: string) => void }
                | undefined;
            preview?.scrollToElement(elementId);
        },

        onElementSelect(elementId: string | null): void {
            const wasTranslations = this.settingsPanelMode === 'translations';
            const wasRichText = this.selectedElementHasRichText;
            this.selectedElementId = elementId;
            if (!wasTranslations && wasRichText && !this.selectedElementHasRichText) {
                this.elementSettingsWidth = DEFAULT_ELEMENT_SETTINGS_WIDTH;
            }
            this.syncActiveLayoutState();

            if (!elementId) {
                return;
            }

            this.settingsPanelMode = 'element';
            const canvas = this.$refs.experienceStudioCanvas as ExperienceStudioCanvasRef | undefined;
            if (wasTranslations) {
                this.elementSettingsWidth = DEFAULT_ELEMENT_SETTINGS_WIDTH;
                this.adjustElementSettingsWidth();
            }
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

            if (findElementLocation([draggedElement], payload.newParentElementId) !== null) {
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

        onElementSettingsChange(payload: { elementId: string; propertyKey: string; value: unknown }): void {
            const element = this.findElementById(payload.elementId);

            if (!element) {
                return;
            }

            this.writeElementPropertyValue(element, payload.propertyKey, payload.value);
        },

        /**
         * Translatable edits store explicit values for the active frame's language and preserve other entries.
         * Clearing text stores an empty string, which remains empty in the preview and Storefront.
         * Resetting a translation to inherited content is outside the current editing workflow.
         * Null is intentionally rejected because removing a language entry would restore rendering fallback.
         */
        writeElementPropertyValue(element: ContentElementNode, propertyKey: string, value: unknown): void {
            if (!this.isTranslatableProperty(element.component, propertyKey)) {
                this.applyLayoutMutation((layout) => {
                    return updateElementPropertiesInLayout(layout, element.id, { [propertyKey]: value }) ? {} : false;
                });

                return;
            }

            this.applyLayoutMutation((layout) => {
                const location = findElementLocation(layout, element.id);

                if (!location) {
                    return false;
                }

                if (typeof value !== 'string' && typeof value !== 'number' && typeof value !== 'boolean') {
                    this.notifyMutationError([]);

                    return false;
                }

                const languageMap = withLanguageEntry(
                    location.elements[location.index].properties?.[propertyKey],
                    this.activeLanguageId,
                    value,
                );

                return updateElementPropertiesInLayout(layout, element.id, { [propertyKey]: languageMap }) ? {} : false;
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

        async diagnoseLayout(
            targetLayout?: ContentLayoutEntity | null,
            options: { showDiagnostics?: boolean } = {},
        ): Promise<void> {
            const layout = targetLayout === undefined ? this.layout : targetLayout;
            const isActive = layout === this.layout;
            if (isActive && this.diagnoseTimeoutId !== null) {
                window.clearTimeout(this.diagnoseTimeoutId);
                this.diagnoseTimeoutId = null;
            }

            if (!layout) {
                this.diagnostics = [];

                return;
            }

            const requestId = isActive ? this.diagnoseRequestSequence + 1 : null;
            if (requestId !== null) {
                this.diagnoseRequestSequence = requestId;
                this.latestDiagnoseRequestId = requestId;
            }
            const isCurrentRequest = (): boolean => layout === this.layout && requestId === this.latestDiagnoseRequestId;

            try {
                const response = await this.draftMutationService().diagnose({
                    layout: cloneDeep(layout.layout),
                    rootSource: this.getLayoutRootSource(layout),
                });

                if (isCurrentRequest()) {
                    this.diagnostics = response.diagnostics.violations;
                    if (options.showDiagnostics) {
                        this.showDiagnostics = true;
                    }
                }
            } catch {
                if (isCurrentRequest()) {
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
            const frameId = this.activeFrameId;
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
                    this.activeFrameId === frameId
                        ? this.selectedElementId
                        : (this.frames.find((frame) => frame.id === frameId)?.selectedElementId ?? null);

                try {
                    const response = await this.requestDraftMutation(operation, currentLayout, operationPayload, rootSource);
                    if (!this.openLayouts.includes(openLayout)) {
                        return;
                    }

                    this.editorStore.pushToHistory(currentLayout, previousSelectedElementId, layout.id);
                    layout.layout = response.layout;
                    this.clearInvalidFrameSelections(layout);
                    if (this.layout !== layout) {
                        return;
                    }

                    if (this.activeFrameId === frameId) {
                        this.selectedElementId = resolveSelectedElementId(response);
                        this.syncActiveLayoutState();
                    }
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

        isOfferedOnRootSource(rootSources: string[] | null): boolean {
            if (rootSources === null) {
                return true;
            }

            const rootSource = this.resolveMutationRootSource();

            return rootSource !== null && rootSources.includes(rootSource);
        },

        isPresetAllowedForPayload(
            preset: ContentSystemLayoutPreset,
            payload: AddElementPayload,
            allowedComponents: Set<string>,
        ): boolean {
            if (!this.isOfferedOnRootSource(preset.defaultBindingRootSources)) {
                return false;
            }

            if (payload.parentElementId === null) {
                return true;
            }

            return preset.payload.every((rootElement) => allowedComponents.has(rootElement.component));
        },

        getAvailableTypesForPayload(payload: AddElementPayload | null): ContentSystemElementTypeSpecification[] {
            return this.getStructurallyAvailableTypesForPayload(payload).filter((type) =>
                this.isOfferedOnRootSource(type.defaultBindingRootSources),
            );
        },

        // Slot eligibility without the root source filter: a preset is judged on its own root sources, not its root type's.
        getStructurallyAvailableTypesForPayload(payload: AddElementPayload | null): ContentSystemElementTypeSpecification[] {
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

        findElementById(elementId: string): ContentElementNode | null {
            if (!this.layout) {
                return null;
            }

            const location = findElementLocation(this.layout.layout, elementId);

            if (!location) {
                return null;
            }

            return location.elements[location.index] ?? null;
        },

        isTranslatableProperty(component: string, propertyKey: string): boolean {
            const typeSpecification = this.elementTypeStore.getByName(component);

            return typeSpecification?.properties[propertyKey]?.translatable === true;
        },

        clearInvalidFrameSelections(layout: ContentLayoutEntity): void {
            for (const frame of this.frames.filter((item) => item.layoutId === layout.id)) {
                if (frame.selectedElementId && !findElementLocation(layout.layout, frame.selectedElementId)) {
                    frame.selectedElementId = null;
                }
            }
            if (
                this.layout === layout &&
                this.selectedElementId &&
                !findElementLocation(layout.layout, this.selectedElementId)
            ) {
                this.selectedElementId = null;
            }
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
            this.clearInvalidFrameSelections(this.layout);
            this.syncActiveLayoutState();
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
            this.clearInvalidFrameSelections(this.layout);
            this.syncActiveLayoutState();
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
            this.frames
                .filter((frame) => frame.layoutId === layoutId)
                .forEach((frame) => {
                    frame.selectedElementId = null;
                });
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

            if (!openLayout || openLayout.isSaving || !this.allowSave) {
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
                if (this.activeLayoutId === layoutId) {
                    this.showDiagnostics = false;
                }
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
                await this.diagnoseLayout(openLayout.layout, { showDiagnostics: true });

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
                this.clearInvalidFrameSelections(reloaded);
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
