import deepmerge from 'deepmerge';
import { tns } from 'tiny-slider';
import ViewportDetection from 'src/helper/viewport-detection.helper';
import SliderSettingsHelper from 'src/plugin/slider/helper/slider-settings.helper';
import Iterator from 'src/helper/iterator.helper';
import BaseSliderPlugin from 'src/plugin/slider/base-slider.plugin';
import DomAccess from 'src/helper/dom-access.helper';

export default class GallerySliderPlugin extends BaseSliderPlugin {
    static options = deepmerge(BaseSliderPlugin.options, {
        containerSelector: '[data-gallery-slider-container=true]',
        thumbnailsSelector: '[data-gallery-slider-thumbnails=true]',
        controlsSelector: '[data-gallery-slider-controls=true]',
        thumbnailControlsSelector: '[data-thumbnail-slider-controls=true]',
        baseSliderWrapperClass: 'base-slider',
        dotActiveClass: 'tns-nav-active',
        navDotDataAttr: 'data-nav-dot',
        loadingCls: 'is-loading',
        slider: {
            preventScrollOnTouch: 'auto',
            startIndex: 1,
            responsive: {
                xs: {},
                sm: {},
                md: {},
                lg: {},
                xl: {},
                xxl: {},
            },
        },
        thumbnailSlider: {
            enabled: true,
            loop: false,
            nav: false,
            items: 5,
            gutter: 10,
            startIndex: 1,
            preventScrollOnTouch: 'force',
            ariaLive: false,
            responsive: {
                xs: {},
                sm: {},
                md: {},
                lg: {},
                xl: {},
                xxl: {},
            },
        },
    });

    init() {
        this._slider = false;
        this._thumbnailSlider = false;

        if (!this.el.classList.contains(this.options.initializedCls)) {
            this.options.slider = SliderSettingsHelper.prepareBreakpointPxValues(this.options.slider);
            this.options.thumbnailSlider = SliderSettingsHelper.prepareBreakpointPxValues(this.options.thumbnailSlider);
            this._correctIndexSettings();

            this._getSettings(ViewportDetection.getCurrentViewport());

            this._initSlider();
            this._registerEvents();
        }
    }

    destroy() {
        if (this._slider && typeof this._slider.destroy === 'function') {
            try {
                this._slider.destroy();
            } catch (e) {
                // don't handle
            }
        }

        if (this._thumbnailSlider && typeof this._thumbnailSlider.destroy === 'function') {
            try {
                this._thumbnailSlider.destroy();
            } catch (e) {
                // don't handle
            }
        }

        this.el.classList.remove(this.options.initializedCls);
    }

    /**
     * Re-initialise the slider with options for the current viewport
     *
     * @param viewport
     */
    rebuild(viewport = ViewportDetection.getCurrentViewport()) {
        this._getSettings(viewport.toLowerCase());

        // get the current index and use it as the start index
        try {
            if (this._slider) {
                const currentIndex = this.getCurrentSliderIndex();
                this._sliderSettings.startIndex = currentIndex;
                this._thumbnailSliderSettings.startIndex = currentIndex;
            }

            this.destroy();
            this._initSlider();
        } catch (e) {
            // something went wrong
        }

        this.$emitter.publish('rebuild');
    }

    /**
     * @private
     */
    _correctIndexSettings() {
        super._correctIndexSettings();

        this.options.thumbnailSlider.startIndex -= 1;
        this.options.thumbnailSlider.startIndex = (this.options.thumbnailSlider.startIndex < 0) ? 0 : this.options.thumbnailSlider.startIndex;
    }

    /**
     * Returns the slider settings for the current viewport
     *
     * @param viewport
     * @private
     */
    _getSettings(viewport) {
        super._getSettings(viewport);

        this._thumbnailSliderSettings = SliderSettingsHelper.getViewportSettings(this.options.thumbnailSlider, viewport);
    }

