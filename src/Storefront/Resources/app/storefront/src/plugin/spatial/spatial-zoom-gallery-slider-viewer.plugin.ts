import SpatialBaseViewerPlugin from './spatial-base-viewer.plugin';
import SpatialZoomGallerySliderRenderUtil from './utils/spatial-zoom-gallery-slider-render-util';
import SpatialCanvasSizeUpdateUtil from './utils/spatial-canvas-size-update-util';
import SpatialObjectLoaderUtil from './utils/spatial-object-loader-util';
import SpatialOrbitControlsUtil from './utils/spatial-orbit-controls-util';
import SpatialMovementNoteUtil from './utils/spatial-movement-note-util';
import { type Object3D } from 'three';
import SpatialLightCompositionUtil from './utils/composition/spatial-light-composition-util';
import { loadThreeJs } from './utils/spatial-threejs-load-util';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */
export default class SpatialZoomGallerySliderViewerPlugin extends SpatialBaseViewerPlugin {

    private SpatialZoomGallerySliderRenderUtil: SpatialZoomGallerySliderRenderUtil | undefined;
    private spatialCanvasSizeUpdateUtil: SpatialCanvasSizeUpdateUtil | undefined;
    private spatialObjectLoaderUtil: SpatialObjectLoaderUtil | undefined;
    private spatialOrbitControlsUtil: SpatialOrbitControlsUtil | undefined;
    private spatialMovementNoteUtil: SpatialMovementNoteUtil | undefined;
    private spatialLightCompositionUtil: SpatialLightCompositionUtil | undefined;
    private spatialARViewUtil: SpatialARViewUtil;

    private model: Object3D | null = null;

    public sliderIndex: number | undefined;
    public el: HTMLElement | undefined;

    /**
     * initialize plugin
     * does not initialize the 3d scene
     */
    async init() {
        await loadThreeJs();

        if (!this.el) {
            return;
        }
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        this.sliderIndex = Number(this.options.sliderPosition);

        this.SpatialZoomGallerySliderRenderUtil = new SpatialZoomGallerySliderRenderUtil(this);

        this.SpatialZoomGallerySliderRenderUtil.removeDisabled();

        this.initViewer(true);
    }

    /**
     * initialize plugin
     * @param force - Will reinitialize the viewer entirely. Otherwise, only the canvas and renderer will be reinitialized.
     */
    public initViewer(force = false) {
        super.initViewer(force);

        // Set up the renderer
        this.renderer?.setClearColor(0xffffff, 0);

        // Set up the camera
        this.camera?.position.set(0, 0.6, 1.2);
        // @ts-ignore
        this.camera?.lookAt(0, 0, 0);

        // Set up the orbit controls
        if (this.spatialOrbitControlsUtil != undefined) {
            // We need to dispose of the old orbit controls if they exist
            this.spatialOrbitControlsUtil.dispose();
        }
        if (this.camera && this.renderer) {
            this.spatialOrbitControlsUtil = new SpatialOrbitControlsUtil(this.camera, this.renderer.domElement);
        }

        // Set up move note
        this.spatialMovementNoteUtil = new SpatialMovementNoteUtil(this);

        // Set up the canvas size updater
        this.spatialCanvasSizeUpdateUtil = new SpatialCanvasSizeUpdateUtil(this);

        // Set up the lights
        if (this.spatialLightCompositionUtil == undefined || force) {
            this.spatialLightCompositionUtil?.dispose();
            if (this.scene) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-argument
                this.spatialLightCompositionUtil = new SpatialLightCompositionUtil(this.scene, this.options.lightIntensity);
            }
        }

        // Set up the object loader
        if (this.spatialObjectLoaderUtil == undefined || force) {
            this.spatialObjectLoaderUtil = new SpatialObjectLoaderUtil(this);
        }

        if (this.model == null || force) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access
            const modelUrl: string = this.options.modelUrl;
            if (modelUrl == null) {
                return;
            }
            this.spatialObjectLoaderUtil.loadSingleObjectByUrl(
                modelUrl,
                {
                    center: true,
                    clampSize: true,
                    clampMaxSize: {
                        x: window.innerWidth / window.innerHeight,
                        y: 1,
                        z: window.innerWidth / window.innerHeight,
                    },
                }
            ).then((model) => {
                this.model = model;
                this.scene?.add(this.model);
                this.setReady(true);
            }).catch(() => {
                this.el?.parentElement?.parentElement?.classList.add('gallery-slider-canvas-disabled');
            });
        } else {
            // set ready if the model is already loaded
            this.setReady(true);
        }

        // start rendering when on the correct slide
        this.SpatialZoomGallerySliderRenderUtil?.initViewer();
    }

    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    protected preRender(delta: number) {
        this.spatialCanvasSizeUpdateUtil?.update();
        this.spatialOrbitControlsUtil?.update();
    }

    // eslint-disable-next-line @typescript-eslint/no-unused-vars, @typescript-eslint/no-empty-function
    protected postRender(delta: number) { }
}
