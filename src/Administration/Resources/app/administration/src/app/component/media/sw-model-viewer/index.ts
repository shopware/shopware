import { markRaw } from 'vue';
import type Repository from 'src/core/data/repository.data';
import { QuickView } from '@shopware-ag/dive/quickview';
import template from './sw-model-viewer.html.twig';
import './sw-model-viewer.scss';

const { EventBus } = Shopware.Utils;
const { Context } = Shopware;

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

    inject: ['repositoryFactory'],

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
        async source(): Promise<void> {
            this.modelEntity = this.source as EntitySchema.Entity<'media'>;
            await this.quickView?.dispose();
            return this.initializeQuickView();
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

    computed: {
        mediaRepository(): Repository<'media'> {
            return this.repositoryFactory.create('media');
        },
    },

    methods: {
        createdComponent(): void {
            // eslint-disable-next-line @typescript-eslint/unbound-method
            EventBus.on('sw-media-library-item-updated', this.onMediaLibraryItemUpdated);
        },

        beforeUnmountedComponent(): void {
            // eslint-disable-next-line @typescript-eslint/unbound-method
            EventBus.off('sw-media-library-item-updated', this.onMediaLibraryItemUpdated);

            this.disposeQuickView().catch((error) => {
                console.error(error);
            });
        },

        mountedComponent(): void {
            /* eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,
                @typescript-eslint/no-unsafe-member-access,
                @typescript-eslint/no-unsafe-call
            */
            this.canvas = this.$el?.querySelector?.('.sw-model-viewer-canvas');

            this.modelEntity = this.source as EntitySchema.Entity<'media'>;
            this.initializeQuickView().catch((error) => {
                console.error(error);
            });
        },

        async initializeQuickView(): Promise<void> {
            if (!this.canvas) {
                return Promise.reject(new Error('Canvas is missing'));
            }

            if (!this.modelEntity?.url) {
                return Promise.reject(new Error('Model entity URL is missing'));
            }

            this.isLoading = true;

            this.quickView = markRaw(
                await QuickView(this.modelEntity.url, {
                    canvas: this.canvas,
                })
                    .catch((error) => {
                        console.error(error);
                        return Promise.reject(error as Error);
                    })
                    .finally(() => {
                        this.isLoading = false;
                    }),
            );

            return Promise.resolve();
        },

        async disposeQuickView(): Promise<void> {
            await this.quickView?.dispose();
        },

        onMediaLibraryItemUpdated(mediaId: string): void {
            if (!this.modelEntity?.id) return;
            if (this.modelEntity?.id !== mediaId) return;

            // Refetch media entity to get fresh URL with updated cache-busting timestamp
            this.mediaRepository
                .get(mediaId, Context.api)
                .then((media) => {
                    this.modelEntity = media;
                })
                .catch((error) => {
                    console.error(error);
                });
        },
    },
});
