import type Repository from 'src/core/data/repository.data';
import type { ContentSystemElementTypeSpecification } from 'src/core/service/api/content-system-element-type.api.service';
import type { ExperienceStudioElementTypeStore } from 'src/module/sw-experience-studio/store/experience-studio-element-type.store';

import type { ContentElementNode } from 'src/module/sw-experience-studio/types/content-element.types';
import { getStorefrontSalesChannelCriteria } from 'src/module/sw-experience-studio/util/sales-channel-criteria.util';
import { castContentElementNodes } from 'src/module/sw-experience-studio/util/content-element-label.util';
import {
    duplicateElementInLayout,
    findElementLocation,
    removeElementFromLayout,
    sanitizeContentElementLayoutForWrite,
    updateElementPropertiesInLayout,
} from 'src/module/sw-experience-studio/util/content-element.util';
import 'src/module/sw-experience-studio/store/experience-studio-editor.store';
import 'src/module/sw-experience-studio/store/experience-studio-element-type.store';
import template from './sw-experience-studio-detail.html.twig';
import './sw-experience-studio-detail.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { cloneDeep } = Shopware.Utils.object;
const { createId } = Shopware.Utils;

type Viewport = 'mobile' | 'tablet-landscape' | 'desktop';

type LayoutMutationResult = false | {
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
};

