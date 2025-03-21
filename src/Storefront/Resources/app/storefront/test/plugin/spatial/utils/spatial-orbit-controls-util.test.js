import SpatialOrbitControlsUtil from 'src/plugin/spatial/utils/spatial-orbit-controls-util';

const mockCamera = {};
const mockCanvas = {
    addEventListener: jest.fn(),
    removeEventListener: jest.fn()
};

const mockKeyDownEvent = {
    key: 'irrelevant',
    preventDefault: jest.fn(),
    stopPropagation: jest.fn()
};

/**
 * @package innovation
 */
describe('SpatialOrbitControlsUtil', () => {
    let SpatialOrbitControlsUtilObject = undefined;

    beforeEach(() => {
        jest.clearAllMocks();

        window.threeJsAddons = {};
        window.threeJsAddons.OrbitControls = function () {
            return {
                update: jest.fn(),
                dispose: jest.fn(),
                target: {
                    set: jest.fn()
                },
                getAzimuthalAngle: jest.fn(),
                getPolarAngle: jest.fn(),
                getDistance: jest.fn()
            }
        };
        window.threeJsAddons.MathUtils = {
            clamp: jest.fn(),
        };
        SpatialOrbitControlsUtilObject = new SpatialOrbitControlsUtil(mockCamera, mockCanvas);
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    test('SpatialOrbitControlsUtil is instantiated', () => {
        expect(SpatialOrbitControlsUtilObject instanceof SpatialOrbitControlsUtil).toBe(true);
    });

    describe('.bindEventListeners ', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialOrbitControlsUtilObject.bindEventListeners).toBe('function');
        });

        test('should call addEventListener on canvas', () => {
            expect(mockCanvas.addEventListener).not.toHaveBeenCalled();

            SpatialOrbitControlsUtilObject.bindEventListeners(mockCanvas);

            expect(mockCanvas.addEventListener).toHaveBeenCalled();
        });
    });

    describe('.onKeyDown', () => {
        beforeEach(() => {
            jest.clearAllMocks();

            jest.spyOn(SpatialOrbitControlsUtilObject, 'zoom');
            jest.spyOn(SpatialOrbitControlsUtilObject, 'move');
        });

        test('should define a function', () => {
            expect(typeof SpatialOrbitControlsUtilObject.onKeyDown).toBe('function');
        });

        test('should not stop event propagation with irrelevant key', () => {
            expect(SpatialOrbitControlsUtilObject.move).not.toHaveBeenCalled();
            expect(mockKeyDownEvent.preventDefault).not.toHaveBeenCalled();
            expect(mockKeyDownEvent.stopPropagation).not.toHaveBeenCalled();

            SpatialOrbitControlsUtilObject.onKeyDown(mockKeyDownEvent);

            expect(SpatialOrbitControlsUtilObject.move).not.toHaveBeenCalled();
            expect(mockKeyDownEvent.preventDefault).not.toHaveBeenCalled();
            expect(mockKeyDownEvent.stopPropagation).not.toHaveBeenCalled();
        });

        describe('should call controls keydown function', () => {
            test('with ArrowRight', () => {
                mockKeyDownEvent.key = 'ArrowRight';

                expect(SpatialOrbitControlsUtilObject.move).not.toHaveBeenCalled();
                expect(mockKeyDownEvent.preventDefault).not.toHaveBeenCalled();
                expect(mockKeyDownEvent.stopPropagation).not.toHaveBeenCalled();

                SpatialOrbitControlsUtilObject.onKeyDown(mockKeyDownEvent);

                expect(SpatialOrbitControlsUtilObject.move).toHaveBeenCalledWith(expect.any(Number), 0);
                expect(mockKeyDownEvent.preventDefault).toHaveBeenCalled();
                expect(mockKeyDownEvent.stopPropagation).toHaveBeenCalled();
            });

            test('with ArrowLeft', () => {
                mockKeyDownEvent.key = 'ArrowLeft';

                expect(SpatialOrbitControlsUtilObject.move).not.toHaveBeenCalled();
                expect(mockKeyDownEvent.preventDefault).not.toHaveBeenCalled();
                expect(mockKeyDownEvent.stopPropagation).not.toHaveBeenCalled();

                SpatialOrbitControlsUtilObject.onKeyDown(mockKeyDownEvent);

                expect(SpatialOrbitControlsUtilObject.move).toHaveBeenCalledWith(expect.any(Number), 0);
                expect(mockKeyDownEvent.preventDefault).toHaveBeenCalled();
                expect(mockKeyDownEvent.stopPropagation).toHaveBeenCalled();
            });

            test('with ArrowUp', () => {
                mockKeyDownEvent.key = 'ArrowUp';

                expect(SpatialOrbitControlsUtilObject.move).not.toHaveBeenCalled();
                expect(mockKeyDownEvent.preventDefault).not.toHaveBeenCalled();
                expect(mockKeyDownEvent.stopPropagation).not.toHaveBeenCalled();

                SpatialOrbitControlsUtilObject.onKeyDown(mockKeyDownEvent);

                expect(SpatialOrbitControlsUtilObject.move).toHaveBeenCalledWith(0, expect.any(Number));
                expect(mockKeyDownEvent.preventDefault).toHaveBeenCalled();
                expect(mockKeyDownEvent.stopPropagation).toHaveBeenCalled();
            });

            test('with ArrowDown', () => {
                mockKeyDownEvent.key = 'ArrowDown';

                expect(SpatialOrbitControlsUtilObject.move).not.toHaveBeenCalled();
                expect(mockKeyDownEvent.preventDefault).not.toHaveBeenCalled();
                expect(mockKeyDownEvent.stopPropagation).not.toHaveBeenCalled();

                SpatialOrbitControlsUtilObject.onKeyDown(mockKeyDownEvent);

                expect(SpatialOrbitControlsUtilObject.move).toHaveBeenCalledWith(0, expect.any(Number));
                expect(mockKeyDownEvent.preventDefault).toHaveBeenCalled();
                expect(mockKeyDownEvent.stopPropagation).toHaveBeenCalled();
            });

            test('with +', () => {
                mockKeyDownEvent.key = '+';

                expect(SpatialOrbitControlsUtilObject.zoom).not.toHaveBeenCalled();
                expect(mockKeyDownEvent.preventDefault).not.toHaveBeenCalled();
                expect(mockKeyDownEvent.stopPropagation).not.toHaveBeenCalled();

                SpatialOrbitControlsUtilObject.onKeyDown(mockKeyDownEvent);

                expect(SpatialOrbitControlsUtilObject.zoom).toHaveBeenCalledWith(expect.any(Number));
                expect(mockKeyDownEvent.preventDefault).toHaveBeenCalled();
                expect(mockKeyDownEvent.stopPropagation).toHaveBeenCalled();
            });

            test('with -', () => {
                mockKeyDownEvent.key = '-';

                expect(SpatialOrbitControlsUtilObject.zoom).not.toHaveBeenCalled();
                expect(mockKeyDownEvent.preventDefault).not.toHaveBeenCalled();
                expect(mockKeyDownEvent.stopPropagation).not.toHaveBeenCalled();

                SpatialOrbitControlsUtilObject.onKeyDown(mockKeyDownEvent);

                expect(SpatialOrbitControlsUtilObject.zoom).toHaveBeenCalledWith(expect.any(Number));
                expect(mockKeyDownEvent.preventDefault).toHaveBeenCalled();
                expect(mockKeyDownEvent.stopPropagation).toHaveBeenCalled();
            });
        });
    });

    describe('.update', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialOrbitControlsUtilObject.update).toBe('function');
        });

        test('should call control update function', () => {
            expect(SpatialOrbitControlsUtilObject.controls.update).not.toHaveBeenCalled();

            SpatialOrbitControlsUtilObject.update();

            expect(SpatialOrbitControlsUtilObject.controls.update).toHaveBeenCalled();
        });
    });

    describe('.enable', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialOrbitControlsUtilObject.enable).toBe('function');
        });

        test('should set controls enabled to true', () => {
            SpatialOrbitControlsUtilObject.controls.enabled = false;

            SpatialOrbitControlsUtilObject.enable();

            expect(SpatialOrbitControlsUtilObject.controls.enabled).toBe(true);
        });
    });

    describe('.disable', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialOrbitControlsUtilObject.disable).toBe('function');
        });

        test('should set controls disable to false', () => {
            SpatialOrbitControlsUtilObject.controls.enabled = true;

            SpatialOrbitControlsUtilObject.disable();

            expect(SpatialOrbitControlsUtilObject.controls.enabled).toBe(false);
        });
    });

    describe('.enableZoom', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialOrbitControlsUtilObject.enableZoom).toBe('function');
        });

        test('should set zoom enable to true', () => {
            SpatialOrbitControlsUtilObject.controls.enableZoom = false;

            SpatialOrbitControlsUtilObject.enableZoom();

            expect(SpatialOrbitControlsUtilObject.controls.enableZoom).toBe(true);
        });
    });

    describe('.disableZoom', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialOrbitControlsUtilObject.disableZoom).toBe('function');
        });

        test('should set zoom enable to false', () => {
            SpatialOrbitControlsUtilObject.controls.enableZoom = true;

            SpatialOrbitControlsUtilObject.disableZoom();

            expect(SpatialOrbitControlsUtilObject.controls.enableZoom).toBe(false);
        });
    });

    describe('.dispose', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialOrbitControlsUtilObject.dispose).toBe('function');
        });

        test('should call controls dispose function', () => {
            const unbindEventListenersSpy = jest.spyOn(SpatialOrbitControlsUtilObject, 'unbindEventListeners');

            expect(unbindEventListenersSpy).not.toHaveBeenCalled();
            expect(SpatialOrbitControlsUtilObject.controls.dispose).not.toHaveBeenCalled();

            SpatialOrbitControlsUtilObject.dispose();

            expect(unbindEventListenersSpy).toHaveBeenCalled();
            expect(SpatialOrbitControlsUtilObject.controls.dispose).toHaveBeenCalled();
        });
    });

    describe('.reset', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialOrbitControlsUtilObject.reset).toBe('function');
        });

        test('should call controls target set function with 0,0,0', () => {
            expect(SpatialOrbitControlsUtilObject.controls.target.set).not.toHaveBeenCalled();

            SpatialOrbitControlsUtilObject.reset();

            expect(SpatialOrbitControlsUtilObject.controls.target.set).toHaveBeenCalledWith(0,0,0);
        });
    });
});
