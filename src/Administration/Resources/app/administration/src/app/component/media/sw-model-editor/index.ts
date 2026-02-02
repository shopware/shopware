import { type DIVEModel, type DIVEScene } from '@shopware-ag/dive';
import { type OrbitController } from '@shopware-ag/dive/orbitcontroller';
import { QuickView } from '@shopware-ag/dive/quickview';
import { Toolbox } from '@shopware-ag/dive/toolbox';
import template from './sw-model-editor.html.twig';
import './sw-model-editor.scss';

const { EventBus } = Shopware.Utils;

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
            isScalable: false,
        } as {
            canvas: HTMLCanvasElement | null;
            isLoading: boolean;
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

            this.toolbox?.dispose();
            this.quickView?.dispose();
        },

        mountedComponent(): void {
            /* eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,
                @typescript-eslint/no-unsafe-member-access,
                @typescript-eslint/no-unsafe-call
            */
            this.canvas = this.$el?.querySelector?.('.sw-model-editor-canvas');

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
                displayAxes: true,
                displayGrid: true,
            })
                .catch((error) => {
                    console.error(error);
                    return Promise.reject(error);
                })
                .finally(() => {
                    this.isLoading = false;
                });

            this.toolbox = new Toolbox(this.quickView.scene as DIVEScene, this.quickView.orbitController as OrbitController);

            this.toolbox.enableTool('transform');

            const model = this.quickView.scene.root.children.find((child) => 'isDIVEModel' in child) as DIVEModel;
            this.toolbox.selectionState.select(model);

            return Promise.resolve();
        },

        onMediaLibraryItemUpdated(mediaId: string): void {
            if (!this.modelEntity?.id) return;
            if (this.modelEntity?.id !== mediaId) return;

            this.initializeQuickView();
        },

        setGizmoMode(mode: 'translate' | 'rotate' | 'scale'): void {
            this.currentEditMode = mode;

            this.toolbox?.getTool('transform').setGizmoMode(mode);
        },
    },
});
