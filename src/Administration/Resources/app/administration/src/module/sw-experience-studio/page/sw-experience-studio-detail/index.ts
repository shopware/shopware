import type Repository from 'src/core/data/repository.data';
import type { ContentSystemElementTypeSpecification } from 'src/core/service/api/content-system-element-type.api.service';
import type {
    ContentLayoutDraftDuplicatePayload,
    ContentLayoutDraftInsertPayload,
    ContentLayoutDraftInsertPresetPayload,
    ContentLayoutDraftMovePayload,
    ContentLayoutDraftMutationResponse,
    ContentLayoutDraftRemovePayload,
} from 'src/core/service/api/content-system-layout-draft-mutation.api.service';
import type { ContentSystemLayoutPreset } from 'src/core/service/api/content-system-layout-preset.api.service';
import type { ExperienceStudioElementTypeStore } from 'src/module/sw-experience-studio/store/experience-studio-element-type.store';
import type { ExperienceStudioLayoutPresetStore } from 'src/module/sw-experience-studio/store/experience-studio-layout-preset.store';
import type { ExperienceStudioStyleOptionStore } from 'src/module/sw-experience-studio/store/experience-studio-style-option.store';

import type { ContentElementNode } from 'src/core/service/content-element.types';
import { getStorefrontSalesChannelCriteria } from 'src/module/sw-experience-studio/util/sales-channel-criteria.util';
import type {
    ContentLayoutEntity,
    ContentLayoutRepository,
} from 'src/module/sw-experience-studio/util/content-layout-repository.util';
import { createContentLayoutRepository } from 'src/module/sw-experience-studio/util/content-layout-repository.util';
import {
    applyResolvedContextConsumers,
    findElementLocation,
    updateElementPropertiesInLayout,
    updateElementStyleInLayout,
} from 'src/module/sw-experience-studio/util/content-element.util';
import 'src/module/sw-experience-studio/store/experience-studio-editor.store';
import 'src/module/sw-experience-studio/store/experience-studio-element-type.store';
import 'src/module/sw-experience-studio/store/experience-studio-layout-preset.store';
import 'src/module/sw-experience-studio/store/experience-studio-style-option.store';
import template from './sw-experience-studio-detail.html.twig';
import './sw-experience-studio-detail.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { cloneDeep } = Shopware.Utils.object;

type Viewport = 'mobile' | 'tablet-landscape' | 'desktop';

type LayoutMutationResult =
    | false
    | {
          selectedElementId?: string | null;
      };

