import BaseSliderPlugin from 'src/plugin/slider/base-slider.plugin';
import NativeEventEmitter from 'src/helper/emitter.helper';

describe('BaseSliderPlugin tests', () => {
    let baseSliderPlugin = undefined;
    let spyInit = jest.fn();

    beforeEach(() => {
        document.body.innerHTML = `
            <div class="base-slider image-slider js-slider-initialized">
            </div>
        `;
        const element = document.querySelector('.base-slider');

        window.router = [];

        window.breakpoints = {
            lg: 992,
            md: 768,
            sm: 576,
            xl: 1200,
            xs: 0,
        }

        window.PluginManager = {
            getPluginInstancesFromElement: () => {
                return new Map();
            },
            getPlugin: () => {
                return {
                    get: () => [],
                };
            },
            initializePlugins: undefined,
        };

        document.$emitter = new NativeEventEmitter();

        // mock base slider plugins
        baseSliderPlugin = new BaseSliderPlugin(element);

        // create spy elements
        baseSliderPlugin.init = spyInit;
    });


    test('base slider plugin exists', () => {
        expect(typeof baseSliderPlugin).toBe('object');
    });

    test('_initSlider should be call when slider init', () => {
        const spyInitSlider = jest.spyOn(baseSliderPlugin, '_initSlider');

        baseSliderPlugin._initSlider();

        expect(spyInitSlider).toHaveBeenCalled();
    });

    test('_getSettings should be call when slider init', () => {
        const spyGetSettings = jest.spyOn(baseSliderPlugin, '_getSettings');
        baseSliderPlugin._getSettings('xl');

        expect(spyGetSettings).toHaveBeenCalled();
    });

    test('should show settings when set configuration at option of slider', () => {
        document.body.innerHTML = `
            <div class="base-slider image-slider js-slider-initialized" data-base-slider="true" data-base-slider-options="">
            </div>
        `;
        const element = document.querySelector('.base-slider');

        const sliderInstance = new BaseSliderPlugin(element);
        sliderInstance.options.slider = {
            ...sliderInstance.options.slider,
            autoplay: true,
            speed: 300,
            autoplayTimeout: 5000,
        }

        sliderInstance._getSettings('md');

        expect(sliderInstance._sliderSettings.autoplay).toBe(true);
        expect(sliderInstance._sliderSettings.speed).toBe(300);
        expect(sliderInstance._sliderSettings.autoplayTimeout).toBe(5000);
    });

    test('should apply accessibility tweaks', () => {
        document.body.innerHTML = `
            <div id="image-slider" class="base-slider image-slider js-slider-initialized" data-base-slider="true" data-base-slider-options="">
                <div class="image-slider-container" data-base-slider-container="true">
                    <div id="item-0" class="image-slider-item tns-slide-cloned">
                        <img src="test.jpg" alt="Test Image" title="Test Image">
                    </div>
                    <div id="item-1" class="image-slider-item">
                        <img src="test.jpg" alt="Test Image" title="Test Image" tabindex="0">
                    </div>
                    <div id="item-2" class="image-slider-item">
                        <img src="test.jpg" alt="Test Image" title="Test Image" tabindex="0">
                    </div>
                    <div id="item-3" class="image-slider-item">
                        <img src="test.jpg" alt="Test Image" title="Test Image" tabindex="0">
                    </div>
                </div>
                <div class="image-slider-controls-container"></div>
            </div>
        `;

        const sliderElement = document.getElementById('image-slider');
        const sliderItems = sliderElement.querySelectorAll('.image-slider-item');
        const sliderControls = sliderElement.querySelector('.image-slider-controls-container');
        const cloneElement = sliderElement.querySelector('.tns-slide-cloned');
        const cloneElementImg = cloneElement.querySelector('img');
        const focusElement = document.getElementById('item-2');
        const focusElementImg = focusElement.querySelector('img');

        const sliderInstance = new BaseSliderPlugin(sliderElement);
        const sliderInfo = {
            controlsContainer: sliderControls,
            slideItems: sliderItems,
        }

        sliderInstance._slider = {
            goTo: jest.fn(),
            getInfo: () => {
                return {
                    index: 0,
                    cloneCount: 1,
                }
            },
        }

        const spyGoTo = jest.spyOn(sliderInstance._slider, 'goTo');
        const spyGetInfo = jest.spyOn(sliderInstance._slider, 'getInfo');

        sliderInstance._initAccessibilityTweaks(sliderInfo, sliderElement);

        expect(sliderControls.getAttribute('tabindex')).toBe('-1');
        expect(cloneElementImg.getAttribute('tabindex')).toBe('-1');

        focusElementImg.focus();
        expect(document.activeElement).toBe(focusElementImg);

        const focusinEvent = new Event('focusin');
        focusElement.dispatchEvent(focusinEvent);

        expect(spyGetInfo).toBeCalled();
        expect(spyGoTo).toBeCalled();
    });
});
