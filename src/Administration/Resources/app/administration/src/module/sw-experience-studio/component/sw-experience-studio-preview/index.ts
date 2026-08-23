import template from './sw-experience-studio-preview.html.twig';
import type { ContentLayoutEntity } from 'src/module/sw-experience-studio/util/content-layout-repository.util';
import './sw-experience-studio-preview.scss';

const { cloneDeep } = Shopware.Utils.object;

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
    requestId?: number;
    top?: number;
    left?: number;
} | null;

type PreviewScrollPosition = {
    top: number;
    left: number;
};

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
            type: Object as PropType<ContentLayoutEntity | null>,
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
            pendingScrollPosition: null as PreviewScrollPosition | null,
            scrollRequestSequence: 0,
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

        getFrameOrigin(frame: 'a' | 'b'): string | null {
            const frameUrl = this.getFrameUrl(frame);

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
            this.pendingScrollPosition = null;
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

        async onPreviewFrameLoad(frame: 'a' | 'b'): Promise<void> {
            if (this.loadingFrame !== frame) {
                return;
            }

            const scrollPositionToRestore = this.pendingScrollPosition;

            if (scrollPositionToRestore) {
                await this.restoreFrameScrollPosition(frame, scrollPositionToRestore);
            }

            if (this.loadingFrame !== frame) {
                return;
            }

            this.activeFrame = frame;
            this.loadingFrame = null;
            this.pendingScrollPosition = null;
        },

        captureActiveFrameScrollPosition(): PreviewScrollPosition | null {
            const activeFrame = this.getActiveFrameElement();
            const activeFrameWindow = activeFrame?.contentWindow;

            if (!activeFrameWindow) {
                return null;
            }

            try {
                return {
                    top: activeFrameWindow.scrollY,
                    left: activeFrameWindow.scrollX,
                };
            } catch {
                return null;
            }
        },

        requestActiveFrameScrollPosition(): Promise<PreviewScrollPosition | null> {
            const directScrollPosition = this.captureActiveFrameScrollPosition();

            if (directScrollPosition) {
                return Promise.resolve(directScrollPosition);
            }

            const activeFrame = this.getActiveFrameElement();
            const activeOrigin = this.getActiveFrameOrigin();
            const activeFrameWindow = activeFrame?.contentWindow;

            if (!activeFrameWindow || !activeOrigin) {
                return Promise.resolve(null);
            }

            const requestId = this.scrollRequestSequence + 1;
            this.scrollRequestSequence = requestId;

            return new Promise((resolve) => {
                let timeoutId: number | null = null;
                let onMessage: ((event: MessageEvent) => void) | null = null;

                const finish = (result: PreviewScrollPosition | null): void => {
                    if (onMessage) {
                        window.removeEventListener('message', onMessage);
                    }

                    if (timeoutId !== null) {
                        window.clearTimeout(timeoutId);
                    }

                    resolve(result);
                };

                onMessage = (event: MessageEvent): void => {
                    if (!this.isTrustedPreviewMessage(event)) {
                        return;
                    }

                    const payload = event.data as PreviewMessagePayload;

                    if (
                        payload?.type !== 'scroll-position' ||
                        payload.requestId !== requestId ||
                        typeof payload.top !== 'number' ||
                        typeof payload.left !== 'number'
                    ) {
                        return;
                    }

                    finish({
                        top: payload.top,
                        left: payload.left,
                    });
                };

                window.addEventListener('message', onMessage);
                timeoutId = window.setTimeout(() => finish(null), 250);

                activeFrameWindow.postMessage(
                    {
                        source: 'sw-experience-studio-admin',
                        type: 'capture-scroll',
                        requestId,
                    },
                    activeOrigin,
                );
            });
        },

        restoreFrameScrollPosition(frame: 'a' | 'b', scrollPosition: PreviewScrollPosition): Promise<void> {
            const frameElement =
                frame === 'a'
                    ? (this.$refs.iframeA as HTMLIFrameElement | null)
                    : (this.$refs.iframeB as HTMLIFrameElement | null);

            const frameWindow = frameElement?.contentWindow;
            const frameOrigin = this.getFrameOrigin(frame);

            if (!frameWindow || !frameOrigin) {
                return Promise.resolve();
            }

            const requestId = this.scrollRequestSequence + 1;
            this.scrollRequestSequence = requestId;

            return new Promise((resolve) => {
                let timeoutId: number | null = null;
                let onMessage: ((event: MessageEvent) => void) | null = null;

                const finish = (): void => {
                    if (onMessage) {
                        window.removeEventListener('message', onMessage);
                    }

                    if (timeoutId !== null) {
                        window.clearTimeout(timeoutId);
                    }

                    resolve();
                };

                onMessage = (event: MessageEvent): void => {
                    const payload = event.data as PreviewMessagePayload;

                    if (
                        event.source !== frameWindow ||
                        event.origin !== frameOrigin ||
                        payload?.source !== 'sw-experience-studio-preview' ||
                        payload?.type !== 'scroll-restored' ||
                        payload.requestId !== requestId
                    ) {
                        return;
                    }

                    finish();
                };

                window.addEventListener('message', onMessage);
                timeoutId = window.setTimeout(() => finish(), 250);

                frameWindow.postMessage(
                    {
                        source: 'sw-experience-studio-admin',
                        type: 'restore-scroll',
                        requestId,
                        top: scrollPosition.top,
                        left: scrollPosition.left,
                    },
                    frameOrigin,
                );
            });
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
            const layout = this.layout;
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
                // Working-tree layout data crossing an outbound boundary is cloned at the call site.
                const previewLayout = cloneDeep(serializedLayout);

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
                this.pendingScrollPosition = await this.requestActiveFrameScrollPosition();
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
