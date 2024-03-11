import SpatialZoomGallerySliderRenderUtil from 'src/plugin/spatial/utils/spatial-zoom-gallery-slider-render-util';

/**
 * @package innovation
 */
describe('SpatialZoomGallerySliderRenderUtil', () => {
    let SpatialZoomGallerySliderRenderUtilObject = undefined;
    let pluginMock = undefined;

    beforeEach(() => {
        jest.clearAllMocks();
        pluginMock = {
            el: {
                closest: jest.fn().mockReturnValue({
                    querySelector: jest.fn().mockReturnValue({})
                })
            }
        };
        jest.spyOn(window.PluginManager, 'getPluginInstanceFromElement').mockReturnValue({
            $emitter: {
                subscribe: jest.fn()
            }
        });
        SpatialZoomGallerySliderRenderUtilObject = new SpatialZoomGallerySliderRenderUtil(pluginMock);
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    test('SpatialZoomGallerySliderRenderUtil is instantiated', () => {
        expect(SpatialZoomGallerySliderRenderUtilObject instanceof SpatialZoomGallerySliderRenderUtil).toBe(true);
    });

    describe('.removeDisabled', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialZoomGallerySliderRenderUtilObject.removeDisabled).toBe('function');
        });

        test('should call classList remove function with the disabled class as parameter', () => {
            SpatialZoomGallerySliderRenderUtilObject.plugin = {
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

            expect(SpatialZoomGallerySliderRenderUtilObject.plugin.el.parentElement.parentElement.classList.remove).not.toHaveBeenCalled();

            SpatialZoomGallerySliderRenderUtilObject.removeDisabled();

            expect(SpatialZoomGallerySliderRenderUtilObject.plugin.el.parentElement.parentElement.classList.remove)
                .toHaveBeenCalledWith(SpatialZoomGallerySliderRenderUtil.options.zoomSliderDisabledClass);
        });
    });

    describe('.changeZoomActionsVisibility', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialZoomGallerySliderRenderUtilObject.changeZoomActionsVisibility).toBe('function');
        });

        test('should remove d-none class if its called with true', () => {
            document.body.innerHTML = `
                <div class="zoom-modal-actions d-none"></div>
            `

            const testEl = document.querySelector(SpatialZoomGallerySliderRenderUtil.options.zoomModalActionsSelector);
            expect(testEl.classList.contains('d-none')).toBe(true);

            SpatialZoomGallerySliderRenderUtilObject.changeZoomActionsVisibility(true);

            expect(testEl.classList.contains('d-none')).toBe(false);
        });

        test('should add d-none class if its called with false', () => {
            document.body.innerHTML = `
                <div class="zoom-modal-actions"></div>
            `

            const testEl = document.querySelector(SpatialZoomGallerySliderRenderUtil.options.zoomModalActionsSelector);
            expect(testEl.classList.contains('d-none')).toBe(false);

            SpatialZoomGallerySliderRenderUtilObject.changeZoomActionsVisibility(false);

            expect(testEl.classList.contains('d-none')).toBe(true);
        });
    });

    describe('.indexChangedEvent', () => {
        beforeEach(() => {
            jest.clearAllMocks();
            jest.useFakeTimers();
            SpatialZoomGallerySliderRenderUtilObject.plugin = {
                sliderIndex: 5,
                stopRendering: jest.fn(),
                startRendering: jest.fn()
            };
            SpatialZoomGallerySliderRenderUtilObject.tnsSlider = {
                getInfo: jest.fn().mockReturnValue({index: 5})
            };
        });

        afterEach(() => {
            jest.runAllTimers();
            jest.useRealTimers();
        });

        test('should define a function', () => {
            expect(typeof SpatialZoomGallerySliderRenderUtilObject.indexChangedEvent).toBe('function');
        });

        test('should call stopRendering if the slider index does not match', () => {
            SpatialZoomGallerySliderRenderUtilObject.indexChangedEvent({index: 5});

            expect(SpatialZoomGallerySliderRenderUtilObject.plugin.stopRendering).not.toHaveBeenCalled();

            SpatialZoomGallerySliderRenderUtilObject.indexChangedEvent({index: 2});

            expect(SpatialZoomGallerySliderRenderUtilObject.plugin.stopRendering).toHaveBeenCalled();
        });

        test('should call startRendering if the slider index match', () => {
            SpatialZoomGallerySliderRenderUtilObject.indexChangedEvent({index: 2});

            expect(SpatialZoomGallerySliderRenderUtilObject.plugin.startRendering).not.toHaveBeenCalled();

            SpatialZoomGallerySliderRenderUtilObject.indexChangedEvent({index: 5});
            jest.advanceTimersByTime(1000);

            expect(SpatialZoomGallerySliderRenderUtilObject.plugin.startRendering).toHaveBeenCalled();
        });
    });

    describe('.initEventListeners', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialZoomGallerySliderRenderUtilObject.initEventListeners).toBe('function');
        });

        test('should initialize the indexChanged and rebuild event listeners', () => {
            SpatialZoomGallerySliderRenderUtilObject.tnsSlider = {
                events: {
                    on: jest.fn()
                }
            }
            SpatialZoomGallerySliderRenderUtilObject.sliderPlugin = {
                $emitter: {
                    subscribe: jest.fn()
                }
            }

            expect(SpatialZoomGallerySliderRenderUtilObject.tnsSlider.events.on).not.toHaveBeenCalled();
            expect(SpatialZoomGallerySliderRenderUtilObject.sliderPlugin.$emitter.subscribe).not.toHaveBeenCalled();

            SpatialZoomGallerySliderRenderUtilObject.initEventListeners();

            expect(SpatialZoomGallerySliderRenderUtilObject.tnsSlider.events.on).toHaveBeenCalledWith('indexChanged', expect.anything());
            expect(SpatialZoomGallerySliderRenderUtilObject.sliderPlugin.$emitter.subscribe).toHaveBeenCalledWith('rebuild', expect.anything());
        });
    });

    describe('.rebuildEvent', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialZoomGallerySliderRenderUtilObject.rebuildEvent).toBe('function');
        });

        test('should call init functions', () => {
            const testTarget = {
                target: {
                    querySelector: jest.fn()
                }
            }
            SpatialZoomGallerySliderRenderUtilObject.plugin = {
                setReady: jest.fn(),
                initViewer: jest.fn()
            }
            jest.spyOn(SpatialZoomGallerySliderRenderUtilObject, 'initViewer').mockImplementation(()=>{});

            expect(SpatialZoomGallerySliderRenderUtilObject.initViewer).not.toHaveBeenCalled();
            expect(SpatialZoomGallerySliderRenderUtilObject.plugin.initViewer).not.toHaveBeenCalled();

            SpatialZoomGallerySliderRenderUtilObject.rebuildEvent(testTarget);

            expect(SpatialZoomGallerySliderRenderUtilObject.initViewer).toHaveBeenCalled();
            expect(SpatialZoomGallerySliderRenderUtilObject.plugin.initViewer).toHaveBeenCalled();
        });
    });

    describe('.initViewer', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialZoomGallerySliderRenderUtilObject.initViewer).toBe('function');
        });

        test('should call plugin startRendering function if sliderIndex is current page', () => {
            jest.spyOn(SpatialZoomGallerySliderRenderUtilObject, 'initEventListeners').mockImplementation(()=>{});
            SpatialZoomGallerySliderRenderUtilObject.zoomModalPlugin = {
                gallerySliderPlugin: {}
            };
            SpatialZoomGallerySliderRenderUtilObject.plugin = {
                sliderIndex: 2,
                startRendering: jest.fn()
            };

            SpatialZoomGallerySliderRenderUtilObject.initViewer();
            expect(SpatialZoomGallerySliderRenderUtilObject.plugin.startRendering).not.toHaveBeenCalled();

            SpatialZoomGallerySliderRenderUtilObject.plugin = {
                sliderIndex: 0,
                startRendering: jest.fn()
            };

            SpatialZoomGallerySliderRenderUtilObject.initViewer();
            expect(SpatialZoomGallerySliderRenderUtilObject.plugin.startRendering).toHaveBeenCalled();
        });
    });
});
