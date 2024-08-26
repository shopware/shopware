import { type tns } from 'tiny-slider';
import type SpatialProductViewerPlugin from '../spatial-gallery-slider-viewer.plugin';
// @ts-ignore
import type GallerySliderPlugin from '../../slider/gallery-slider.plugin';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 *
 * This util is responsible for starting and stopping the rendering of the viewer when the slide is active or not.
 * It also listens to the rebuild event of the slider and reinitializes the viewer when needed.
 */
export default class SpatialProductSliderRenderUtil {

    private sliderElement: HTMLElement | undefined | null = null;
    // @ts-ignore
    private tnsSlider: tns | null = null;
    private sliderPlugin: GallerySliderPlugin | null = null;
    private plugin: SpatialProductViewerPlugin;

    static options = {
        sliderSelector: '.tns-item',
        gallerySliderSelector: '.gallery-slider-row',
        sliderPositionAttribute: 'data-product-slider-position',
        singleImageGallerySelector: '.gallery-slider-single-image',
        gallerySliderDisabledClass: 'gallery-slider-canvas-disabled',
    };

    /**
     * @param plugin
     */
    constructor(plugin: SpatialProductViewerPlugin) {
        this.plugin = plugin;
        this.init();
    }

    /**
     * Initializes the util.
     */
    public init() {
        this.refreshSliderElements();

        // return if slider element is not found
        if (
            this.sliderElement == null ||
            this.tnsSlider == null
        ) {
            return;
        }

        // init event listeners
        this.initEventListeners();
    }

    /**
     * Initializes the rendering.
     */
    public initRender() {
        // Start rendering when slider is active
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
        const sliderInfo = this.tnsSlider?.getInfo();
        const singleImageGallery = !!this.plugin.el?.closest(
            SpatialProductSliderRenderUtil.options.gallerySliderSelector
        )?.querySelector(SpatialProductSliderRenderUtil.options.singleImageGallerySelector);

        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        if (singleImageGallery || sliderInfo?.slideItems[sliderInfo.index] === this.sliderElement ) {
            this.plugin.startRendering();
        }
    }

    /**
     * Creates event listeners for the slider.
     * @private
     */
    private initEventListeners() {
        // listen to active slide changes
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
        this.tnsSlider?.events.on('indexChanged', this.indexChangedEvent.bind(this));

        this.sliderPlugin?.$emitter.subscribe('rebuild', this.rebuildEvent.bind(this));
    }

    /**
     * removes the disabled class
     * @private
     */
    public removeDisabled(): void {
        this.plugin.el?.parentElement?.parentElement?.classList.remove(SpatialProductSliderRenderUtil.options.gallerySliderDisabledClass);
    }

    /**
     * rebuild event listener
     * @param t
     * @private
     */
    private rebuildEvent(t: { target: HTMLElement }) {
        this.plugin.setReady(false);
        // @ts-ignore
        this.plugin.el = t.target.querySelector(
            // eslint-disable-next-line @typescript-eslint/restrict-template-expressions
            `[${SpatialProductSliderRenderUtil.options.sliderPositionAttribute}="${this.plugin.sliderIndex}"]`
        );

        this.init();
        this.plugin.initViewer(false);
    }

    /**
     * index changed event listener
     * @param a
     * @private
     */
    private indexChangedEvent(a: { index: number }) {
        const active = this.plugin.sliderIndex == a.index;

        // Start or stop rendering when the slide is active or not
        if (active) {
            // We should only start rendering after the slider has finished sliding
            setTimeout(() => {
                // recheck if the slide is still active
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
                if (this.plugin.sliderIndex == this.tnsSlider.getInfo().index) {
                    this.plugin.startRendering();
                }
            }, 500);
        } else {
            this.plugin.stopRendering();
        }
    }

    /**
     * Refreshes the slider elements.
     */
    private refreshSliderElements() {
        this.sliderElement = this.plugin?.el?.closest(SpatialProductSliderRenderUtil.options.sliderSelector);
        this.sliderPlugin = this.getSliderPlugin();

        if (this.sliderPlugin != null) {
            // @ts-ignore
            this.tnsSlider = this.sliderPlugin._slider;
        }
    }

    /**
     * Finds the slider plugin element in the DOM.
     * @returns {GallerySliderPlugin|null}
     * @private
     */
    private getSliderPlugin(): GallerySliderPlugin | null {
        const sliderPluginElement = this.plugin?.el?.closest(
            SpatialProductSliderRenderUtil.options.gallerySliderSelector
        );

        // Return null if no slider element is found
        if (sliderPluginElement == null || sliderPluginElement == undefined) {
            return null;
        }

        // Get the slider plugin instance
        // @ts-ignore
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
        const sliderPlugin = window.PluginManager.getPluginInstanceFromElement(sliderPluginElement, 'GallerySlider');

        if (sliderPlugin == null) {
            return null;
        }

        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
        return sliderPlugin;
    }
}
