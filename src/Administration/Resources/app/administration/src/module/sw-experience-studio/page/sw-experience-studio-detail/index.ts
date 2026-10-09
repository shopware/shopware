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
    category: string | null;
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
    fitCanvasToFrame: () => void;
    fitCanvasToFrames: () => void;
    focusFrame: (layoutId: string) => void;
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
const MIN_PREVIEW_WIDTH = 320;

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

    data(): {
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
    } {
        return {
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
            return this.activeLayoutId ?? (this.$route.params.id as string | undefined) ?? '';
        },

        isWorkspaceMode(): boolean {
            return this.$route.name !== 'sw.experience.studio.create';
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

        layoutRootSource(): string | null {
            return this.getLayoutRootSource(this.layout);
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

        isCreateMode(): boolean {
            return this.$route.name === 'sw.experience.studio.create';
        },

        showCreateWizard(): boolean {
            return this.isCreateMode && !this.hasCreateLayoutMetadata;
        },

        hasCreateLayoutMetadata(): boolean {
            return Boolean(this.layoutRootSource && this.layout?.name?.trim().length);
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
                category: typeSpecification.category,
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
                    category: 'presets',
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
        if (this.isWorkspaceMode) {
            this.isLayoutPickerOpen = this.openLayouts.length === 0;

            if (this.$route.params.id) {
                void this.loadLayout();
            }
        } else {
            void this.loadLayout();
        }
        void this.loadDefaultPreviewSalesChannel();
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

    beforeUnmount(): void {
        if (this.historyKeydownHandler) {
            document.removeEventListener('keydown', this.historyKeydownHandler);
        }

        this.stopElementSettingsResize();

        this.editorStore.reset();
    },

    methods: {
        async loadLayout(): Promise<void> {
            this.isLoading = true;
            this.showDiagnostics = false;

            if (this.isCreateMode) {
                this.layout = this.layoutRepository.create(Shopware.Context.api);
                this.layout.id = this.layoutId;
                this.layout.name = '';
                this.layout.version = '1.0.0';
                this.layout.layout = [];
            } else {
                const layoutId = this.$route.params.id as string | undefined;

                if (!layoutId) {
                    this.isLoading = false;
                    this.isLayoutPickerOpen = true;

                    return;
                }

                this.layout = await this.layoutRepository.get(layoutId, Shopware.Context.api, this.layoutLoadCriteria);
            }

            if (!this.layout) {
                this.isLoading = false;

                return;
            }

            this.activeLayoutId = this.layout.id;
            this.openLayouts = [this.createOpenLayoutState(this.layout, this.isCreateMode)];
            this.createWizardName = this.layout?.name ?? '';
            this.createWizardSelectedType = this.layoutRootSource;
            this.applyPreviewContextDefaults();
            await this.loadDefaultPreviewEntity();
            this.editorStore.initialize(this.layoutId);
            this.syncActiveLayoutState();
            this.isLoading = false;
            void this.diagnoseLayout();
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

            this.syncActiveLayoutState();
            this.activeLayoutId = layoutId;
            this.layout = openLayout.layout;
            this.selectedElementId = openLayout.selectedElementId;
            this.currentViewport = openLayout.viewport;
            this.previewEntityId = openLayout.previewEntityId;
            this.elementSettingsWidth = openLayout.settingsWidth;
            this.editorStore.initialize(layoutId);
            this.showDiagnostics = false;
            void this.diagnoseLayout();
        },

        focusLayout(layoutId: string): void {
            const canvas = this.$refs.experienceStudioCanvas as ExperienceStudioCanvasRef | undefined;
            canvas?.focusFrame(layoutId);
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
                    this.isLayoutPickerOpen = true;
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

        onClickBack(): void {
            void this.$router.push({ name: 'sw.experience.studio.index' });
        },

        adjustElementSettingsWidth(): void {
            if (this.selectedElementHasRichText) {
                this.elementSettingsWidth = RICH_TEXT_ELEMENT_SETTINGS_WIDTH;
            }
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

            return rootSource === 'product' || rootSource === 'category';
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

            const workspace = this.$refs.workspace as HTMLElement | undefined;
            const workspaceWidth = workspace?.getBoundingClientRect().width ?? Number.POSITIVE_INFINITY;
            const maxWidth = Math.min(MAX_ELEMENT_SETTINGS_WIDTH, workspaceWidth - 280 - MIN_PREVIEW_WIDTH);
            const width = this.resizeStartWidth - (event.clientX - this.resizeStartX);

            this.elementSettingsWidth = Math.max(MIN_ELEMENT_SETTINGS_WIDTH, Math.min(maxWidth, width));
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

        async loadDefaultPreviewSalesChannel(): Promise<void> {
            if (this.previewSalesChannelId) {
                return;
            }

            const contextSalesChannelId = this.resolvedPreviewContext?.salesChannelId ?? null;

            if (contextSalesChannelId) {
                this.previewSalesChannelId = contextSalesChannelId;

                return;
            }

            const salesChannels = await this.salesChannelRepository.search(
                this.defaultSalesChannelCriteria,
                Shopware.Context.api,
            );
            const firstSalesChannel = salesChannels.first();

            if (firstSalesChannel) {
                this.previewSalesChannelId = firstSalesChannel.id;
            }
        },

        async loadDefaultPreviewEntity(): Promise<void> {
            if (this.previewEntityId) {
                return;
            }

            const previewEntityType = this.previewEntityType;

            if (!previewEntityType) {
                return;
            }

            try {
                const repository = this.repositoryFactory.create(previewEntityType as keyof EntitySchema.Entities);
                const entities = await repository.search(this.defaultPreviewEntityCriteria, Shopware.Context.api);
                const firstEntity = entities.first();

                if (!this.previewEntityId && firstEntity?.id) {
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

        onCreateWizardCancel(): void {
            this.onClickBack();
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
            if (this.isWorkspaceMode) {
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

                return;
            }

            if (!this.layout) {
                return;
            }

            this.layout.name = payload.name;
            this.layout.rootSource = payload.type;
            this.createWizardName = payload.name;
            this.createWizardSelectedType = payload.type;
            this.previewEntityId = null;
            void this.loadDefaultPreviewEntity();
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

        onPreviewEntityIdChange(entityId: string | null): void {
            this.previewEntityId = entityId;
            this.syncActiveLayoutState();
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

            void this.diagnoseLayout();
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
            projection?: string | null;
        }): Promise<void> {
            if (!this.layout) {
                return;
            }

            const rootSource = this.resolveMutationRootSource();
            if (payload.source !== null && rootSource === null) {
                this.notifyMutationError(['CONTENT_SYSTEM__UNKNOWN_ROOT_SOURCE']);

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
                }
            }
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
            const structuralErrorCodes = new Set([
                'CONTENT_SYSTEM__MUTATION_TARGET_NOT_FOUND',
                'CONTENT_SYSTEM__MUTATION_CYCLE',
                'CONTENT_SYSTEM__MUTATION_SLOT_REQUIRED',
                'CONTENT_SYSTEM__MUTATION_INVALID_WRAP_TARGETS',
                'CONTENT_SYSTEM__MUTATION_UNKNOWN_TYPE',
                'CONTENT_SYSTEM__INVALID_LAYOUT_STRUCTURE',
                'CONTENT_SYSTEM__UNKNOWN_ROOT_SOURCE',
            ]);

            if (codes.some((code) => structuralErrorCodes.has(code))) {
                this.createNotificationError({
                    message:
                        'The layout edit is not valid in the current structure. Please review your change and try again.',
                });

                return;
            }

            this.createNotificationError({
                message: 'The layout edit failed. Please try again.',
            });
        },

        createDraftMutationPayload(
            layout: ContentElementNode[],
            operationPayload: Record<string, unknown>,
        ): Record<string, unknown> {
            return {
                // Working-tree layout data crossing an outbound boundary is cloned at the call site.
                layout: cloneDeep(layout),
                rootSource: this.resolveMutationRootSource(),
                ...operationPayload,
            };
        },

        async requestDraftMutation(
            operation: DraftMutationOperation,
            layout: ContentElementNode[],
            operationPayload: Record<string, unknown>,
        ): Promise<ContentLayoutDraftMutationResponse> {
            const service = this.draftMutationService();
            const payload = this.createDraftMutationPayload(layout, operationPayload);

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

            this.pendingMutationCount += 1;
            this.isLoading = true;

            const mutation = this.mutationQueue.then(async () => {
                if (!this.layout || !this.allowSave) {
                    return;
                }

                const currentLayout = cloneDeep(this.layout.layout);
                const previousSelectedElementId = this.selectedElementId;

                try {
                    const response = await this.requestDraftMutation(operation, currentLayout, operationPayload);

                    this.editorStore.pushToHistory(currentLayout, previousSelectedElementId);
                    this.layout.layout = response.layout;
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
            void this.diagnoseLayout();
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
            void this.diagnoseLayout();
        },

        onHistoryKeydown(event: KeyboardEvent): void {
            if (!this.allowSave) {
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

        async onSave(): Promise<void> {
            if (this.isWorkspaceMode) {
                await this.saveAllOpenLayouts();
                return;
            }

            if (!this.layout || !this.allowSave) {
                return;
            }

            if (!this.layout.name?.trim() || !this.layoutRootSource) {
                this.createNotificationWarning({
                    message: this.$t('sw-experience-studio.createWizard.missingFields'),
                });

                return;
            }

            const layout = this.layout;
            // Working-tree layout data crossing an outbound boundary is cloned at the call site.
            layout.layout = cloneDeep(layout.layout);

            this.isLoading = true;

            try {
                await this.layoutRepository.save(layout, Shopware.Context.api);
            } catch (error) {
                this.notifySaveError(error);
                await this.diagnoseLayout();
                this.showDiagnostics = true;
                this.isLoading = false;

                return;
            }

            this.createNotificationSuccess({
                message: this.$t('sw-experience-studio.detail.messageSaved'),
            });

            try {
                this.layout = await this.layoutRepository.get(layout.id, Shopware.Context.api, this.layoutLoadCriteria);
                this.applyPreviewContextDefaults();
                this.showDiagnostics = false;
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-experience-studio.detail.messageReloadError'),
                });
            } finally {
                this.isLoading = false;
            }

            if (this.isCreateMode) {
                void this.$router.push({
                    name: 'sw.experience.studio.detail',
                    params: { id: layout.id },
                });
            }
        },

        async saveAllOpenLayouts(): Promise<void> {
            if (!this.allowSave || this.isSavingAll) {
                return;
            }

            this.syncActiveLayoutState();
            const dirtyLayouts = this.openLayouts.filter((openLayout) => this.isLayoutDirty(openLayout));

            if (dirtyLayouts.length === 0) {
                return;
            }

            this.isSavingAll = true;
            await Promise.all(dirtyLayouts.map((openLayout) => this.saveOpenLayout(openLayout.id)));
            this.isSavingAll = false;

            const failures = this.openLayouts.filter((item) => item.saveError);

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
                await this.layoutRepository.save(openLayout.layout, Shopware.Context.api);
                openLayout.savedState = this.serializeLayoutState(openLayout.layout);
                openLayout.isNew = false;

                return true;
            } catch (error) {
                const detail = this.extractApiErrorDetail(error);
                openLayout.saveError = detail ?? this.$t('sw-experience-studio.detail.messageSaveError');

                return false;
            } finally {
                openLayout.isSaving = false;
            }
        },

        // Resolvability is a write-time gate, so a draft that previews cleanly can still be refused on save.
        notifySaveError(error: unknown): void {
            const detail = this.extractApiErrorDetail(error);

            this.createNotificationError({
                message: detail
                    ? this.$t('sw-experience-studio.detail.messageSaveErrorDetail', { detail })
                    : this.$t('sw-experience-studio.detail.messageSaveError'),
            });
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
