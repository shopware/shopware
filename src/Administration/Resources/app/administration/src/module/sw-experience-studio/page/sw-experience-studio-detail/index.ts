import type Repository from 'src/core/data/repository.data';

import type { ContentElementNode } from 'src/module/sw-experience-studio/types/content-element.types';
import { getStorefrontSalesChannelCriteria } from 'src/module/sw-experience-studio/util/sales-channel-criteria.util';
import { castContentElementNodes } from 'src/module/sw-experience-studio/util/content-element-label.util';
import {
    duplicateElementInLayout,
    findElementLocation,
    removeElementFromLayout,
    sanitizeContentElementLayoutForWrite,
} from 'src/module/sw-experience-studio/util/content-element.util';
import 'src/module/sw-experience-studio/store/experience-studio-editor.store';
import template from './sw-experience-studio-detail.html.twig';
import './sw-experience-studio-detail.scss';

const { Mixin } = Shopware;
const { cloneDeep } = Shopware.Utils.object;

type Viewport = 'mobile' | 'tablet-landscape' | 'desktop';

type LayoutMutationResult = false | {
    selectedElementId?: string | null;
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
        historyKeydownHandler: ((event: KeyboardEvent) => void) | null;
    } {
        return {
            layout: null,
            isLoading: false,
            isSaveSuccessful: false,
            currentViewport: 'desktop',
            selectedElementId: null,
            previewSalesChannelId: null,
            historyKeydownHandler: null,
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

        allowSave(): boolean {
            return this.acl.can('experience_studio.editor');
        },

        isCreateMode(): boolean {
            return this.$route.name === 'sw.experience.studio.create';
        },

        editorStore() {
            return Shopware.Store.get('experienceStudioEditor');
        },

        canUndo(): boolean {
            return this.editorStore.canUndo;
        },

        canRedo(): boolean {
            return this.editorStore.canRedo;
        },
    },

    created(): void {
        Shopware.Store.get('adminMenu').collapseSidebar();
        this.historyKeydownHandler = (event: KeyboardEvent): void => {
            this.onHistoryKeydown(event);
        };
        void this.loadLayout();
        void this.loadDefaultPreviewSalesChannel();
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
                this.layout = await this.layoutRepository.get(this.layoutId, Shopware.Context.api);
            }

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

            const salesChannels = await this.salesChannelRepository.search(
                this.defaultSalesChannelCriteria,
                Shopware.Context.api,
            );
            const firstSalesChannel = salesChannels.first();

            if (firstSalesChannel) {
                this.previewSalesChannelId = firstSalesChannel.id;
            }
        },

        onPreviewSalesChannelChange(salesChannelId: string | null): void {
            if (!salesChannelId) {
                return;
            }

            this.previewSalesChannelId = salesChannelId;
        },

        onSelectElement(elementId: string | null): void {
            this.selectedElementId = elementId;
        },

        onAddElement(): void {
            // Element insertion will be implemented in a follow-up step.
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
            this.layout = await this.layoutRepository.get(layout.id, Shopware.Context.api);

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
