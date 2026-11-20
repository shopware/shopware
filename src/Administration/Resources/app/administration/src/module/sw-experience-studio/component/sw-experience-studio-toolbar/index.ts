import type CriteriaType from 'src/core/data/criteria.data';

import { getStorefrontSalesChannelCriteria } from 'src/module/sw-experience-studio/util/sales-channel-criteria.util';
import template from './sw-experience-studio-toolbar.html.twig';
import './sw-experience-studio-toolbar.scss';

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
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
        changedLayouts: {
            type: Array as PropType<Array<{ id: string; name: string }>>,
            default: () => [],
        },
        isSavingAll: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: [
        'save',
        'discard-layout',
        'preview-sales-channel-change',
    ],

    data() {
        return {
            isReviewOpen: false,
            reviewKeydownHandler: null as ((event: KeyboardEvent) => void) | null,
        };
    },

    computed: {
        reviewActionsDisabled(): boolean {
            return !this.allowSave || this.isLoading || this.isSavingAll;
        },

        salesChannelCriteria(): CriteriaType {
            return getStorefrontSalesChannelCriteria();
        },
    },

    watch: {
        'changedLayouts.length'(count: number): void {
            if (count === 0) {
                this.closeReview();
            }
        },
    },

    mounted(): void {
        this.reviewKeydownHandler = (event: KeyboardEvent): void => {
            if (this.isReviewOpen && event.key === 'Escape') {
                event.preventDefault();
                this.closeReview();
            }
        };
        window.addEventListener('keydown', this.reviewKeydownHandler);
    },

    beforeUnmount(): void {
        if (this.reviewKeydownHandler) {
            window.removeEventListener('keydown', this.reviewKeydownHandler);
        }
    },

    methods: {
        toggleReview(): void {
            if (this.isReviewOpen) {
                this.closeReview();
                return;
            }
            this.isReviewOpen = true;
            void this.$nextTick(() => {
                const panel = this.$refs.reviewPanel as HTMLElement | undefined;
                panel?.focus();
            });
        },

        closeReview(): void {
            if (!this.isReviewOpen) {
                return;
            }
            this.isReviewOpen = false;
            const trigger = this.$refs.reviewTrigger as { $el: HTMLButtonElement } | undefined;
            trigger?.$el.focus();
        },

        onPreviewSalesChannelChange(salesChannelId: string | null): void {
            this.$emit('preview-sales-channel-change', salesChannelId);
        },

        onSave(): void {
            this.$emit('save');
        },
    },
});