type AddElementPayload = {
    parentElementId: string | null;
    slotName: string | null;
    anchorTop: number;
    anchorLeft: number;
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

type InlineEditSession = {
    elementId: string;
    originalValue: string;
    draftValue: string;
    isEditing: boolean;
} | null;

type DraftMutationOperation = 'insert' | 'remove' | 'duplicate' | 'move' | 'insert-preset';

type ContentSystemLayoutDraftMutationService = {
    insertElement: (payload: ContentLayoutDraftInsertPayload) => Promise<ContentLayoutDraftMutationResponse>;
    removeElement: (payload: ContentLayoutDraftRemovePayload) => Promise<ContentLayoutDraftMutationResponse>;
    duplicateElement: (payload: ContentLayoutDraftDuplicatePayload) => Promise<ContentLayoutDraftMutationResponse>;
    moveElement: (payload: ContentLayoutDraftMovePayload) => Promise<ContentLayoutDraftMutationResponse>;
    insertPreset: (payload: ContentLayoutDraftInsertPresetPayload) => Promise<ContentLayoutDraftMutationResponse>;
};

type ContentSystemEntityTypeService = {
    getEntityTypes: () => Promise<string[]>;
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

    data(): {
        layout: ContentLayoutEntity | null;
        isLoading: boolean;
        isSaveSuccessful: boolean;
        currentViewport: Viewport;
        selectedElementId: string | null;
        previewSalesChannelId: string | null;
        previewEntityId: string | null;
        historyKeydownHandler: ((event: KeyboardEvent) => void) | null;
        isElementPickerOpen: boolean;
        pendingAddElementPayload: AddElementPayload | null;
        pickerTop: number;
        pickerLeft: number;
        inlineEditSession: InlineEditSession;
        mutationRequestSequence: number;
        latestMutationRequestId: number;
        availableLayoutTypes: string[];
        isLoadingLayoutTypes: boolean;
        layoutTypeLoadError: string | null;
        createWizardName: string;
        createWizardSelectedType: string | null;
    } {
        return {
            layout: null,
            isLoading: false,
            isSaveSuccessful: false,
            currentViewport: 'desktop',
            selectedElementId: null,
            previewSalesChannelId: null,
            previewEntityId: null,
            historyKeydownHandler: null,
            isElementPickerOpen: false,
            pendingAddElementPayload: null,
            pickerTop: 0,
            pickerLeft: 0,
            inlineEditSession: null,
            mutationRequestSequence: 0,
            latestMutationRequestId: 0,
            availableLayoutTypes: [],
            isLoadingLayoutTypes: false,
            layoutTypeLoadError: null,
            createWizardName: '',
            createWizardSelectedType: null,
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
            return this.$route.params.id as string;
        },

        layoutRootSource(): string | null {
            return this.getLayoutRootSource(this.layout);
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

        isInlineEditing(): boolean {
            return this.inlineEditSession?.isEditing ?? false;
        },
    },

    created(): void {
        Shopware.Store.get('adminMenu').collapseSidebar();
        this.historyKeydownHandler = (event: KeyboardEvent): void => {
            this.onHistoryKeydown(event);
        };
        void this.loadLayout();
        void this.loadDefaultPreviewSalesChannel();
        void this.loadElementTypes();
        void this.loadStyleOptions();
        void this.loadLayoutPresets();
        void this.loadLayoutTypes();
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

        this.editorStore.reset();
    },

    methods: {
        async loadLayout(): Promise<void> {
            this.isLoading = true;

            if (this.isCreateMode) {
                this.layout = this.layoutRepository.create(Shopware.Context.api);
                this.layout.id = this.layoutId;
                this.layout.name = '';
                this.layout.version = '1.0.0';
                this.layout.layout = [];
            } else {
                this.layout = await this.layoutRepository.get(this.layoutId, Shopware.Context.api, this.layoutLoadCriteria);
            }

            this.createWizardName = this.layout?.name ?? '';
            this.createWizardSelectedType = this.layoutRootSource;
            this.applyPreviewContextDefaults();
            await this.loadDefaultPreviewEntity();
            this.editorStore.initialize(this.layoutId);
            this.isLoading = false;
        },

        onClickBack(): void {
            void this.$router.push({ name: 'sw.experience.studio.index' });
        },

        onViewportChange(viewport: Viewport): void {
            this.currentViewport = viewport;
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
        },

        onSelectElement(elementId: string | null): void {
            this.selectedElementId = elementId;
        },

        onInlineEditStart(payload: { elementId: string }): void {
            const element = this.findElementById(payload.elementId);

            if (!this.isTextElement(element)) {
                return;
            }

            const currentValue = this.getElementTextValue(element);
            this.selectedElementId = payload.elementId;
            this.inlineEditSession = {
                elementId: payload.elementId,
                originalValue: currentValue,
                draftValue: currentValue,
                isEditing: true,
            };
        },

        onInlineEditChange(payload: { elementId: string; value: string }): void {
            if (!this.inlineEditSession || this.inlineEditSession.elementId !== payload.elementId) {
                return;
            }

            const normalizedValue = payload.value.trim();

            this.inlineEditSession = {
                ...this.inlineEditSession,
                draftValue: normalizedValue,
            };
        },

        onInlineEditCommit(payload: { elementId: string; value: string }): void {
            if (!this.inlineEditSession || this.inlineEditSession.elementId !== payload.elementId) {
                return;
            }

            const normalizedValue = payload.value.trim();
            const session = this.inlineEditSession;
            this.clearInlineEditSession();

            if (normalizedValue === session.originalValue) {
                return;
            }

            this.applyLayoutMutation((layout) => {
                return updateElementPropertiesInLayout(layout, payload.elementId, { text: normalizedValue }) ? {} : false;
            });
        },

        onInlineEditCancel(payload: { elementId: string }): void {
            if (!this.inlineEditSession || this.inlineEditSession.elementId !== payload.elementId) {
                return;
            }

            this.clearInlineEditSession();
        },

        onAddElement(payload: AddElementPayload): void {
            this.pendingAddElementPayload = payload;
            this.pickerTop = payload.anchorTop - 8;
            this.pickerLeft = payload.anchorLeft + 26;
            this.isElementPickerOpen = true;
        },

        onCloseElementPicker(): void {
            this.isElementPickerOpen = false;
            this.pendingAddElementPayload = null;
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

            const layoutElements = this.layout ? this.layout.layout : [];
            const insertPayload: Omit<ContentLayoutDraftInsertPayload, 'layout' | 'rootSource'> = {
                type: component,
            };

            if (payload.parentElementId !== null) {
                insertPayload.parentElementId = payload.parentElementId;
                insertPayload.slot = payload.slotName;
            }

            await this.executeStructuralDraftMutation(
                'insert',
                layoutElements,
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
                this.layout.layout,
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

            if (result.selectedElementId !== undefined) {
                this.selectedElementId = result.selectedElementId;
            }
        },

        async onDuplicateElement(elementId: string): Promise<void> {
            if (!this.layout || !this.allowSave) {
                return;
            }

            const layoutElements = this.layout.layout;

            await this.executeStructuralDraftMutation(
                'duplicate',
                layoutElements,
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

            const layoutElements = this.layout.layout;

            await this.executeStructuralDraftMutation(
                'remove',
                layoutElements,
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
                layoutElements,
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

        draftMutationService(): ContentSystemLayoutDraftMutationService {
            return Shopware.Service('contentSystemLayoutDraftMutationService') as ContentSystemLayoutDraftMutationService;
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

            return service.duplicateElement(payload as ContentLayoutDraftDuplicatePayload);
        },

        async executeStructuralDraftMutation(
            operation: DraftMutationOperation,
            currentLayout: ContentElementNode[],
            operationPayload: Record<string, unknown>,
            resolveSelectedElementId: (response: ContentLayoutDraftMutationResponse) => string | null,
        ): Promise<void> {
            if (!this.layout || !this.allowSave) {
                return;
            }

            const requestId = this.mutationRequestSequence + 1;
            this.mutationRequestSequence = requestId;
            this.latestMutationRequestId = requestId;
            this.isLoading = true;

            const previousSelectedElementId = this.selectedElementId;

            try {
                const response = await this.requestDraftMutation(operation, currentLayout, operationPayload);

                if (requestId !== this.latestMutationRequestId) {
                    return;
                }

                this.editorStore.pushToHistory(currentLayout, previousSelectedElementId);
                applyResolvedContextConsumers(response.layout, response.resolutions);
                this.layout.layout = response.layout;
                this.selectedElementId = resolveSelectedElementId(response);
            } catch (error) {
                if (requestId !== this.latestMutationRequestId) {
                    return;
                }

                this.notifyMutationError(this.extractMutationErrorCodes(error));
            } finally {
                if (requestId === this.latestMutationRequestId) {
                    this.isLoading = false;
                }
            }
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

        clearInlineEditSession(): void {
            this.inlineEditSession = null;
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

        isTextElement(element: ContentElementNode | null): boolean {
            if (!element) {
                return false;
            }

            const typeSpecification = this.elementTypeStore.getByName(element.component);

            if (!typeSpecification) {
                return false;
            }

            if (typeSpecification.name.endsWith(':text')) {
                return true;
            }

            return typeSpecification.properties.text?.adminUI?.component === 'text-editor';
        },

        getElementTextValue(element: ContentElementNode | null): string {
            if (!element) {
                return '';
            }

            return typeof element.properties?.text === 'string' ? element.properties.text : '';
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

            await this.layoutRepository.save(layout, Shopware.Context.api);
            this.layout = await this.layoutRepository.get(layout.id, Shopware.Context.api, this.layoutLoadCriteria);
            this.applyPreviewContextDefaults();

            this.createNotificationSuccess({
                message: this.$t('sw-experience-studio.detail.messageSaved'),
            });

            if (this.isCreateMode) {
                void this.$router.push({
                    name: 'sw.experience.studio.detail',
                    params: { id: layout.id },
                });
            }

            this.isLoading = false;
        },
    },
});