    /**
     * Sets the active dot depending on the current slider index
     *
     * @private
     */
    _setActiveDot() {
        const currentIndex = this.getCurrentSliderIndex();

        Iterator.iterate(this._dots, dot => dot.classList.remove(this.options.dotActiveClass));

        const currentDot = this._dots[currentIndex];
        if (!currentDot) {
            return;
        }

        currentDot.classList.add(this.options.dotActiveClass);
    }

    /**
     * @private
     */
    _initDots() {
        this._dots = this.el.querySelectorAll('[' + this.options.navDotDataAttr + ']');
        if (!this._dots) {
            return;
        }

        Iterator.iterate(this._dots, dot => {
            dot.addEventListener('click', this._onDotClick.bind(this));
        });

        this._setActiveDot();

        if (this._slider) {
            this._slider.events.on('indexChanged', () => {
                this._setActiveDot();
            });
        }
    }

    /**
     * Navigfates to image on dot click
     *
     * @private
     */
    _onDotClick(event) {
        const currentIndex = DomAccess.getDataAttribute(event.target, this.options.navDotDataAttr);

        this._slider.goTo(currentIndex - 1);
    }

    /**
     * @private
     */
    _initSlider() {
        this.el.classList.add(this.options.initializedCls);

        const container = this.el.querySelector(this.options.containerSelector);
        const navContainer = this.el.querySelector(this.options.thumbnailsSelector);
        const controlsContainer = this.el.querySelector(this.options.controlsSelector);

        const hasThumbnails = (!!navContainer);

        if (container) {
            const onInit = (sliderInfo) => {
                window.PluginManager.initializePlugin('Magnifier', '[data-magnifier]');
                window.PluginManager.initializePlugin('ZoomModal', '[data-zoom-modal]');

                if (!hasThumbnails) {
                    this.el.classList.remove(this.options.loadingCls);
                }

                const containerEl = this.el.getElementsByClassName(this.options.baseSliderWrapperClass).item(0);

                this._initAccessibilityTweaks(sliderInfo, containerEl);

                this.$emitter.publish('initGallerySlider');
            };

            if (this._sliderSettings.enabled) {
                container.style.display = '';

                this._slider = tns({
                    container,
                    controlsContainer,
                    navContainer,
                    onInit,
                    ...this._sliderSettings,
                });

                this._initDots();
            } else {
                container.style.display = 'none';
            }
        }

        if (navContainer) {
            const thumbnailControls = this.el.querySelector(this.options.thumbnailControlsSelector);

            const onInitThumbnails = (sliderInfo) => {
                if (hasThumbnails) {
                    this.el.classList.remove(this.options.loadingCls);
                }
                this.$emitter.publish('initThumbnailSlider');

                // Remove controls div container from tab index for better accessibility.
                sliderInfo.controlsContainer.setAttribute('tabindex', '-1');
            };

            if (this._thumbnailSliderSettings.enabled) {
                navContainer.style.display = '';

                this._thumbnailSlider = tns({
                    container: navContainer,
                    controlsContainer: thumbnailControls,
                    onInit: onInitThumbnails,
                    ...this._thumbnailSliderSettings,
                });
            } else {
                navContainer.style.display = 'none';
                this.el.classList.remove(this.options.loadingCls);
            }
        }

        this._navigateThumbnailSlider();

        this.$emitter.publish('afterInitSlider');
    }

    /**
     * Navigates through the thumbnail slider automatically, if the selected slider image is hidden
     *
     * @private
     */
    _navigateThumbnailSlider() {
        const thumbnailSlideInfo = this._thumbnailSlider && this._thumbnailSlider.getInfo();

        if (!(this._slider && thumbnailSlideInfo)) {
            return;
        }

        this._slider.events.on('indexChanged', () => {
            const currentIndex = this.getCurrentSliderIndex();

            if (thumbnailSlideInfo.slideItems[currentIndex].getAttribute('aria-hidden')) {
                this._thumbnailSlider.goTo(currentIndex - 1);
            }
        });
    }
}
