import SpatialBaseViewerPlugin from './spatial-base-viewer.plugin';
import SpatialProductSliderRenderUtil from './utils/spatial-product-slider-render-util';
import { loadDIVE } from './utils/spatial-dive-load-util';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
export default class SpatialGallerySliderViewerPlugin extends SpatialBaseViewerPlugin {

    private spatialProductSliderRenderUtil: SpatialProductSliderRenderUtil | undefined;
    public sliderIndex: number | undefined;

    public el: HTMLElement | undefined;

    /**
     * initialize plugin
     * does not initialize the 3d scene
     */
    async init() {
        await loadDIVE();

        if (!this.el) {
            return;
        }
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        this.sliderIndex = Number(this.options.sliderPosition);

        this.spatialProductSliderRenderUtil = new SpatialProductSliderRenderUtil(this);

        this.spatialProductSliderRenderUtil.removeDisabled();

        await this.initViewer();
    }

    /**
     * initialize the 3d scene
     * @param force - Will reinitialize the viewer. Otherwise, only the canvas and renderer will be reinitialized.
     */
    public async initViewer() {
        try {
            await super.initViewer();
        } catch (e) {
            this.el?.parentElement?.parentElement?.classList.add('gallery-slider-canvas-disabled');
        } finally {
            this.setReady(true);

            // start rendering when on the correct slide
            this.spatialProductSliderRenderUtil?.initRender();
        }
    }
}
