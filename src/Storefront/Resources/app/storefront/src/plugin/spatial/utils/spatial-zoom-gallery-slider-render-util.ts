import {type tns} from 'tiny-slider';
// @ts-ignore
import type GallerySliderPlugin from '../../slider/gallery-slider.plugin';
import type SpatialZoomGallerySliderViewerPlugin from '../spatial-zoom-gallery-slider-viewer.plugin';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 *
 * This util is responsible for starting and stopping the rendering of the viewer when the slide is active or not.
 * It also listens to the rebuild event of the slider and reinitializes the viewer when needed.
 */
export default class SpatialZoomGallerySliderRenderUtil {

    private readonly zoomModalElement: Element | undefined;
    private zoomModalPlugin;
    private sliderPlugin: GallerySliderPlugin;
    // @ts-ignore
    private tnsSlider: tns;
    private plugin: SpatialZoomGallerySliderViewerPlugin;

    static options = {
        zoomSliderPositionAttribute: 'data-zoom-product-slider-position',
        gallerySliderSelector: '.gallery-slider-row',
        zoomModalSelector: '[data-zoom-modal]',
        zoomModalActionsSelector: '.zoom-modal-actions',
        zoomSliderDisabledClass: 'gallery-slider-canvas-disabled',
    };

    /**
     * @param plugin
     */
    constructor(plugin: SpatialZoomGallerySliderViewerPlugin) {
        this.plugin = plugin;
        if (!this.plugin.el) {
            return;
        }
        const gallerySlider = this.plugin.el.closest(SpatialZoomGallerySliderRenderUtil.options.gallerySliderSelector);
        if (!gallerySlider) {
            return;
        }
        const zoomModalElement = gallerySlider.querySelector(SpatialZoomGallerySliderRenderUtil.options.zoomModalSelector);
        if (!zoomModalElement) {
            return;
        }
        this.zoomModalElement = zoomModalElement;

        // @ts-ignore
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
        this.zoomModalPlugin = window.PluginManager.getPluginInstanceFromElement(this.zoomModalElement, 'ZoomModal');

        // initialize the util once the slider is created & initialized
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
        this.zoomModalPlugin.$emitter.subscribe('initSlider', () => {
            this.plugin.initViewer(true);
        });
    }

    /**
     * Initializes the util.
     */
    public initViewer() {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access
        this.sliderPlugin = this.zoomModalPlugin.gallerySliderPlugin;
        this.tnsSlider = this.sliderPlugin?._slider;

        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
        const currentPage = this.tnsSlider?.getInfo().index ?? 0;
        if (currentPage == this.plugin.sliderIndex) {
            this.changeZoomActionsVisibility(false);
            this.plugin.startRendering();
        }
        this.initEventListeners();
    }

    /**
     * Creates event listeners for the slider.
     * @private
     */
    private initEventListeners() {
        // listen to active slide changes
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
        this.tnsSlider?.events.on('indexChanged', this.indexChangedEvent.bind(this));

        // listen to slider rebuild events
        // @ts-ignore
        this.sliderPlugin?.$emitter.subscribe('rebuild', this.rebuildEvent.bind(this));
    }

    /**
     * rebuild event listener
     * @param event
     * @private
     */
    private rebuildEvent(event: { target: HTMLElement }) {
        this.plugin.setReady(false);
        // @ts-ignore
        this.plugin.el = event.target.querySelector(
            // eslint-disable-next-line @typescript-eslint/restrict-template-expressions
            `[${SpatialZoomGallerySliderRenderUtil.options.zoomSliderPositionAttribute}="${this.plugin.sliderIndex}"]`
        );

        this.plugin.initViewer(false);
        this.initViewer();
    }

    /**
     * indexChanged event listener
     * @param event
     * @private
     */
    private indexChangedEvent(event: { target: HTMLElement, index: number }) {
        // This somehow drastically improves the sliding animation
        // without the requestAnimationFrame, the animation is not displayed or is pretty laggy
        const active = this.plugin.sliderIndex == event.index;

        // Start or stop rendering when the slide is active or not
        if (active) {
            // We should only start rendering after the slider has finished sliding
            setTimeout(() => {
                // recheck if the slide is still active
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-call
                if (this.plugin.sliderIndex == this.tnsSlider.getInfo().index) {
                    this.changeZoomActionsVisibility(false);
                    this.plugin.startRendering();
                }
            }, 500);
        } else {
            this.changeZoomActionsVisibility(true);
            this.plugin.stopRendering();
        }
    }

    /**
     * Change Modal Zoom Actions Visibility
     * @param showZoomActions
     * @private
     */
    private changeZoomActionsVisibility(showZoomActions: boolean): void {
        const zoomActionsEl = document.querySelector(SpatialZoomGallerySliderRenderUtil.options.zoomModalActionsSelector);

        if (showZoomActions) {
            zoomActionsEl?.classList.remove('d-none');
        }
        else {
            zoomActionsEl?.classList.add('d-none');
        }
    }

    /**
     * removes the disabled class
     * @private
     */
    public removeDisabled(): void {
        this.plugin.el?.parentElement?.parentElement?.classList.remove(SpatialZoomGallerySliderRenderUtil.options.zoomSliderDisabledClass);
    }
}