type LayoutPreviewContext = {
    entityType: 'product' | 'category' | 'landing_page';
    entityId: string;
    salesChannelId: string | null;
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
        layout: Entity<'content_layout'> | null;
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
        };
    },

    computed: {
        layoutRepository(): Repository<'content_layout'> {
            return this.repositoryFactory.create('content_layout');
        },

        salesChannelRepository(): Repository<'sales_channel'> {
            return this.repositoryFactory.create('sales_channel');
        },

        defaultSalesChannelCriteria() {
            return getStorefrontSalesChannelCriteria(1);
        },

        layoutId(): string {
            return this.$route.params.id as string;
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

        editorStore() {
            return Shopware.Store.get('experienceStudioEditor');
        },

        elementTypeStore() {
            return Shopware.Store.get('experienceStudioElementType' as never) as ExperienceStudioElementTypeStore;
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

            const layoutElements = castContentElementNodes(this.layout.layout);
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
            const availableTypes = this.getAvailableTypesForPayload(this.pendingAddElementPayload);

            return availableTypes.map((typeSpecification) => ({
                name: typeSpecification.name,
                label: typeSpecification.label,
                icon: typeSpecification.icon,
            }));
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
                this.layout = await this.layoutRepository.get(
                    this.layoutId,
                    Shopware.Context.api,
                    this.layoutLoadCriteria,
                );
            }

            this.applyPreviewContextDefaults();
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

        applyPreviewContextDefaults(): void {
            const resolvedPreviewContext = this.resolvedPreviewContext;

            if (!this.previewEntityId && resolvedPreviewContext?.entityId) {
                this.previewEntityId = resolvedPreviewContext.entityId;
            }

            if (!this.previewSalesChannelId && resolvedPreviewContext?.salesChannelId) {
                this.previewSalesChannelId = resolvedPreviewContext.salesChannelId;
            }
        },

        getFirstAssociationEntry(
            association: unknown,
        ): Record<string, unknown> | null {
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

        resolvePreviewContext(layout: Entity<'content_layout'> | null): LayoutPreviewContext | null {
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

        async loadElementTypes(): Promise<void> {
            await this.elementTypeStore.loadTypes();
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

        onSelectElementType(component: string): void {
            const payload = this.pendingAddElementPayload;

            if (!payload) {
                this.onCloseElementPicker();
                return;
            }

            this.applyLayoutMutation((layout) => {
                const newElement = this.createElementFromType(component);

                if (payload.parentElementId === null) {
                    return this.insertRootElement(layout, newElement);
                }

                if (!payload.slotName) {
                    return false;
                }

                return this.insertSlotElement(layout, payload, component, newElement);
            });

            this.onCloseElementPicker();
        },

        applyLayoutMutation(
            mutator: (layout: ContentElementNode[]) => LayoutMutationResult,
        ): void {
            if (!this.layout || !this.allowSave) {
                return;
            }

            const layoutElements = castContentElementNodes(this.layout.layout);
            const workingLayout = cloneDeep(layoutElements);
            const result = mutator(workingLayout);

            if (result === false) {
                return;
            }

            this.editorStore.pushToHistory(layoutElements, this.selectedElementId);
            this.layout.layout = sanitizeContentElementLayoutForWrite(workingLayout);

            if (result.selectedElementId !== undefined) {
                this.selectedElementId = result.selectedElementId;
            }
        },

        onDuplicateElement(elementId: string): void {
            this.applyLayoutMutation((layout) => {
                const result = duplicateElementInLayout(layout, elementId);

                if (!result) {
                    return false;
                }

                return {
                    selectedElementId: result.duplicatedId,
                };
            });
        },

        onDeleteElement(elementId: string): void {
            this.applyLayoutMutation((layout) => {
                if (!removeElementFromLayout(layout, elementId)) {
                    return false;
                }

                if (
                    this.selectedElementId !== null
                    && findElementLocation(layout, this.selectedElementId) === null
                ) {
                    return {
                        selectedElementId: null,
                    };
                }

                return {};
            });
        },

        onElementSettingsChange(payload: {
            elementId: string;
            properties: Record<string, unknown>;
        }): void {
            this.applyLayoutMutation((layout) => {
                return updateElementPropertiesInLayout(layout, payload.elementId, payload.properties) ? {} : false;
            });
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

            const parentLocation = findElementLocation(castContentElementNodes(this.layout.layout), payload.parentElementId);
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
        ): boolean {
            const parentType = this.elementTypeStore.getByName(parentComponent);
            const slotDefinition = parentType?.slots.find((slot) => slot.name === slotName);

            if (!slotDefinition) {
                return true;
            }

            const existingElements = parentElement.slots?.[slotName] ?? [];

            if (slotDefinition.maxElements !== null && existingElements.length >= slotDefinition.maxElements) {
                return false;
            }

            if (slotDefinition.allowList.length === 0) {
                return true;
            }

            return slotDefinition.allowList.includes(childComponent);
        },

        createElementFromType(component: string): ContentElementNode {
            const typeSpecification = this.elementTypeStore.getByName(component);
            const properties: Record<string, unknown> = {};

            if (typeSpecification) {
                for (const [key, property] of Object.entries(typeSpecification.properties)) {
                    if (property.default !== null && property.default !== undefined) {
                        properties[key] = property.default;
                    }
                }
            }

            const slots = typeSpecification?.slots.reduce<Record<string, ContentElementNode[]>>((acc, slot) => {
                acc[slot.name] = [];

                return acc;
            }, {});

            return {
                id: createId(),
                component,
                properties,
                ...(slots && Object.keys(slots).length > 0 ? { slots } : {}),
            };
        },

        insertRootElement(layout: ContentElementNode[], newElement: ContentElementNode): LayoutMutationResult {
            layout.push(newElement);

            return {
                selectedElementId: newElement.id,
            };
        },

        insertSlotElement(
            layout: ContentElementNode[],
            payload: AddElementPayload,
            component: string,
            newElement: ContentElementNode,
        ): LayoutMutationResult {
            const parentLocation = findElementLocation(layout, payload.parentElementId as string);

            if (!parentLocation || !payload.slotName) {
                return false;
            }

            const parentElement = parentLocation.elements[parentLocation.index];

            if (!parentElement) {
                return false;
            }

            if (!this.canInsertIntoSlot(parentElement.component, payload.slotName, component, parentElement)) {
                this.createNotificationInfo({
                    message: this.$t('sw-experience-studio.detail.sidebarTree.addElementNotAllowed'),
                });

                return false;
            }

            parentElement.slots = parentElement.slots ?? {};
            parentElement.slots[payload.slotName] = parentElement.slots[payload.slotName] ?? [];
            parentElement.slots[payload.slotName].push(newElement);

            return {
                selectedElementId: newElement.id,
            };
        },

        onUndo(): void {
            if (!this.layout || !this.canUndo) {
                return;
            }

            const layoutElements = castContentElementNodes(this.layout.layout);
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

            const layoutElements = castContentElementNodes(this.layout.layout);
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
                target instanceof HTMLInputElement
                || target instanceof HTMLTextAreaElement
                || (target instanceof HTMLElement && target.isContentEditable)
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

            const layout = this.layout;

            this.isLoading = true;

            await this.layoutRepository.save(layout, Shopware.Context.api);
            this.layout = await this.layoutRepository.get(
                layout.id,
                Shopware.Context.api,
                this.layoutLoadCriteria,
            );
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
