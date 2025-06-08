import SpatialBaseViewerPlugin from './spatial-base-viewer.plugin';
import SpatialZoomGallerySliderRenderUtil from './utils/spatial-zoom-gallery-slider-render-util';
import { loadDIVE } from './utils/spatial-dive-load-util';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
 */
export default class SpatialZoomGallerySliderViewerPlugin extends SpatialBaseViewerPlugin {

    private SpatialZoomGallerySliderRenderUtil: SpatialZoomGallerySliderRenderUtil | undefined;

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

        this.SpatialZoomGallerySliderRenderUtil = new SpatialZoomGallerySliderRenderUtil(this);

        this.SpatialZoomGallerySliderRenderUtil.removeDisabled();

        await this.initViewer();
    }

    /**
     * initialize plugin
     * @param force - Will reinitialize the viewer entirely. Otherwise, only the canvas and renderer will be reinitialized.
     */
    public async initViewer() {
        try {
            await super.initViewer();
        } catch (e) {
            this.el?.parentElement?.parentElement?.classList.add('gallery-slider-canvas-disabled');
        } finally {
            this.setReady(true);

            // start rendering when on the correct slide
            this.SpatialZoomGallerySliderRenderUtil?.initViewer();
        }
    }
}
