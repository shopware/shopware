import Plugin from 'asset/script/helper/plugin/plugin.class';
import { tns } from 'tiny-slider/src/tiny-slider.module';
import ViewportDetection from 'asset/script/helper/viewport-detection.helper';
import SliderSettingsHelper from 'asset/script/plugin/image-slider/helper/image-slider-settings.helper';
import PluginManager from 'asset/script/helper/plugin/plugin.manager';

/**
 *
 */
export default class ImageSliderPlugin extends Plugin {

    /**
     * default slider options
     *
     * @type {*}
     */
    static options = {
        containerSelector: '[data-image-slider-container=true]',
        thumbnailsSelector: '[data-image-slider-thumbnails=true]',
        controlsSelector: '[data-image-slider-controls=true]',
        slider: {
            enabled: true,
            loop: true,
            items: 1,
            mode: 'carousel',
            autoplay: false,
            startIndex: 0,
            navAsThumbnails: true,
            speed: 500,
            responsive: {
                xs: {},
                sm: {},
                md: {},
                lg: {},
                xl: {},
            },
        },
        thumbnailSlider: {
            enabled: true,
            loop: false,
            nav: false,
            controls: false,
            items: 5,
            mode: 'carousel',
            autoplay: false,
            startIndex: 0,
            speed: 500,
            responsive: {
                xs: {
                    enabled: false,
                },
                sm: {
                    enabled: false,
                },
                md: {
                    items: 3,
                    center: true,
                },
                lg: {},
                xl: {
                    axis: 'vertical',
                },
            },
        }
    };

    init() {
        this.initializedCls = 'image-slider-initialized';
        this._slider = false;
        this._thumbnailSlider = false;

        if (!this.el.classList.contains(this.initializedCls)) {
            this.options.slider = SliderSettingsHelper.prepareBreakpointPxValues(this.options.slider);
            this.options.thumbnailSlider = SliderSettingsHelper.prepareBreakpointPxValues(this.options.thumbnailSlider);

            this._getSettings(ViewportDetection.getCurrentViewport());

            this._initSlider();
            this._registerEvents();
        }
    }

    /**
     * destroys the slider
     */
    destroy() {
        if (this._slider && typeof this._slider.destroy === 'function') {
            this._slider.destroy();
        }
        if (this._thumbnailSlider && typeof this._thumbnailSlider.destroy === 'function') {
            this._thumbnailSlider.destroy();
        }
        this.el.classList.remove(this.initializedCls);
    }

    /**
     * register all needed events
     *
     * @private
     */
    _registerEvents() {
        if (this._slider) {
            document.addEventListener(ViewportDetection.EVENT_VIEWPORT_HAS_CHANGED(), () => this.rebuild(ViewportDetection.getCurrentViewport()));
        }
    }

    /**
     * reinitialise the slider
     * with the options for our viewport
     *
     * @param viewport
     */
    rebuild(viewport) {
        this._getSettings(viewport);
        // get the current index and use it as the start index
        if (this._slider) {
            const currentIndex = this._getCurrentIndex();
            this._sliderSettings.startIndex = currentIndex;
            this._thumbnailSliderSettings.startIndex = currentIndex;
        }

        this.destroy();
        this._initSlider();
    }

    /**
     * returns the slider settings for the current viewport
     *
     * @param viewport
     * @private
     */
    _getSettings(viewport) {
        this._sliderSettings = SliderSettingsHelper.getViewportSettings(this.options.slider, viewport);
        this._thumbnailSliderSettings = SliderSettingsHelper.getViewportSettings(this.options.thumbnailSlider, viewport);
    }

    /**
     * returns the current slider index
     *
     * @return {*}
     */
    getCurrentSliderIndex() {
        if (!this._slider) {
            return 0;
        }

        const info = this._slider.getInfo();

        return info.index;
    }

    /**
     * initialize the slider
     *
     * @private
     */
    _initSlider() {
        this.el.classList.add(this.initializedCls);

        const container = this.el.querySelector(this.options.containerSelector);
        const navContainer = this.el.querySelector(this.options.thumbnailsSelector);
        const controlsContainer = this.el.querySelector(this.options.controlsSelector);

        if (container) {
            if (this._sliderSettings.enabled) {
                container.style.display = '';
                this._slider = tns({
                    container,
                    controlsContainer,
                    navContainer,
                    ...this._sliderSettings
                });

                PluginManager.executePlugin('Magnifier','[data-magnifier]');
                PluginManager.executePlugin('ZoomModal','[data-zoom-modal]');
            } else {
                container.style.display = 'none';
            }
        }

        if (navContainer) {
            if (this._thumbnailSliderSettings.enabled) {
                navContainer.style.display = '';
                this._thumbnailSlider = tns({
                    container: navContainer,
                    ...this._thumbnailSliderSettings
                });

                this._slideThumbnails();
                this._activateThumbnailNavigationItem();

            } else {
                navContainer.style.display = 'none';
            }
        }
    }

    /**
     * slides the thumbnails to match up
     * with the main slider
     *
     * @private
     */
    _slideThumbnails() {
        this._slider.events.on('indexChanged', () => {
            const currentIndex = this._getCurrentIndex();
            this._thumbnailSlider.goTo(currentIndex);
        });

        this._thumbnailSlider.events.on('indexChanged', () => this._activateThumbnailNavigationItem());
    }

    /**
     * activates the currently active
     * navigation thumbnail
     *
     * @private
     */
    _activateThumbnailNavigationItem() {
        const thumbnailSliderInfo = this._thumbnailSlider.getInfo();
        const thumbnailSlides = thumbnailSliderInfo.slideItems;
        const currentIndex = thumbnailSliderInfo.index;
        const activeClass = 'tns-nav-active';

        Object.values(thumbnailSlides).forEach((slide) => slide.classList.remove(activeClass));
        thumbnailSlides[currentIndex].classList.add(activeClass);
    }

    /**
     * returns the current index of the main slider
     *
     * @return {number}
     * @private
     */
    _getCurrentIndex() {
        const sliderInfo = this._slider.getInfo();

        let currentIndex = sliderInfo.index % sliderInfo.slideCount;
        currentIndex = (currentIndex === 0) ? sliderInfo.slideCount : currentIndex;

        return currentIndex - 1;
    }
}
