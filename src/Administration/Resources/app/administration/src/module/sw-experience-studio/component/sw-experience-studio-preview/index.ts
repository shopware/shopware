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

type PreviewMessagePayload = {
    source?: string;
    type?: string;
    elementId?: string | null;
    value?: string | null;
} | null;

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    emits: [
        'select-element',
        'inline-edit-start',
        'inline-edit-change',
        'inline-edit-commit',
        'inline-edit-cancel',
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
        suspendAutoReload: {
            type: Boolean,
            required: false,
            default: false,
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
            const payload = event.data as PreviewMessagePayload;

            if (!payload || payload.source !== 'sw-experience-studio-preview') {
                return;
            }

            if (!this.isTrustedPreviewMessage(event)) {
                return;
            }

            if (payload.type === 'select-element') {
                this.$emit('select-element', payload.elementId ?? null);
                return;
            }

            if (!payload.elementId) {
                return;
            }

            if (payload.type === 'inline-edit-start') {
                this.$emit('inline-edit-start', {
                    elementId: payload.elementId,
                });

                return;
            }

            if (payload.type === 'inline-edit-change' && typeof payload.value === 'string') {
                this.$emit('inline-edit-change', {
                    elementId: payload.elementId,
                    value: payload.value,
                });

                return;
            }

            if (payload.type === 'inline-edit-commit' && typeof payload.value === 'string') {
                this.$emit('inline-edit-commit', {
                    elementId: payload.elementId,
                    value: payload.value,
                });

                return;
            }

            if (payload.type === 'inline-edit-cancel') {
                this.$emit('inline-edit-cancel', {
                    elementId: payload.elementId,
                });
            }
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

        suspendAutoReload(nextValue: boolean, previousValue: boolean) {
            if (previousValue && !nextValue) {
                this.debouncedLoadPreview?.();
            }
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
        getActiveFrameElement(): HTMLIFrameElement | null {
            if (this.activeFrame === 'a') {
                return this.$refs.iframeA as HTMLIFrameElement | null;
            }

            if (this.activeFrame === 'b') {
                return this.$refs.iframeB as HTMLIFrameElement | null;
            }

            return null;
        },

        getActiveFrameOrigin(): string | null {
            const activeFrame = this.getActiveFrameElement();
            const frameUrl = activeFrame?.getAttribute('src');

            if (!frameUrl) {
                return null;
            }

            try {
                return new URL(frameUrl, window.location.origin).origin;
            } catch {
                return null;
            }
        },

        isTrustedPreviewMessage(event: MessageEvent): boolean {
            const activeFrame = this.getActiveFrameElement();

            if (!activeFrame?.contentWindow || event.source !== activeFrame.contentWindow) {
                return false;
            }

            const activeOrigin = this.getActiveFrameOrigin();

            if (!activeOrigin) {
                return false;
            }

            return event.origin === activeOrigin;
        },

        schedulePreviewReload(): void {
            if (this.suspendAutoReload) {
                return;
            }

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
