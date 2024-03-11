import HitTest from 'src/plugin/spatial/utils/ar/webXr/HitTest';

/**
 * @package innovation
 */
describe('HitTest', () => {
    let HitTestObject = undefined;
    let renderer = undefined;
    let scene = undefined;

    beforeEach(() => {
        jest.clearAllMocks();
        renderer = {
            xr: {
                getReferenceSpace: jest.fn(),
                getSession: function () {
                    return {
                        requestReferenceSpace: jest.fn(),
                        requestHitTestSource: jest.fn().mockReturnValue(123)
                    }
                }
            }
        };
        scene = {
            add: jest.fn(),
            remove: jest.fn()
        };
        global.XRFrame = jest.fn();
        window.threeJs = {};
        window.threeJs.RingGeometry = function () {
            return {
                rotateX: (radians) => {
                    return {}
                }
            }
        };
        window.threeJs.MeshBasicMaterial = function () {
            return {}
        };
        window.threeJs.Mesh = function (ring, material) {
            return {}
        };
        window.threeJs.Matrix4 = function() {
            return {
                fromArray: jest.fn()
            }
        };
        HitTestObject = new HitTest(renderer, scene);
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    test('HitTest is instantiated', () => {
        expect(HitTestObject instanceof HitTest).toBe(true);
    });

    describe('.update', () => {
        let getHitTestResultsSpy = undefined;
        let frame = undefined;

        beforeEach(() => {
            jest.clearAllMocks();

            frame = new XRFrame();
            frame.getHitTestResults = () => {};
            getHitTestResultsSpy = jest.spyOn(frame, 'getHitTestResults');

            HitTestObject.marker.matrix = {
                fromArray : jest.fn()
            };
        });

        test('returns false if no XRFrame is passed as parameter', () => {
            expect(HitTestObject.update({})).toBe(false);
        });

        test('should return true if there are hitTestResults', async () => {
            expect(HitTestObject.update(frame)).toBe(false); // false on first call due to not having hitTestSource set
            await new Promise(process.nextTick);
            getHitTestResultsSpy.mockImplementation(() => {return []});
            expect(HitTestObject.update(frame)).toBe(false); // false due to not getting hitTestResults
            await new Promise(process.nextTick);

            getHitTestResultsSpy.mockImplementation(() => {return [{
                getPose: () => {
                    return {
                        transform: {
                            matrix: '123'
                        }
                    }
                }
            }]
            });
            await new Promise(process.nextTick);

            expect(HitTestObject.update(frame)).toBe(true); // true due to getting hitTestResults
        });
    });

    describe('.getHitPose', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof HitTestObject.getHitPose).toBe('function');
        });

        test('should return the output of Matrix4 fromArray function', () => {
            expect(HitTestObject.getHitPose()).toBe(undefined); // undefined cause original empty mock return undefined

            window.threeJs.Matrix4 = function() {
                return {
                    fromArray: jest.fn().mockReturnValue(123)
                }
            }

            expect(HitTestObject.getHitPose()).toBe(123); // 123 cause overwritten mock return 123
        });
    });

    describe('.hideMarker', () => {
        test('should define a function', () => {
            expect(typeof HitTestObject.hideMarker).toBe('function');
        });

        test('should set markerVisible to false', () => {
            HitTestObject.markerVisible = true;
            HitTestObject.hideMarker();
            expect(HitTestObject.markerVisible).toBe(false);
        });
    });

    describe('.showMarker', () => {
        test('should define a function', () => {
            expect(typeof HitTestObject.showMarker).toBe('function');
        });

        test('should set markerVisible to true', () => {
            HitTestObject.markerVisible = false;
            HitTestObject.showMarker();
            expect(HitTestObject.markerVisible).toBe(true);
        });
    });

    describe('.dispose', () => {
        test('should define a function', () => {
            expect(typeof HitTestObject.dispose).toBe('function');
        });

        test('should set hitTestSourceRequested to false', () => {
            HitTestObject.hitTestSourceRequested = true;
            HitTestObject.dispose();
            expect(HitTestObject.hitTestSourceRequested).toBe(false);
        });

        test('should set hitTestSource to null', () => {
            HitTestObject.hitTestSource = [];
            HitTestObject.dispose();
            expect(HitTestObject.hitTestSource).toBe(null);
        });

        test('should set marker visible to false', () => {
            HitTestObject.marker.visible = true;
            HitTestObject.dispose();
            expect(HitTestObject.marker.visible).toBe(false);
        });
    });
});
