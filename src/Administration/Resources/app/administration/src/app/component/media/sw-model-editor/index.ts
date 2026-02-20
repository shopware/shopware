import { markRaw } from 'vue';
import type Repository from 'src/core/data/repository.data';
import type MediaService from 'src/core/service/api/media.api.service';
import { type DIVEModel, DIVEMath } from '@shopware-ag/dive';
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
            diveModel: null,
            quickView: null,
            toolbox: null,
            currentEditMode: 'translate' as 'translate' | 'rotate' | 'scale',
            isTranslatable: true,
            isRotatable: true,
            isScalable: true,
            initialProperties: {},
        } as {
            canvas: HTMLCanvasElement | null;
            isLoading: boolean;
            mediaService: MediaService;
            modelEntity: EntitySchema.Entity<'media'> | null;
            diveModel: DIVEModel | null;
            quickView: QuickView | null;
            toolbox: Toolbox | null;
            currentEditMode: 'translate' | 'rotate' | 'scale';
            isTranslatable: boolean;
            isRotatable: boolean;
            isScalable: boolean;
            initialProperties: any,
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

            this.diveModel = this.quickView.scene.root.children.find((child) => 'isDIVEModel' in child) as DIVEModel;
            this.saveInitialProperties(this.diveModel);
            this.toolbox.selectionState.select(this.diveModel);

            return Promise.resolve();
        },

        async disposeQuickView(): Promise<void> {
            this.toolbox?.dispose();
            await this.quickView?.dispose();
        },

        // we need this wrapper because we use it in the twig template as well
        radToDeg(value: number): number {
            return DIVEMath.radToDeg(value);
        },

        // we need this wrapper because we use it in the twig template as well
        degToRad(value: number): number {
            return DIVEMath.degToRad(value);
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

        changeModelPosition(position: { x: number; y: number; z: number }): void {
            if (!this.diveModel) return;

            this.diveModel.setPosition({ x: position.x, y: position.y, z: position.z });
        },

        changeModelRotation(rotation: { x: number; y: number; z: number }): void {
            if (!this.diveModel) return;

            this.diveModel.setRotation({ x: rotation.x, y: rotation.y, z: rotation.z });
        },

        changeModelScale(scale: { x: number; y: number; z: number }): void {
            if (!this.diveModel) return;

            this.diveModel.setScale({ x: scale.x, y: scale.y, z: scale.z });
        },

        // in this function we save all inital values we can change in the model editor to compare it on save
        saveInitialProperties(model: DIVEModel): void {
            this.initialProperties = {
                position: model.position.clone(),
                rotation: model.rotation.clone(),
                scale: model.scale.clone(),
            };
        },

        /***
         * compare initial properties with current properties of modal
         *
         * @param model - the current model
         * @returns true if the initial properties are equal to the current properties, false otherwise
         */
        compareInitialProperties(model: DIVEModel): boolean {
            // compare position
            const equalPosition = this.initialProperties.position.equals(model.position);

            // compare rotation
            const equalRotation = this.initialProperties.rotation.equals(model.rotation);

            // compare scale
            const equalScale = this.initialProperties.scale.equals(model.scale);

            return equalPosition && equalRotation && equalScale;
        },

        async save(): Promise<void> {
            if (!this.modelEntity) return;
            if (!this.diveModel) return;

            const isEqual = this.compareInitialProperties(this.diveModel as DIVEModel);
            if(isEqual) return;

            const targetId = this.modelEntity.id;
            const fileName = this.modelEntity.fileName ?? 'model';
            const fileExtension = this.modelEntity.fileExtension ?? 'glb';

            const buffer = await new AssetExporter().export(this.diveModel, 'glb');
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
