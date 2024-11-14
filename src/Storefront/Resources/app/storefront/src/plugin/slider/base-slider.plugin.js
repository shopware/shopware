import Plugin from 'src/plugin-system/plugin.class';
import { tns } from 'tiny-slider';
import ViewportDetection from 'src/helper/viewport-detection.helper';
import SliderSettingsHelper from 'src/plugin/slider/helper/slider-settings.helper';

export default class BaseSliderPlugin extends Plugin {
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
                xxl: {},
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
     * Re-initialise the slider with options for the current viewport
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
     * @private
     */
    _registerEvents() {
        if (this._slider) {
            document.addEventListener('Viewport/hasChanged', () => this.rebuild(ViewportDetection.getCurrentViewport()));
        }
    }

    /**
     * @private
     */
    _correctIndexSettings() {
        this.options.slider.startIndex -= 1;
        this.options.slider.startIndex = (this.options.slider.startIndex < 0) ? 0 : this.options.slider.startIndex;
    }

    /**
     * Returns the slider settings for the current viewport
     *
     * @param {String} viewport
     * @private
     */
    _getSettings(viewport) {
        this._sliderSettings = SliderSettingsHelper.getViewportSettings(this.options.slider, viewport);
    }

    getCurrentSliderIndex() {
        if (!this._slider) {
            return;
        }

        const sliderInfo = this._slider.getInfo();

        let currentIndex = sliderInfo.displayIndex % sliderInfo.slideCount;
        currentIndex = (currentIndex === 0) ? sliderInfo.slideCount : currentIndex;

        return currentIndex - 1;
    }

    getActiveSlideElement() {
        const info = this._slider.getInfo();

        return info.slideItems[info.index];
    }

    /**
     * @private
     */
    _initSlider() {
        this.el.classList.add(this.options.initializedCls);

        const container = this.el.querySelector(this.options.containerSelector);
        const controlsContainer = this.el.querySelector(this.options.controlsSelector);
        const onInit = (sliderInfo) => {
            window.PluginManager.initializePlugins();

            this.$emitter.publish('initSlider');

            if (window.Feature.isActive('ACCESSIBILITY_TWEAKS')) {
                this._initAccessibilityTweaks(sliderInfo, this.el);
            }
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
     * Initializes some accessibility improvements for the tiny-slider package.
     *
     * @param {Object} sliderInfo
     * @param {HTMLElement} wrapperEl
     * @private
     */
    _initAccessibilityTweaks(sliderInfo, wrapperEl) {
        const sliderItems = sliderInfo.slideItems;

        if (sliderInfo.controlsContainer) {
            // Remove controls div container from tab index for better accessibility.
            sliderInfo.controlsContainer.setAttribute('tabindex', '-1');
        }

        const wrapper = wrapperEl || this.el;
        wrapper.scrollLeft = 0;

        // Prevent native browser function to scroll items into view.
        wrapper.addEventListener('scroll', (event) => {
            wrapper.scrollLeft = 0;
            event.preventDefault();
        });

        for (let index = 0; index < sliderItems.length; index++) {
            const item = sliderItems.item(index);

            if (item.classList.contains('tns-slide-cloned')) {
                const selectableElements = item.querySelectorAll('a, button, img');

                // Hide selectable elements within cloned elements from screen readers.
                for (const selectableEl of selectableElements) {
                    selectableEl.setAttribute('tabindex', '-1');
                }

            } else {
                // Tracking the focus within slider elements to keep them in view when navigating via keyboard.
                item.addEventListener('keyup', (event) => {
                    if (event.key !== 'Tab') {
                        return;
                    }

                    const currentSliderInfo = this._slider.getInfo();

                    // Prevent native browser function to scroll items into view.
                    wrapper.scrollLeft = 0;

                    // Stop autoplay if an element gets focus via keyboard navigation.
                    if (this._sliderSettings.autoplay) {
                        this._slider.pause();
                    }

                    // Keep the element which has focus on first slide position.
                    if (index !== currentSliderInfo.index) {
                        const newSlide = index - currentSliderInfo.cloneCount;

                        this._slider.goTo(newSlide);
                    }
                });
            }
        }
    }

    /**
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
