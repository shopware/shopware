import { markRaw } from 'vue';
import type Repository from 'src/core/data/repository.data';
import type MediaService from 'src/core/service/api/media.api.service';
import { type DIVEModel, DIVEMath } from '@shopware-ag/dive';
import { QuickView } from '@shopware-ag/dive/quickview';
import { Toolbox } from '@shopware-ag/dive/toolbox';
import { AssetExporter } from '@shopware-ag/dive/assetexporter';
// eslint-disable-next-line import/no-extraneous-dependencies
import { Euler, type Vector3 } from 'three';
import template from './sw-model-editor.html.twig';
import './sw-model-editor.scss';

const { EventBus } = Shopware.Utils;
const { Context } = Shopware;

type MEModelProperties = {
    position: Vector3;
    rotation: Euler;
    scale: Vector3;
};

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
            objectChangeHandler: null,
            diveModel: null,
            quickView: null,
            toolbox: null,
            currentEditMode: 'translate' as 'translate' | 'rotate' | 'scale',
            isTranslatable: true,
            isRotatable: true,
            isScalable: true,
            initialProperties: {
                position: { x: 0, y: 0, z: 0 },
                rotation: { x: 0, y: 0, z: 0 },
                scale: { x: 1, y: 1, z: 1 },
            },
            currentProperties: {
                position: { x: 0, y: 0, z: 0 },
                rotation: { x: 0, y: 0, z: 0 },
                scale: { x: 1, y: 1, z: 1 },
            },
        } as {
            canvas: HTMLCanvasElement | null;
            isLoading: boolean;
            mediaService: MediaService;
            modelEntity: EntitySchema.Entity<'media'> | null;
            objectChangeHandler: ((event: { object: unknown }) => void) | null;
            diveModel: DIVEModel | null;
            quickView: QuickView | null;
            toolbox: Toolbox | null;
            currentEditMode: 'translate' | 'rotate' | 'scale';
            isTranslatable: boolean;
            isRotatable: boolean;
            isScalable: boolean;
            initialProperties: MEModelProperties;
            currentProperties: MEModelProperties;
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
            this.objectChangeHandler = this.onObjectChange.bind(this);
            this.toolbox.getTool('transform').addEventListener('object-change', this.objectChangeHandler);

            this.diveModel = this.quickView.scene.root.children.find((child) => 'isDIVEModel' in child) as DIVEModel;
            this.saveInitialProperties(this.diveModel as DIVEModel);
            this.syncProperties(this.diveModel as DIVEModel);
            this.toolbox.selectionState.select(this.diveModel);

            return Promise.resolve();
        },

        async disposeQuickView(): Promise<void> {
            if (this.toolbox && this.objectChangeHandler) {
                this.toolbox.getTool('transform').removeEventListener('object-change', this.objectChangeHandler);
            }
            this.objectChangeHandler = null;
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

        /**
         * Sets the gizmo mode.
         * @param mode - the new gizmo mode
         */
        setGizmoMode(mode: 'translate' | 'rotate' | 'scale'): void {
            this.currentEditMode = mode;
            this.toolbox?.getTool('transform').setGizmoMode(mode);
        },

        onObjectChange(event: { object: unknown }): void {
            this.syncProperties(event.object as DIVEModel);
        },

        /**
         * Changes the position of the model. Will be called when the user changes the position in the UI.
         * @param position - the new position
         */
        changeModelPosition(position: { x: number; y: number; z: number }): void {
            if (!this.diveModel) return;

            this.diveModel.setPosition({ x: position.x, y: position.y, z: position.z });
            this.syncProperties(this.diveModel as DIVEModel);
        },

        /**
         * Changes the rotation of the model. Will be called when the user changes the rotation in the UI.
         * @param rotation - the new rotation
         */
        changeModelRotation(rotation: { x: number; y: number; z: number }): void {
            if (!this.diveModel) return;

            this.diveModel.setRotation({
                x: DIVEMath.degToRad(rotation.x),
                y: DIVEMath.degToRad(rotation.y),
                z: DIVEMath.degToRad(rotation.z),
            });
            this.syncProperties(this.diveModel as DIVEModel);
        },

        /**
         * Changes the scale of the model. Will be called when the user changes the scale in the UI.
         * @param scale - the new scale
         */
        changeModelScale(scale: { x: number; y: number; z: number }): void {
            if (!this.diveModel) return;

            this.diveModel.setScale({ x: scale.x, y: scale.y, z: scale.z });
            this.syncProperties(this.diveModel as DIVEModel);
        },

        /**
         * Changes whether the scale is linked or not. Will be called when the user changes the linked state in the UI.
         * @param value - boolean value indicating whether the scale should be linked or not
         */
        changeModelScaleLinked(value: boolean): void {
            if (!this.toolbox) return;

            this.toolbox.getTool('transform').setGizmoScaleLinked(value);
        },

        /**
         * Saves the model to the media library.
         */
        async save(): Promise<void> {
            if (!this.modelEntity) return;
            if (!this.diveModel) return;

            const isEqual = this.compareInitialProperties(this.diveModel as DIVEModel);
            if (isEqual) return;

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

        /**
         * Saves all initial values to compare it on save.
         * @param model - the model to save the initial properties of
         */
        saveInitialProperties(model: DIVEModel): void {
            this.initialProperties = {
                position: model.position.clone(),
                rotation: model.rotation.clone(),
                scale: model.scale.clone(),
            };
        },

        /**
         * Compare initial properties with current properties of the model.
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

        /**
         * Transforms Euler rotation into reasonable degree values for the UI.
         */
        syncProperties(model: DIVEModel): void {
            if (!model) return;

            // handle position
            this.currentProperties.position = model.position.clone();

            // handle rotation
            const x = model.rotation.x;
            const y = model.rotation.y;
            const z = model.rotation.z;

            const std: Euler = new Euler(DIVEMath.radToDeg(x), DIVEMath.radToDeg(y), DIVEMath.radToDeg(z));

            const alt: Euler = new Euler(
                DIVEMath.radToDeg(x > 0 ? x - Math.PI : x + Math.PI),
                DIVEMath.radToDeg(y > 0 ? Math.PI - y : -Math.PI - y),
                DIVEMath.radToDeg(z > 0 ? z - Math.PI : z + Math.PI),
            );

            const prev = this.currentProperties.rotation;
            const distStd = Math.abs(std.x - prev.x) + Math.abs(std.y - prev.y) + Math.abs(std.z - prev.z);
            const distAlt = Math.abs(alt.x - prev.x) + Math.abs(alt.y - prev.y) + Math.abs(alt.z - prev.z);

            this.currentProperties.rotation = distAlt < distStd ? alt : std;

            // handle scale
            this.currentProperties.scale = model.scale.clone();
        },
    },
});
