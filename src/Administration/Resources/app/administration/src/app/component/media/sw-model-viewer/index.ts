import { QuickView } from '@shopware-ag/dive/quickview';
import template from './sw-model-viewer.html.twig';
import './sw-model-viewer.scss';

const { EventBus } = Shopware.Utils;

/**
 * @status ready
 * @description The <u>sw-model-viewer</u> component is used to show a preview of model objects.
 * @sw-package discovery
 * @example-type code-only
 * @component-example
 * <sw-model-viewer
 *      :source="mediaEntity"
 * </sw-model-viewer>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        source: {
            type: Object,
            required: true,
            validator(value: EntitySchema.Entity<'media'>) {
                return value?.getEntityName() === 'media';
            },
        },
    },

    data() {
        return {
            canvas: null,
            canvasWrapper: null,
            resizeObserver: null,
            isLoading: false,
            modelEntity: null,
        } as {
            canvas: HTMLCanvasElement | null;
            canvasWrapper: HTMLElement | null;
            resizeObserver: ResizeObserver | null;
            isLoading: boolean;
            modelEntity: EntitySchema.Entity<'media'> | null;
        };
    },

    watch: {
        source() {
            this.modelEntity = this.source as EntitySchema.Entity<'media'>;
            this.initializeQuickView();
        },
    },

    created() {
        this.createdComponent();
    },

    beforeUnmount() {
        this.beforeUnmountedComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createdComponent(): void {
            // eslint-disable-next-line @typescript-eslint/unbound-method
            EventBus.on('sw-media-library-item-updated', this.onMediaLibraryItemUpdated);
        },

        beforeUnmountedComponent(): void {
            // eslint-disable-next-line @typescript-eslint/unbound-method
            EventBus.off('sw-media-library-item-updated', this.onMediaLibraryItemUpdated);

            if (this.resizeObserver) {
                this.resizeObserver.disconnect();
                this.resizeObserver = null;
            }
        },

        mountedComponent(): void {
            /* eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,
                @typescript-eslint/no-unsafe-member-access,
                @typescript-eslint/no-unsafe-call
            */
            this.canvas = this.$el?.querySelector?.('.sw-model-viewer-canvas');
            /* eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,
               @typescript-eslint/no-unsafe-member-access,
               @typescript-eslint/no-unsafe-call
           */
            this.canvasWrapper = this.$el?.querySelector?.('.sw-model-viewer-canvas-wrapper');

            this.initResizeObserver();

            this.modelEntity = this.source as EntitySchema.Entity<'media'>;
            this.initializeQuickView();
        },

        initResizeObserver(): void {
            if (!this.canvasWrapper) {
                return;
            }

            this.resizeObserver = new ResizeObserver((entries) => {
                entries.forEach((entry) => {
                    const width = entry.contentRect.width;
                    (entry.target as HTMLElement).style.height = `${width}px`;
                });
            });

            this.resizeObserver.observe(this.canvasWrapper);
        },

        initializeQuickView(): void {
            if (!this.canvas || !this.modelEntity?.url) {
                return;
            }

            this.isLoading = true;

            QuickView(this.modelEntity.url, {
                canvas: this.canvas,
            })
                .catch((error) => {
                    console.error(error);
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onMediaLibraryItemUpdated(mediaId: string): void {
            if (!this.modelEntity?.id) return;
            if (this.modelEntity?.id !== mediaId) return;

            this.initializeQuickView();
        },
    },
});
