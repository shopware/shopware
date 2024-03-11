import SpatialObjectLoaderUtil from 'src/plugin/spatial/utils/spatial-object-loader-util';
import NativeEventEmitter from 'src/helper/emitter.helper';
jest.mock('src/helper/emitter.helper');

/**
 * @package innovation
 */
describe("SpatialObjectLoaderUtil", () => {
    let SpatialObjectLoaderUtilObject = undefined;

    beforeEach(() => {
        jest.clearAllMocks();

        window.threeJs = {};
        window.threeJs.Object3d = function () {
            return {}
        };
        window.threeJs.Box3 = function () {
            return {
                setFromObject: function (){
                    return {
                        getSize: jest.fn()
                    }
                },
            }
        }
        window.threeJs.Vector3 = function () {
            return {}
        }
        window.threeJs.Group = function () {
            return {
                name: '',
                add: jest.fn(),
            }
        };
        window.threeJsAddons = {};
        window.threeJsAddons.GLTFLoader = function () {
            return {
                load: jest.fn(),
                setDRACOLoader: jest.fn()
            }
        };
        window.threeJsAddons.DRACOLoader = function () {
            return {
                setDecoderPath: jest.fn()
            }
        };
        window.threeJsAddons.DRACOLibPath = `three/examples/jsm/libs/draco/`;
        SpatialObjectLoaderUtilObject = new SpatialObjectLoaderUtil();
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    test('SpatialObjectLoaderUtil is instantiated', () => {
        expect(SpatialObjectLoaderUtilObject instanceof SpatialObjectLoaderUtil).toBe(true);
    });

    describe('.detailLoaded', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialObjectLoaderUtilObject.detailLoaded).toBe('function');
        });

        test('should return load status', () => {
            SpatialObjectLoaderUtilObject.loadStatus = '';
            expect(SpatialObjectLoaderUtilObject.detailLoaded()).toBe('');

            SpatialObjectLoaderUtilObject.loadStatus = '123';
            expect(SpatialObjectLoaderUtilObject.detailLoaded()).toBe('123');
        });
    });

    describe('.emitLoadingUpdate', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialObjectLoaderUtilObject.emitLoadingUpdate).toBe('function');
        });

        test('should trigger the loading update callback', () => {
            SpatialObjectLoaderUtilObject.$emitter = {
                publish: jest.fn()
            };

            expect(SpatialObjectLoaderUtilObject.$emitter.publish).not.toHaveBeenCalled();

            SpatialObjectLoaderUtilObject.emitLoadingUpdate();

            expect(SpatialObjectLoaderUtilObject.$emitter.publish).toHaveBeenCalledWith('ObjectLoaderUtil/loadingUpdate', expect.anything());
        });
    });

    describe('.percentageLoaded', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialObjectLoaderUtilObject.percentageLoaded).toBe('function');
        });

        test('should return the loading percentage', () => {
            const loadStatusTest = new Map();
            SpatialObjectLoaderUtilObject.loadStatus = loadStatusTest.set('testObject', 1);

            expect(SpatialObjectLoaderUtilObject.percentageLoaded()).toBe(1);

            SpatialObjectLoaderUtilObject.loadStatus = loadStatusTest.set('testObject', 50);

            expect(SpatialObjectLoaderUtilObject.percentageLoaded()).toBe(50);
       });
    });

    describe('.clampSize', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialObjectLoaderUtilObject.clampSize).toBe('function');
        });

        test('should scale the object half size if the max side size is 2', () => {
            window.threeJs.Box3 = function () {
                return {
                    setFromObject: function (){
                        return {
                            getSize: jest.fn().mockReturnValue({x:2,y:2,z:2})
                        }
                    },
                }
            }
            const object3dMock = {
                scale : {
                    multiplyScalar: jest.fn()
                }
            }

            expect(object3dMock.scale.multiplyScalar).not.toHaveBeenCalled();

            SpatialObjectLoaderUtilObject.clampSize(object3dMock);

            expect(object3dMock.scale.multiplyScalar).toHaveBeenCalledWith(0.5);
        });
    });

    describe('.centerObject', () => {
        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('should define a function', () => {
            expect(typeof SpatialObjectLoaderUtilObject.centerObject).toBe('function');
        });

        test('should center the object position', () => {
            window.threeJs.Box3 = function () {
                return {
                    setFromObject: function (){
                        return {
                            getCenter: jest.fn().mockReturnValue({x:5,y:5,z:5})
                        }
                    },
                }
            }
            const object3dMock = {
                position : {
                    copy: jest.fn(),
                    multiplyScalar: jest.fn()
                }
            }

            expect(object3dMock.position.copy).not.toHaveBeenCalled();
            expect(object3dMock.position.multiplyScalar).not.toHaveBeenCalled();

            SpatialObjectLoaderUtilObject.centerObject(object3dMock);

            expect(object3dMock.position.copy).toHaveBeenCalledWith({x:5,y:5,z:5});
            expect(object3dMock.position.multiplyScalar).toHaveBeenCalledWith(-1);
        });
    });

    describe('.loadSingleObjectByUrl', () => {
        let options = undefined;
        let loadSpy = undefined;
        let url = undefined;

        beforeEach(() => {
            jest.clearAllMocks();
            loadSpy = jest.spyOn(SpatialObjectLoaderUtilObject.gltfLoader, 'load').mockImplementation(
                (url, onLoad) => {
                    onLoad({scene: '123'});
                }
            );
            url = 'https://test3d.com/object.glb';
            options = {
                center: false,
                clampSize: false,
            };
        });

        test('should define a function', () => {
            expect(typeof SpatialObjectLoaderUtilObject.loadSingleObjectByUrl).toBe('function');
        });

        test('should set loading status and call emitLoadingUpdate', async () => {
            const emitLoadingUpdateSpy = jest.spyOn(SpatialObjectLoaderUtilObject, 'emitLoadingUpdate');

            expect(SpatialObjectLoaderUtilObject.loadStatus.has('https://test3d.com/object.glb')).toBe(false);
            expect(emitLoadingUpdateSpy).not.toHaveBeenCalled();

            await SpatialObjectLoaderUtilObject.loadSingleObjectByUrl(url, options);

            expect(emitLoadingUpdateSpy).toHaveBeenCalled();
            expect(SpatialObjectLoaderUtilObject.loadStatus.has('https://test3d.com/object.glb')).toBe(true);
        });

        test('should call clampSize if clampSize option is true', async () => {
            const clampSizeSpy = jest.spyOn(SpatialObjectLoaderUtilObject, 'clampSize').mockReturnValue({x:1,y:1,z:1});
            await SpatialObjectLoaderUtilObject.loadSingleObjectByUrl(url, options);

            expect(clampSizeSpy).not.toHaveBeenCalled();

            options = {
                center: false,
                clampSize: true,
            };

            await SpatialObjectLoaderUtilObject.loadSingleObjectByUrl(url, options);
            expect(clampSizeSpy).toHaveBeenCalled();
        });

        test('should call centerObject if center option is true', async () => {
            const centerObjectSpy = jest.spyOn(SpatialObjectLoaderUtilObject, 'centerObject').mockReturnValue({x:1,y:1,z:1});
            await SpatialObjectLoaderUtilObject.loadSingleObjectByUrl(url, options);

            expect(centerObjectSpy).not.toHaveBeenCalled();

            options = {
                center: true,
                clampSize: false,
            };

            await SpatialObjectLoaderUtilObject.loadSingleObjectByUrl(url, options);
            expect(centerObjectSpy).toHaveBeenCalled();
        });
    });
});
