import axe, { type Result } from 'axe-core';
import type CriteriaType from 'src/core/data/criteria.data';

import { getStorefrontSalesChannelCriteria } from 'src/module/sw-experience-studio/util/sales-channel-criteria.util';
import type { AccessibilityViolation } from '../../util/accessibility.types';
import template from './sw-experience-studio-toolbar.html.twig';
import './sw-experience-studio-toolbar.scss';

type Viewport = 'mobile' | 'tablet-landscape' | 'desktop';

type PreviewAccessibilityMessage = {
    source?: string;
    type?: string;
    requestId?: number;
    violations?: AccessibilityViolation[];
    error?: boolean;
};

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    data(): {
        showAccessibilityModal: boolean;
        isAccessibilityScanLoading: boolean;
        accessibilityScanError: string | null;
        accessibilityViolations: AccessibilityViolation[];
        accessibilityScanSequence: number;
        accessibilityScanCompleted: boolean;
        initialAccessibilityScanTimeout: number | null;
    } {
        return {
            showAccessibilityModal: false,
            isAccessibilityScanLoading: false,
            accessibilityScanError: null,
            accessibilityViolations: [],
            accessibilityScanSequence: 0,
            accessibilityScanCompleted: false,
            initialAccessibilityScanTimeout: null,
        };
    },

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
        previewRefreshSequence: {
            type: Number,
            required: false,
            default: 0,
        },
    },

    watch: {
        previewRefreshSequence(): void {
            this.accessibilityScanCompleted = false;
            this.scheduleAccessibilityScan();
        },
    },

    mounted(): void {
        this.scheduleAccessibilityScan();
    },

    beforeUnmount(): void {
        if (this.initialAccessibilityScanTimeout !== null) {
            window.clearTimeout(this.initialAccessibilityScanTimeout);
        }
    },

    emits: [
        'back',
        'viewport-change',
        'save',
        'preview-sales-channel-change',
        'preview-entity-id-change',
        'undo',
        'redo',
        'accessibility-scan-results',
    ],

    computed: {
        layoutName(): string {
            const layout = this.layout as Entity<'content_layout'> | null;

            return layout?.name ?? '';
        },

        salesChannelCriteria(): CriteriaType {
            return getStorefrontSalesChannelCriteria();
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

        onUndo(): void {
            this.$emit('undo');
        },

        onRedo(): void {
            this.$emit('redo');
        },

        onOpenAccessibilityModal(): void {
            this.showAccessibilityModal = true;
            void this.scanAccessibility();
        },

        scheduleAccessibilityScan(attempt = 0): void {
            if (this.accessibilityScanCompleted || this.isAccessibilityScanLoading || attempt >= 20) {
                return;
            }

            if (this.getActivePreviewIframe()) {
                this.initialAccessibilityScanTimeout = window.setTimeout(() => {
                    this.initialAccessibilityScanTimeout = null;
                    void this.scanAccessibility();
                }, 500);

                return;
            }

            this.initialAccessibilityScanTimeout = window.setTimeout(() => {
                this.initialAccessibilityScanTimeout = null;
                this.scheduleAccessibilityScan(attempt + 1);
            }, 250);
        },

        onCloseAccessibilityModal(): void {
            this.showAccessibilityModal = false;
        },

        async scanAccessibility(): Promise<void> {
            this.isAccessibilityScanLoading = true;
            this.accessibilityScanError = null;
            this.accessibilityViolations = [];
            this.accessibilityScanCompleted = false;
            this.$emit('accessibility-scan-results', []);

            try {
                const iframe = this.getActivePreviewIframe();
                let previewDocument: Document | null = null;

                try {
                    previewDocument = iframe?.contentDocument ?? null;
                } catch {
                    // Cross-origin preview documents are scanned through postMessage below.
                }

                if (previewDocument) {
                    const results = await axe.run(previewDocument);
                    this.accessibilityViolations = results.violations;
                    this.accessibilityScanCompleted = true;
                    this.$emit('accessibility-scan-results', this.accessibilityViolations);
                    return;
                }

                if (!iframe?.contentWindow) {
                    throw new Error('The preview is not available for accessibility scanning.');
                }

                this.accessibilityViolations = await this.scanAccessibilityInPreviewFrame(iframe);
                this.accessibilityScanCompleted = true;
                this.$emit('accessibility-scan-results', this.accessibilityViolations);
            } catch (err) {
                this.accessibilityScanError = 'sw-experience-studio.detail.toolbar.accessibilityModal.error';
            } finally {
                this.isAccessibilityScanLoading = false;
            }
        },

        getActivePreviewIframe(): HTMLIFrameElement | null {
            return document.querySelector<HTMLIFrameElement>('.sw-experience-studio-preview__iframe--active');
        },

        scanAccessibilityInPreviewFrame(iframe: HTMLIFrameElement): Promise<Result[]> {
            const requestId = this.accessibilityScanSequence + 1;
            this.accessibilityScanSequence = requestId;
            const frameOrigin = new URL(iframe.src, window.location.origin).origin;

            return new Promise((resolve, reject) => {
                let timeoutId: number | null = null;
                let retryIntervalId: number | null = null;
                const scanRequest = {
                    source: 'sw-experience-studio-admin',
                    type: 'accessibility-scan',
                    requestId,
                };

                const cleanup = (): void => {
                    window.removeEventListener('message', onMessage);

                    if (timeoutId !== null) {
                        window.clearTimeout(timeoutId);
                    }

                    if (retryIntervalId !== null) {
                        window.clearInterval(retryIntervalId);
                    }
                };

                const onMessage = (event: MessageEvent<PreviewAccessibilityMessage>): void => {
                    if (event.source !== iframe.contentWindow || event.origin !== frameOrigin) {
                        return;
                    }

                    const payload = event.data;

                    if (
                        payload?.source !== 'sw-experience-studio-preview'
                        || payload.type !== 'accessibility-scan-result'
                        || payload.requestId !== requestId
                    ) {
                        return;
                    }

                    cleanup();

                    if (payload.error) {
                        reject(new Error('The preview could not scan its document for accessibility issues.'));

                        return;
                    }

                    resolve(payload.violations ?? []);
                };

                window.addEventListener('message', onMessage);
                timeoutId = window.setTimeout(() => {
                    cleanup();
                    reject(new Error('The preview did not return accessibility scan results.'));
                }, 10000);

                const postScanRequest = (): void => {
                    iframe.contentWindow?.postMessage(scanRequest, frameOrigin);
                };

                postScanRequest();
                retryIntervalId = window.setInterval(postScanRequest, 500);
            });
        },

    },
});
