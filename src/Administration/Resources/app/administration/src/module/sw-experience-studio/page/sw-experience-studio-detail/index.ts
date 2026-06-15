import type Repository from 'src/core/data/repository.data';

import { getStorefrontSalesChannelCriteria } from 'src/module/sw-experience-studio/util/sales-channel-criteria.util';
import { castContentElementNodes } from 'src/module/sw-experience-studio/util/content-element-label.util';
import {
    duplicateElementInLayout,
    findElementLocation,
    removeElementFromLayout,
    sanitizeContentElementLayoutForWrite,
} from 'src/module/sw-experience-studio/util/content-element.util';
import template from './sw-experience-studio-detail.html.twig';
import './sw-experience-studio-detail.scss';

const { Mixin } = Shopware;

type Viewport = 'mobile' | 'tablet-landscape' | 'desktop';

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
    } {
        return {
            layout: null,
            isLoading: false,
            isSaveSuccessful: false,
            currentViewport: 'desktop',
            selectedElementId: null,
            previewSalesChannelId: null,
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
    },

    created(): void {
        Shopware.Store.get('adminMenu').collapseSidebar();
        void this.loadLayout();
        void this.loadDefaultPreviewSalesChannel();
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

        onDuplicateElement(elementId: string): void {
            if (!this.layout || !this.allowSave) {
                return;
            }

            const layoutElements = castContentElementNodes(this.layout.layout);
            const result = duplicateElementInLayout(layoutElements, elementId);

            if (!result) {
                return;
            }

            this.layout.layout = sanitizeContentElementLayoutForWrite(layoutElements);
            this.selectedElementId = result.duplicatedId;
        },

        onDeleteElement(elementId: string): void {
            if (!this.layout || !this.allowSave) {
                return;
            }

            const layoutElements = castContentElementNodes(this.layout.layout);
            const removed = removeElementFromLayout(layoutElements, elementId);

            if (!removed) {
                return;
            }

            this.layout.layout = sanitizeContentElementLayoutForWrite(layoutElements);

            if (
                this.selectedElementId !== null
                && findElementLocation(layoutElements, this.selectedElementId) === null
            ) {
                this.selectedElementId = null;
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
