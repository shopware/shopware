import Plugin from 'src/plugin-system/plugin.class';
import { tns } from 'tiny-slider/src/tiny-slider.module';
import ViewportDetection from 'src/helper/viewport-detection.helper';
import SliderSettingsHelper from 'src/plugin/slider/helper/slider-settings.helper';
import PluginManager from 'src/plugin-system/plugin.manager';

export default class BaseSliderPlugin extends Plugin {
    /**
     * default slider options
     *
     * @type {*}
     */
    static options = {
        initializedCls: 'js-slider-initialized',
        containerSelector: '[data-base-slider-container=true]',
        controlsSelector: '[data-base-slider-controls=true]',
        slider: {
            enabled: true,
            responsive: {
                xs: {},
                sm: {},
                md: {},
                lg: {},
                xl: {},
            },
        },
    };

    init() {
        this._slider = false;

        if (!this.el.classList.contains(this.options.initializedCls)) {
            this.options.slider = SliderSettingsHelper.prepareBreakpointPxValues(this.options.slider);
            this._correctIndexSettings();

            this._getSettings(ViewportDetection.getCurrentViewport());

            this._initSlider();
            this._registerEvents();
        }
    }

    /**
     * since the tns slider indexes internally with 0
     * but the setting starts at 1 we have to subtract 1
     * to have the correct index
     *
     * @private
     */
    _correctIndexSettings() {
        this.options.slider.startIndex -= 1;
        this.options.slider.startIndex = (this.options.slider.startIndex < 0) ? 0 : this.options.slider.startIndex;
    }

    /**
     * destroys the slider
     */
    destroy() {
        if (this._slider && typeof this._slider.destroy === 'function') {
            try {
                this._slider.destroy();
            } catch (e) {
                // don't handle
            }
        }

        this.el.classList.remove(this.options.initializedCls);
    }

    /**
     * register all needed events
     *
     * @private
     */
    _registerEvents() {
        if (this._slider) {
            document.addEventListener('Viewport/hasChanged', () => this.rebuild(ViewportDetection.getCurrentViewport()));
        }
    }

    /**
     * reinitialise the slider
     * with the options for our viewport
     *
     * @param viewport
     * @param resetIndex
     */
    rebuild(viewport = ViewportDetection.getCurrentViewport(), resetIndex = false) {
        this._getSettings(viewport.toLowerCase());

        // get the current index and use it as the start index
        try {
            if (this._slider && !resetIndex) {
                const currentIndex = this._getCurrentIndex();
                this._sliderSettings.startIndex = currentIndex;
            }

            this.destroy();
            this._initSlider();
        } catch (e) {
            // something went wrong
        }

        this.$emitter.publish('rebuild');
    }

    /**
     * returns the slider settings for the current viewport
     *
     * @param viewport
     * @private
     */
    _getSettings(viewport) {
        this._sliderSettings = SliderSettingsHelper.getViewportSettings(this.options.slider, viewport);
    }

    /**
     * returns the current slider index
     *
     * @return {*}
     */
    getCurrentSliderIndex() {
        if (!this._slider) {
            return;
        }

        const sliderInfo = this._slider.getInfo();

        let currentIndex = sliderInfo.displayIndex % sliderInfo.slideCount;
        currentIndex = (currentIndex === 0) ? sliderInfo.slideCount : currentIndex;

        return currentIndex - 1;
    }

    /**
     * returns the active slider item
     *
     * @return {*}
     */
    getActiveSlideElement() {
        const info = this._slider.getInfo();

        return info.slideItems[info.index];
    }

    /**
     * initialize the slider
     *
     * @private
     */
    _initSlider() {
        this.el.classList.add(this.options.initializedCls);

        const container = this.el.querySelector(this.options.containerSelector);
        const controlsContainer = this.el.querySelector(this.options.controlsSelector);
        const onInit = () => {
            PluginManager.initializePlugins();

            this.$emitter.publish('initSlider');
        };

        if (container) {
            if (this._sliderSettings.enabled) {
                container.style.display = '';
                this._slider = tns({
                    container,
                    controlsContainer,
                    onInit,
                    ...this._sliderSettings,
                });
            } else {
                container.style.display = 'none';
            }
        }

        this.$emitter.publish('afterInitSlider');
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
