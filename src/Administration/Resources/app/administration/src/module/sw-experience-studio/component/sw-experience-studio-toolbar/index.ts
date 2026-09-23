import type CriteriaType from 'src/core/data/criteria.data';

import { getStorefrontSalesChannelCriteria } from 'src/module/sw-experience-studio/util/sales-channel-criteria.util';
import template from './sw-experience-studio-toolbar.html.twig';
import './sw-experience-studio-toolbar.scss';

type Viewport = 'mobile' | 'tablet-landscape' | 'desktop';

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        layout: {
            type: Object,
            required: false,
            default: null,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
        currentViewport: {
            type: String,
            required: false,
            default: 'desktop',
        },
        allowSave: {
            type: Boolean,
            required: false,
            default: false,
        },
        previewSalesChannelId: {
            type: String,
            required: false,
            default: null,
        },
        previewEntityType: {
            type: String,
            required: false,
            default: null,
        },
        previewEntityId: {
            type: String,
            required: false,
            default: null,
        },
        canUndo: {
            type: Boolean,
            required: false,
            default: false,
        },
        canRedo: {
            type: Boolean,
            required: false,
            default: false,
        },
        isCreateMode: {
            type: Boolean,
            required: false,
            default: false,
        },
        hasDraft: {
            type: Boolean,
            required: false,
            default: false,
        },
        isDirty: {
            type: Boolean,
            required: false,
            default: false,
        },
        draftCreatedAt: {
            type: String,
            required: false,
            default: null,
        },
    },

    emits: [
        'back',
        'viewport-change',
        'save',
        'publish',
        'discard',
        'preview-sales-channel-change',
        'preview-entity-id-change',
        'undo',
        'redo',
    ],

    computed: {
        layoutName(): string {
            const layout = this.layout as Entity<'content_layout'> | null;

            return layout?.name ?? '';
        },

        salesChannelCriteria(): CriteriaType {
            return getStorefrontSalesChannelCriteria();
        },

        saveLabel(): string {
            return this.isCreateMode
                ? this.$t('sw-experience-studio.detail.toolbar.save')
                : this.$t('sw-experience-studio.detail.toolbar.saveDraft');
        },

        versionStatusLabel(): string {
            if (!this.hasDraft) {
                return this.$t('sw-experience-studio.detail.toolbar.statusLive');
            }

            if (!this.draftCreatedAt) {
                return this.$t('sw-experience-studio.detail.toolbar.statusDraft');
            }

            return this.$t('sw-experience-studio.detail.toolbar.statusDraftCreatedAt', {
                date: Shopware.Utils.format.date(this.draftCreatedAt),
            });
        },

        isPublishDisabled(): boolean {
            return !this.allowSave || this.isLoading || !(this.hasDraft || this.isDirty);
        },
    },

    methods: {
        onBack(): void {
            this.$emit('back');
        },

        onViewportChange(viewport: Viewport): void {
            this.$emit('viewport-change', viewport);
        },

        onPreviewSalesChannelChange(salesChannelId: string | null): void {
            this.$emit('preview-sales-channel-change', salesChannelId);
        },

        onPreviewEntityIdChange(entityId: string | null): void {
            this.$emit('preview-entity-id-change', entityId);
        },

        onSave(): void {
            this.$emit('save');
        },

        onPublish(): void {
            this.$emit('publish');
        },

        onDiscard(): void {
            this.$emit('discard');
        },

        onUndo(): void {
            this.$emit('undo');
        },

        onRedo(): void {
            this.$emit('redo');
        },
    },
});
