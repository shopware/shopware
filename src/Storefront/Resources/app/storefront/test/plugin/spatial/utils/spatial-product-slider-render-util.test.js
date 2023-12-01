import SpatialProductSliderRenderUtil from 'src/plugin/spatial/utils/spatial-product-slider-render-util';

/**
 * @package innovation
 */
describe('SpatialProductSliderRenderUtil', () => {
    let SpatialProductSliderRenderUtilObject = undefined;

    beforeEach(() => {
        jest.clearAllMocks();

        SpatialProductSliderRenderUtilObject = new SpatialProductSliderRenderUtil();
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    test('SpatialProductSliderRenderUtil is instantiated', () => {
        expect(SpatialProductSliderRenderUtilObject instanceof SpatialProductSliderRenderUtil).toBe(true);
    });

    describe('.refreshSliderElements', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialProductSliderRenderUtilObject.refreshSliderElements).toBe('function');
        });

        test('should update tnsSlider if slider plugin found', () => {
            expect(SpatialProductSliderRenderUtilObject.tnsSlider).toBe(null);

            jest.spyOn(SpatialProductSliderRenderUtilObject, 'getSliderPlugin').mockReturnValue({
                _slider: '123'
            });

            SpatialProductSliderRenderUtilObject.refreshSliderElements();

            expect(SpatialProductSliderRenderUtilObject.tnsSlider).toBe('123');
        });
    });

    describe('.getSliderPlugin', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialProductSliderRenderUtilObject.getSliderPlugin).toBe('function');
        });

        test('should return null if slider plugin not found', () => {
            jest.spyOn(window.PluginManager, 'getPluginInstanceFromElement').mockReturnValue(null);
            SpatialProductSliderRenderUtilObject.plugin = {
                el: {
                    closest: jest.fn().mockReturnValue({})
                }
            }

            expect(SpatialProductSliderRenderUtilObject.getSliderPlugin()).toBe(null);
        });

        test('should return the slider plugin if found', () => {
            expect(SpatialProductSliderRenderUtilObject.getSliderPlugin()).toBe(null);

            jest.spyOn(window.PluginManager, 'getPluginInstanceFromElement').mockReturnValue('123');
            SpatialProductSliderRenderUtilObject.plugin = {
                el: {
                    closest: jest.fn().mockReturnValue({})
                }
            }

            expect(SpatialProductSliderRenderUtilObject.getSliderPlugin()).toBe('123');
        });
    });

    describe('.indexChangedEvent', () => {
        beforeEach(() => {
            jest.clearAllMocks();
            jest.useFakeTimers();
            SpatialProductSliderRenderUtilObject.plugin = {
                sliderIndex: 5,
                stopRendering: jest.fn(),
                startRendering: jest.fn()
            };
            SpatialProductSliderRenderUtilObject.tnsSlider = {
                getInfo: jest.fn().mockReturnValue({index: 5})
            };
        });

        afterEach(() => {
            jest.runAllTimers();
            jest.useRealTimers();
        });

        test('should define a function', () => {
            expect(typeof SpatialProductSliderRenderUtilObject.indexChangedEvent).toBe('function');
        });

        test('should call stopRendering if the slider index does not match', () => {
            SpatialProductSliderRenderUtilObject.indexChangedEvent({index: 5});

            expect(SpatialProductSliderRenderUtilObject.plugin.stopRendering).not.toHaveBeenCalled();

            SpatialProductSliderRenderUtilObject.indexChangedEvent({index: 2});

            expect(SpatialProductSliderRenderUtilObject.plugin.stopRendering).toHaveBeenCalled();
        });

        test('should call startRendering if the slider index match', () => {
            SpatialProductSliderRenderUtilObject.indexChangedEvent({index: 2});

            expect(SpatialProductSliderRenderUtilObject.plugin.startRendering).not.toHaveBeenCalled();

            SpatialProductSliderRenderUtilObject.indexChangedEvent({index: 5});
            jest.advanceTimersByTime(1000);

            expect(SpatialProductSliderRenderUtilObject.plugin.startRendering).toHaveBeenCalled();
        });
    });

    describe('.rebuildEvent', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialProductSliderRenderUtilObject.rebuildEvent).toBe('function');
        });

        test('should call init functions', () => {
            const testTarget = {
                target: {
                    querySelector: jest.fn()
                }
            }
            SpatialProductSliderRenderUtilObject.plugin = {
                setReady: jest.fn(),
                initViewer: jest.fn()
            }
            jest.spyOn(SpatialProductSliderRenderUtilObject, 'init');

            expect(SpatialProductSliderRenderUtilObject.init).not.toHaveBeenCalled();
            expect(SpatialProductSliderRenderUtilObject.plugin.initViewer).not.toHaveBeenCalled();

            SpatialProductSliderRenderUtilObject.rebuildEvent(testTarget);

            expect(SpatialProductSliderRenderUtilObject.init).toHaveBeenCalled();
            expect(SpatialProductSliderRenderUtilObject.plugin.initViewer).toHaveBeenCalled();
        });
    });

    describe('.removeDisabled', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialProductSliderRenderUtilObject.removeDisabled).toBe('function');
        });

        test('should call classList remove function with the disabled class as parameter', () => {
            SpatialProductSliderRenderUtilObject.plugin = {
                el:{
                    parentElement: {
                        parentElement: {
                            classList: {
                                remove: jest.fn()
                            }
                        }
                    }
                }
            }

            expect(SpatialProductSliderRenderUtilObject.plugin.el.parentElement.parentElement.classList.remove).not.toHaveBeenCalled();

            SpatialProductSliderRenderUtilObject.removeDisabled();

            expect(SpatialProductSliderRenderUtilObject.plugin.el.parentElement.parentElement.classList.remove)
                .toHaveBeenCalledWith(SpatialProductSliderRenderUtil.options.gallerySliderDisabledClass);
        });
    });

    describe('.initEventListeners', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialProductSliderRenderUtilObject.initEventListeners).toBe('function');
        });

        test('should initialize the indexChanged and rebuild event listeners', () => {
            SpatialProductSliderRenderUtilObject.tnsSlider = {
                events: {
                    on: jest.fn()
                }
            }
            SpatialProductSliderRenderUtilObject.sliderPlugin = {
                $emitter: {
                    subscribe: jest.fn()
                }
            }

            expect(SpatialProductSliderRenderUtilObject.tnsSlider.events.on).not.toHaveBeenCalled();
            expect(SpatialProductSliderRenderUtilObject.sliderPlugin.$emitter.subscribe).not.toHaveBeenCalled();

            SpatialProductSliderRenderUtilObject.initEventListeners();

            expect(SpatialProductSliderRenderUtilObject.tnsSlider.events.on).toHaveBeenCalledWith('indexChanged', expect.anything());
            expect(SpatialProductSliderRenderUtilObject.sliderPlugin.$emitter.subscribe).toHaveBeenCalledWith('rebuild', expect.anything());
        });
    });

    describe('.initRender', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialProductSliderRenderUtilObject.initRender).toBe('function');
        });

        test('should call plugin startRendering function', () => {
            SpatialProductSliderRenderUtilObject.sliderElement = 1;
            SpatialProductSliderRenderUtilObject.tnsSlider = {
                getInfo: jest.fn().mockReturnValue({
                    index: 1,
                    slideItems: [0, 1]
                })
            }
            SpatialProductSliderRenderUtilObject.plugin = {
                startRendering: jest.fn()
            };

            expect(SpatialProductSliderRenderUtilObject.plugin.startRendering).not.toHaveBeenCalled();

            SpatialProductSliderRenderUtilObject.initRender();

            expect(SpatialProductSliderRenderUtilObject.plugin.startRendering).toHaveBeenCalled();
        });
    });

    describe('.init', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialProductSliderRenderUtilObject.init).toBe('function');
        });

        test('should call initEventListeners if tnsSlider and sliderElement are not null', () => {
            SpatialProductSliderRenderUtilObject.tnsSlider = null;
            SpatialProductSliderRenderUtilObject.sliderElement = null;
            jest.spyOn(SpatialProductSliderRenderUtilObject, 'refreshSliderElements').mockImplementation(()=>{});
            jest.spyOn(SpatialProductSliderRenderUtilObject, 'initEventListeners').mockImplementation(()=>{});
            SpatialProductSliderRenderUtilObject.init();

            expect(SpatialProductSliderRenderUtilObject.initEventListeners).not.toHaveBeenCalled();

            SpatialProductSliderRenderUtilObject.tnsSlider = '123';
            SpatialProductSliderRenderUtilObject.sliderElement = '123';

            SpatialProductSliderRenderUtilObject.init();

            expect(SpatialProductSliderRenderUtilObject.initEventListeners).toHaveBeenCalled();
        });
    });
});
