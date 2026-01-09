import template from './sw-model-viewer.html.twig';
import './sw-model-viewer.scss';
import { QuickView } from '@shopware-ag/dive/quickview';

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
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        source: {
            type: Object,
            required: true,
            validator(value: any) {
                return value?.getEntityName() === 'media';
            },
        },
    },

    data() {
        return {
            canvas: null,
            isLoading: true,
            modelEntity: null,
        } as {
            canvas: HTMLCanvasElement | null;
            isLoading: boolean;
            modelEntity: EntitySchema.Entity<'media'> | null;
        };
    },

    watch: {
        async source() {
            this.modelEntity = this.source as EntitySchema.Entity<'media'>;
            await this.initializeQuickView().catch((error) => {
                console.error(error);
            });
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
            EventBus.on('sw-media-library-item-updated', this.onMediaLibraryItemUpdated);
        },

        beforeUnmountedComponent(): void {
            EventBus.off('sw-media-library-item-updated', this.onMediaLibraryItemUpdated);
        },

        async mountedComponent(): Promise<void> {
            this.canvas = this.$el?.querySelector?.('.sw-model-viewer-canvas') || null;

            this.modelEntity = this.source as EntitySchema.Entity<'media'>;
            await this.initializeQuickView().catch((error) => {
                console.error(error);
            });
        },

        async initializeQuickView(): Promise<void> {
            this.isLoading = true;

            if(!this.canvas || !this.modelEntity || !this.modelEntity.url) {
                this.isLoading = false;
                return;
            };

            await QuickView(this.modelEntity.url, {
                canvas: this.canvas,
            });

            this.isLoading = false;
        },

        async onMediaLibraryItemUpdated(mediaId: string): Promise<void> {
            const currentMediaId = this.getCurrentMediaId();

            if (!currentMediaId || currentMediaId !== mediaId) {
                return;
            }

            await this.initializeQuickView().catch((error) => {
                console.error(error);
            });
        },

        getCurrentMediaId() : string | null {
            const entity = Array.isArray(this.source) ? this.source[0] : this.source;
            return entity?.id ?? null;
        },
    },
});
