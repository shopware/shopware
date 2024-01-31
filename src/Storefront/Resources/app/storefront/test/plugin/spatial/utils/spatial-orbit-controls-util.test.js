import SpatialOrbitControlsUtil from 'src/plugin/spatial/utils/spatial-orbit-controls-util';

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
                }
            }
        };
        SpatialOrbitControlsUtilObject = new SpatialOrbitControlsUtil();
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    test('SpatialOrbitControlsUtil is instantiated', () => {
        expect(SpatialOrbitControlsUtilObject instanceof SpatialOrbitControlsUtil).toBe(true);
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
            expect(SpatialOrbitControlsUtilObject.controls.dispose).not.toHaveBeenCalled();

            SpatialOrbitControlsUtilObject.dispose();

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
