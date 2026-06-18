import template from './sw-experience-studio-preview.html.twig';
import type { ContentElementNode } from 'src/module/sw-experience-studio/types/content-element.types';
import { sanitizeContentElementLayoutForWrite } from 'src/module/sw-experience-studio/util/content-element.util';
import './sw-experience-studio-preview.scss';

type Viewport = 'mobile' | 'tablet-landscape' | 'desktop';

type ContentSystemPreviewService = {
    previewEntityUrl: (payload: {
        layout: unknown[];
        entityType: string;
        entityId: string;
        salesChannelId: string;
    }) => Promise<string>;
};

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    emits: [
        'select-element',
    ],

    props: {
        layout: {
            type: Object,
            required: false,
            default: null,
        },
        viewport: {
            type: String,
            required: false,
            default: 'desktop',
        },
        salesChannelId: {
            type: String,
            required: false,
            default: null,
        },
        entityType: {
            type: String,
            required: false,
            default: null,
        },
        entityId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            isPreviewLoading: false,
            previewLoadError: null as string | null,
            iframeAUrl: null as string | null,
            iframeBUrl: null as string | null,
            activeFrame: null as 'a' | 'b' | null,
            loadingFrame: null as 'a' | 'b' | null,
            latestRequestId: 0,
            debouncedLoadPreview: null as (() => void) | null,
            previewMessageHandler: null as ((event: MessageEvent) => void) | null,
        };
    },

    created() {
        this.debouncedLoadPreview = Shopware.Utils.debounce(() => {
            void this.loadPreview();
        }, 300);

        this.previewMessageHandler = (event: MessageEvent) => {
            const payload = event.data as {
                source?: string;
                type?: string;
                elementId?: string | null;
            } | null;

            if (!payload || payload.source !== 'sw-experience-studio-preview') {
                return;
            }

            if (payload.type !== 'select-element') {
                return;
            }

            this.$emit('select-element', payload.elementId ?? null);
        };
        window.addEventListener('message', this.previewMessageHandler);

        this.schedulePreviewReload();
    },

    beforeUnmount() {
        if (this.previewMessageHandler) {
            window.removeEventListener('message', this.previewMessageHandler);
        }
    },

    watch: {
        'layout.layout': {
            handler() {
                this.schedulePreviewReload();
            },
            deep: true,
        },

        salesChannelId() {
            this.schedulePreviewReload();
        },

        entityType() {
            this.schedulePreviewReload();
        },

        entityId() {
            this.schedulePreviewReload();
        },
    },

    computed: {
        viewportClass(): string {
            return `is--${this.viewport as Viewport}`;
        },

        hasPreviewContext(): boolean {
            return Boolean(this.layout && this.salesChannelId && this.entityType && this.entityId);
        },

        hasAnyPreviewFrame(): boolean {
            return this.activeFrame !== null || this.loadingFrame !== null;
        },

        showInitialLoader(): boolean {
            return this.isPreviewLoading && !this.hasAnyPreviewFrame;
        },
    },

    methods: {
        schedulePreviewReload(): void {
            this.debouncedLoadPreview?.();
        },

        resetPreviewFrames(): void {
            this.iframeAUrl = null;
            this.iframeBUrl = null;
            this.activeFrame = null;
            this.loadingFrame = null;
        },

        getFrameUrl(frame: 'a' | 'b'): string | null {
            return frame === 'a' ? this.iframeAUrl : this.iframeBUrl;
        },

        getFrameClass(frame: 'a' | 'b'): string[] {
            const classes = ['sw-experience-studio-preview__iframe'];

            if (this.activeFrame === frame) {
                classes.push('sw-experience-studio-preview__iframe--active');
            } else {
                classes.push('sw-experience-studio-preview__iframe--inactive');
            }

            if (this.loadingFrame === frame) {
                classes.push('sw-experience-studio-preview__iframe--preload');
            }

            return classes;
        },

        onPreviewFrameLoad(frame: 'a' | 'b'): void {
            if (this.loadingFrame !== frame) {
                return;
            }

            this.activeFrame = frame;
            this.loadingFrame = null;
        },

        assignLoadingFrame(url: string): void {
            const targetFrame = this.activeFrame === 'a' ? 'b' : 'a';

            if (targetFrame === 'a') {
                this.iframeAUrl = url;
            } else {
                this.iframeBUrl = url;
            }

            this.loadingFrame = targetFrame;
        },

        async loadPreview(): Promise<void> {
            const previewService = Shopware.Service('contentSystemPreviewService') as ContentSystemPreviewService;
            const layout = this.layout as Entity<'content_layout'> | null;
            const salesChannelId = this.salesChannelId as string | null;
            const entityType = this.entityType as string | null;
            const entityId = this.entityId as string | null;
            const serializedLayout = Array.isArray(layout?.layout) ? layout.layout : null;

            if (!serializedLayout || !salesChannelId || !entityType || !entityId) {
                this.resetPreviewFrames();
                this.previewLoadError = null;
                this.isPreviewLoading = false;

                return;
            }

            const requestId = this.latestRequestId + 1;
            this.latestRequestId = requestId;
            this.isPreviewLoading = true;
            this.previewLoadError = null;

            try {
                const previewLayout = sanitizeContentElementLayoutForWrite(serializedLayout as ContentElementNode[]);

                const previewUrl = await previewService.previewEntityUrl({
                    layout: previewLayout,
                    entityType,
                    entityId,
                    salesChannelId,
                });

                if (requestId !== this.latestRequestId) {
                    return;
                }

                this.previewLoadError = null;
                this.assignLoadingFrame(previewUrl);
            } catch {
                if (requestId !== this.latestRequestId) {
                    return;
                }

                if (!this.hasAnyPreviewFrame) {
                    this.previewLoadError = this.$t('sw-experience-studio.detail.preview.errorLoad');
                }
            } finally {
                if (requestId === this.latestRequestId) {
                    this.isPreviewLoading = false;
                }
            }
        },
    },
});
