import SpatialLightCompositionUtil from 'src/plugin/spatial/utils/composition/spatial-light-composition-util';

/**
 * @package innovation
 */
describe('SpatialLightCompositionUtil', () => {
    let SpatialLightCompositionUtilObject = undefined;
    let scene = undefined;

    beforeEach(() => {
        jest.clearAllMocks();
        scene = {
            add: jest.fn(),
            remove: jest.fn()
        };
        window.threeJs = {};
        window.threeJs.Group = function () {
            return {
                add: jest.fn(),
                remove: jest.fn(),
                getObjectByName: jest.fn()
            }
        };
        window.threeJs.AmbientLight = function () {
            return {
                position: {
                    set: jest.fn()
                }
            }
        };
        window.threeJs.DirectionalLight = function () {
            return {
                position: {
                    set: jest.fn()
                },
                target: {
                    position: {
                        set: jest.fn()
                    }
                }
            }
        };
        SpatialLightCompositionUtilObject = new SpatialLightCompositionUtil(scene);
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    test('SpatialLightCompositionUtil is instantiated', () => {
        expect(SpatialLightCompositionUtilObject instanceof SpatialLightCompositionUtil).toBe(true);
    });

    test('SpatialLightCompositionUtil is instantiated with custom intensity', () => {
        const SpatialLightCompositionUtilObject = new SpatialLightCompositionUtil(scene, '69');
        expect(SpatialLightCompositionUtilObject.lights[0].intensity).toBe(0.69);
    });

    describe('.dispose', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialLightCompositionUtilObject.dispose).toBe('function');
        });

        test('should reset lights', () => {
            expect(SpatialLightCompositionUtilObject.scene.remove).not.toHaveBeenCalled();
            SpatialLightCompositionUtilObject.lights = '123';

            SpatialLightCompositionUtilObject.dispose();

            expect(SpatialLightCompositionUtilObject.scene.remove).toHaveBeenCalledWith(SpatialLightCompositionUtilObject.lightGroup);
            expect(SpatialLightCompositionUtilObject.lights.length).toBe(0);
        });
    });

    describe('.removeLightById', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialLightCompositionUtilObject.removeLightById).toBe('function');
        });

        test('should remove the light group found by getObjectByName', () => {
            const getObjectByNameSpy = jest.spyOn(SpatialLightCompositionUtilObject.lightGroup, 'getObjectByName');
            getObjectByNameSpy.mockReturnValue('123');

            expect(SpatialLightCompositionUtilObject.lightGroup.remove).not.toHaveBeenCalled();

            SpatialLightCompositionUtilObject.removeLightById('test');

            expect(SpatialLightCompositionUtilObject.lightGroup.remove).toHaveBeenCalledWith('123');
        });
    });

    describe('.removeLight', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialLightCompositionUtilObject.removeLight).toBe('function');
        });

        test('should call removeLightById with the given id', () => {
            const removeLightByIdSpy = jest.spyOn(SpatialLightCompositionUtilObject, 'removeLightById');
            const testLight = { id: '123' }

            expect(removeLightByIdSpy).not.toHaveBeenCalled();

            SpatialLightCompositionUtilObject.removeLight(testLight);

            expect(removeLightByIdSpy).toHaveBeenCalledWith('123');
        });
    });
});
