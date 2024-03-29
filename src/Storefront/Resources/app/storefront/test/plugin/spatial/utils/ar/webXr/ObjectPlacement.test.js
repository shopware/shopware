import ObjectPlacement from 'src/plugin/spatial/utils/ar/webXr/ObjectPlacement';
import HitTest from 'src/plugin/spatial/utils/ar/webXr/HitTest';
jest.mock('src/plugin/spatial/utils/ar/webXr/HitTest');

/**
 * @package innovation
 */
describe('ObjectPlacement', () => {
    let ObjectPlacementObject = undefined;
    let renderer = undefined;
    let scene = undefined;
    let model = undefined;

    beforeEach(() => {
        jest.clearAllMocks();
        HitTest.mockClear();
        renderer = {};
        scene = {
            add: function () {},
            remove: function () {}
        };
        model = {
            position: {
                setFromMatrixPosition: jest.fn()
            }
        };
        global.XRFrame = jest.fn();
        window.threeJs = {};
        window.threeJs.Raycaster = function () {
            return {}
        };
        ObjectPlacementObject = new ObjectPlacement(renderer, scene, model);
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    test('ObjectPlacement is instantiated', () => {
        expect(ObjectPlacementObject instanceof ObjectPlacement).toBe(true);
    });

    describe('.update', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof ObjectPlacementObject.update).toBe('function');
        });

        test('should return the output of webXrHitTest update function', () => {
            jest.spyOn(ObjectPlacementObject.webXrHitTest, 'update').mockReturnValue(true);

            expect(ObjectPlacementObject.update(new XRFrame())).toBe(true);

            jest.spyOn(ObjectPlacementObject.webXrHitTest, 'update').mockReturnValue(false);

            expect(ObjectPlacementObject.update(new XRFrame())).toBe(false);
        });
    });

    describe('.placeObject', () => {
        let getHitPoseSpy = undefined;

        beforeEach(() => {
            jest.clearAllMocks();
            getHitPoseSpy = jest.spyOn(ObjectPlacementObject.webXrHitTest, 'getHitPose').mockReturnValue('123');
        });

        test('should define a function', () => {
            expect(typeof ObjectPlacementObject.placeObject).toBe('function');
        });

        test('should set model visible to true', () => {
            ObjectPlacementObject.model.visible = false;
            ObjectPlacementObject.placeObject();
            expect(ObjectPlacementObject.model.visible).toBe(true);
        });

        test('should set placed to true', () => {
            ObjectPlacementObject.placed = false;
            ObjectPlacementObject.placeObject();
            expect(ObjectPlacementObject.placed).toBe(true);
        });

        test('should call hideMarker', () => {
            const hideMarkerSpy = jest.spyOn(ObjectPlacementObject.webXrHitTest, 'hideMarker');
            ObjectPlacementObject.placeObject();
            expect(hideMarkerSpy).toHaveBeenCalled();
        });

        test('should not change model visible and placed if getHitPose() returns null', () => {
            getHitPoseSpy.mockReturnValue(null);
            ObjectPlacementObject.model.visible = false;
            ObjectPlacementObject.placed = false;

            ObjectPlacementObject.placeObject();

            expect(ObjectPlacementObject.placed).toBe(false);
            expect(ObjectPlacementObject.model.visible).toBe(false);
        });
    });

    describe('.resetPlacement', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof ObjectPlacementObject.resetPlacement).toBe('function');
        });

        test('should set model visible to false', () => {
            ObjectPlacementObject.model.visible = true;
            ObjectPlacementObject.resetPlacement();
            expect(ObjectPlacementObject.model.visible).toBe(false);
        });

        test('should call showMarker', () => {
            const showMarkerSpy = jest.spyOn(ObjectPlacementObject.webXrHitTest, 'showMarker');
            ObjectPlacementObject.resetPlacement();
            expect(showMarkerSpy).toHaveBeenCalled();
        });
    })

    describe('.dispose', () => {
        let disposeSpy = undefined;

        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof ObjectPlacementObject.dispose).toBe('function');
        });

        test('should call webXrHitTest dispose function', () => {
            const disposeSpy = jest.spyOn(ObjectPlacementObject.webXrHitTest, 'dispose');
            expect(disposeSpy).toHaveBeenCalledTimes(0);
            ObjectPlacementObject.dispose();
            expect(disposeSpy).toHaveBeenCalledTimes(1);
        });
    });
});
