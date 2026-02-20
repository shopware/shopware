import { markRaw } from 'vue';
import type Repository from 'src/core/data/repository.data';
import type MediaService from 'src/core/service/api/media.api.service';
import type { DIVEModel } from '@shopware-ag/dive';
import { QuickView } from '@shopware-ag/dive/quickview';
import { Toolbox } from '@shopware-ag/dive/toolbox';
import { AssetExporter } from '@shopware-ag/dive/assetexporter';
import template from './sw-model-editor.html.twig';
import './sw-model-editor.scss';

const { EventBus } = Shopware.Utils;
const { Context } = Shopware;
/**
 * @status ready
 * @description The <u>sw-model-editor</u> component is used to edit model objects.
 * @sw-package innovation
 * @example-type code-only
 * @component-example
 * <sw-model-editor
 *      :source="mediaEntity"
 * </sw-model-editor>
 *
 * @experimental stableVersion:v6.8.0 feature:MODEL_EDITOR
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'mediaService',
        'repositoryFactory',
    ],

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
            toolbox: null,
            currentEditMode: 'translate' as 'translate' | 'rotate' | 'scale',
            isTranslatable: true,
            isRotatable: true,
            isScalable: true,
        } as {
            canvas: HTMLCanvasElement | null;
            isLoading: boolean;
            mediaService: MediaService;
            modelEntity: EntitySchema.Entity<'media'> | null;
            quickView: QuickView | null;
            toolbox: Toolbox | null;
            currentEditMode: 'translate' | 'rotate' | 'scale';
            isTranslatable: boolean;
            isRotatable: boolean;
            isScalable: boolean;
        };
    },

    watch: {
        async source(): Promise<void> {
            this.modelEntity = this.source as EntitySchema.Entity<'media'>;
            await this.disposeQuickView();
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
            this.canvas = this.$el?.querySelector?.('.sw-model-editor-canvas');

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
                    displayAxes: true,
                    displayGrid: true,
                })
                    .catch((error: Error) => {
                        console.error(error);
                        return Promise.reject(error);
                    })
                    .finally(() => {
                        this.isLoading = false;
                    }),
            );

            /* eslint-disable-next-line @typescript-eslint/no-explicit-any,
                @typescript-eslint/no-unsafe-argument
            */
            this.toolbox = markRaw(new Toolbox(this.quickView.scene as any, this.quickView.orbitController as any));
            this.toolbox.enableTool('transform');
            this.toolbox.getTool('transform').setGizmoMode(this.currentEditMode);

            const model = this.quickView.scene.root.children.find((child) => 'isDIVEModel' in child) as DIVEModel;
            this.toolbox.selectionState.select(model);

            return Promise.resolve();
        },

        async disposeQuickView(): Promise<void> {
            this.toolbox?.dispose();
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

        setGizmoMode(mode: 'translate' | 'rotate' | 'scale'): void {
            this.currentEditMode = mode;
            this.toolbox?.getTool('transform').setGizmoMode(mode);
        },

        async save(): Promise<void> {
            if (!this.modelEntity) return;
            const targetId = this.modelEntity.id;
            const fileName = this.modelEntity.fileName ?? 'model';
            const fileExtension = this.modelEntity.fileExtension ?? 'glb';

            const model = this.quickView?.scene.root.children.find((child) => 'isDIVEModel' in child) as DIVEModel;
            if (!model) return;

            const buffer = await new AssetExporter().export(model, 'glb');
            const file = new File([buffer], `${fileName}`, { type: 'model/gltf-binary' });

            const uploadData = {
                src: file,
                fileName: file.name,
                mimeType: file.type,
                extension: fileExtension,
                isPrivate: false,
                targetId: targetId,
            };

            this.mediaService.addUpload('media', uploadData);
            await this.mediaService.runUploads('media');

            // Emit event to trigger refresh with new URL (includes updated cache-busting timestamp)
            EventBus.emit('sw-media-library-item-updated', targetId);
        },
    },
});
