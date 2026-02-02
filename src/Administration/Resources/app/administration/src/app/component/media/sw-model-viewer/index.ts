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
            isLoading: false,
            modelEntity: null,
            quickView: null,
        } as {
            canvas: HTMLCanvasElement | null;
            isLoading: boolean;
            modelEntity: EntitySchema.Entity<'media'> | null;
            quickView: QuickView | null;
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

            this.quickView?.dispose();
        },

        mountedComponent(): void {
            /* eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,
                @typescript-eslint/no-unsafe-member-access,
                @typescript-eslint/no-unsafe-call
            */
            this.canvas = this.$el?.querySelector?.('.sw-model-viewer-canvas');

            this.modelEntity = this.source as EntitySchema.Entity<'media'>;
            this.initializeQuickView();
        },

        async initializeQuickView(): Promise<void> {
            if (!this.canvas || !this.modelEntity?.url) {
                return Promise.reject();
            }

            this.isLoading = true;

            this.quickView = await QuickView(this.modelEntity.url, {
                canvas: this.canvas,
            })
                .catch((error) => {
                    console.error(error);
                    return Promise.reject(error);
                })
                .finally(() => {
                    this.isLoading = false;
                });

            return Promise.resolve();
        },

        onMediaLibraryItemUpdated(mediaId: string): void {
            if (!this.modelEntity?.id) return;
            if (this.modelEntity?.id !== mediaId) return;

            this.initializeQuickView();
        },
    },
});
