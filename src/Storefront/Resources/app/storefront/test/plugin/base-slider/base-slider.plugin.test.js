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
});
