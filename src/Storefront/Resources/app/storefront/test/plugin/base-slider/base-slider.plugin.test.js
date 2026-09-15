import BaseSliderPlugin from 'src/plugin/slider/base-slider.plugin';
import NativeEventEmitter from 'src/helper/emitter.helper';

describe('BaseSliderPlugin tests', () => {
    let baseSliderPlugin = undefined;
    let spyInit = jest.fn();

    const mockCurrentTime = (video, initial = 0) => {
        let currentTime = initial;
        Object.defineProperty(video, 'currentTime', {
            get: () => currentTime,
            set: (value) => { currentTime = value; },
            configurable: true,
        });
    };

    const definePaused = (video, paused) => {
        Object.defineProperty(video, 'paused', {
            get: () => paused,
            configurable: true,
        });
    };

    const createSliderWithSlides = () => {
        document.body.innerHTML = `
            <div class="base-slider image-slider js-slider-initialized">
                <div class="image-slider-item" id="slide-0"><img src="test.jpg"></div>
                <div class="image-slider-item" id="slide-1"><video></video></div>
            </div>
        `;

        const element = document.querySelector('.base-slider');

        return {
            sliderInstance: new BaseSliderPlugin(element),
            slideItems: document.querySelectorAll('.image-slider-item'),
        };
    };

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
        };

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
            initializePluginsInParentElement: jest.fn(),
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
        };

        sliderInstance._getSettings('md');

        expect(sliderInstance._sliderSettings.autoplay).toBe(true);
        expect(sliderInstance._sliderSettings.speed).toBe(300);
        expect(sliderInstance._sliderSettings.autoplayTimeout).toBe(5000);
    });

    test('rebuild should re-initialise on the currently displayed slide', () => {
        const element = document.querySelector('.base-slider');
        const sliderInstance = new BaseSliderPlugin(element);

        let startIndexAtReInit;
        jest.spyOn(sliderInstance, '_initSlider').mockImplementation(() => {
            startIndexAtReInit = sliderInstance._sliderSettings.startIndex;
        });

        sliderInstance._slider = {
            getInfo: () => {
                return {
                    index: 4,
                    displayIndex: 1,
                    slideCount: 4,
                };
            },
        };

        sliderInstance.rebuild('xl');

        expect(startIndexAtReInit).toBe(0);
    });

    test('getActiveSlideElement returns undefined when the slider info has no slideItems', () => {
        const { sliderInstance } = createSliderWithSlides();

        sliderInstance._slider = {
            getInfo: () => ({ displayIndex: 1 }),
        };

        expect(sliderInstance.getActiveSlideElement()).toBeUndefined();
    });

    test('_captureActiveVideoState returns null when the slider is not initialised', () => {
        const { sliderInstance } = createSliderWithSlides();
        sliderInstance._slider = false;

        expect(sliderInstance._captureActiveVideoState()).toBeNull();
    });

    test('_captureActiveVideoState returns null when the active slide has no video', () => {
        const { sliderInstance, slideItems } = createSliderWithSlides();

        sliderInstance._slider = {
            getInfo: () => ({ slideItems, displayIndex: 0 }),
        };

        expect(sliderInstance._captureActiveVideoState()).toBeNull();
    });

    test('_captureActiveVideoState captures the playback position of the active slide video', () => {
        const { sliderInstance, slideItems } = createSliderWithSlides();
        const video = slideItems[1].querySelector('video');
        mockCurrentTime(video, 12.5);
        definePaused(video, false);

        sliderInstance._slider = {
            getInfo: () => ({ slideItems, displayIndex: 1 }),
        };

        expect(sliderInstance._captureActiveVideoState()).toEqual({
            currentTime: 12.5,
            wasPlaying: true,
        });
    });

    test('_restoreActiveVideoState does nothing when there is no captured state', () => {
        const { sliderInstance, slideItems } = createSliderWithSlides();
        const video = slideItems[1].querySelector('video');
        video.play = jest.fn();

        sliderInstance._slider = {
            getInfo: () => ({ slideItems, displayIndex: 1 }),
        };

        expect(() => sliderInstance._restoreActiveVideoState(null)).not.toThrow();
        expect(video.play).not.toHaveBeenCalled();
    });

    test('_restoreActiveVideoState resumes playback on the new active slide video', () => {
        const { sliderInstance, slideItems } = createSliderWithSlides();
        const video = slideItems[1].querySelector('video');
        mockCurrentTime(video);
        video.play = jest.fn(() => Promise.resolve());

        sliderInstance._slider = {
            getInfo: () => ({ slideItems, displayIndex: 1 }),
        };

        sliderInstance._restoreActiveVideoState({ currentTime: 8, wasPlaying: true });

        expect(video.currentTime).toBe(8);
        expect(video.play).toHaveBeenCalled();
    });

    test('_restoreActiveVideoState restores the position without resuming playback when it was paused', () => {
        const { sliderInstance, slideItems } = createSliderWithSlides();
        const video = slideItems[1].querySelector('video');
        mockCurrentTime(video);
        video.play = jest.fn(() => Promise.resolve());

        sliderInstance._slider = {
            getInfo: () => ({ slideItems, displayIndex: 1 }),
        };

        sliderInstance._restoreActiveVideoState({ currentTime: 3, wasPlaying: false });

        expect(video.currentTime).toBe(3);
        expect(video.play).not.toHaveBeenCalled();
    });

    test('rebuild preserves the video playback state across the destroy/init cycle', () => {
        const { sliderInstance, slideItems } = createSliderWithSlides();
        const video = slideItems[1].querySelector('video');
        mockCurrentTime(video, 4.2);
        definePaused(video, false);
        video.play = jest.fn(() => Promise.resolve());

        jest.spyOn(sliderInstance, '_initSlider').mockImplementation(() => {});

        sliderInstance._slider = {
            getInfo: () => ({ index: 1, displayIndex: 1, slideCount: 2, slideItems }),
        };

        sliderInstance.rebuild('xl');

        expect(video.currentTime).toBe(4.2);
        expect(video.play).toHaveBeenCalled();
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
        };

        sliderInstance._sliderSettings = {
            autoplay: true,
        };

        sliderInstance._slider = {
            goTo: jest.fn(),
            pause: jest.fn(),
            getInfo: () => {
                return {
                    index: 0,
                    cloneCount: 1,
                };
            },
        };

        const spyGoTo = jest.spyOn(sliderInstance._slider, 'goTo');
        const spyPause = jest.spyOn(sliderInstance._slider, 'pause');
        const spyGetInfo = jest.spyOn(sliderInstance._slider, 'getInfo');

        sliderInstance._initAccessibilityTweaks(sliderInfo, sliderElement);

        expect(sliderControls.getAttribute('tabindex')).toBe('-1');
        expect(cloneElementImg.getAttribute('tabindex')).toBe('-1');

        focusElementImg.focus();
        expect(document.activeElement).toBe(focusElementImg);

        const focusEvent = new Event('keyup');
        focusEvent.key = 'Tab';
        focusElement.dispatchEvent(focusEvent);

        expect(spyGetInfo).toHaveBeenCalled();
        expect(spyPause).toHaveBeenCalled();
        expect(spyGoTo).toHaveBeenCalled();

        const scrollEvent = new Event('scroll');
        const scrollEventSpy = jest.spyOn(scrollEvent, 'preventDefault');

        sliderElement.dispatchEvent(scrollEvent);

        expect(sliderElement.scrollLeft).toBe(0);
        expect(scrollEventSpy).toHaveBeenCalled();
    });
});
