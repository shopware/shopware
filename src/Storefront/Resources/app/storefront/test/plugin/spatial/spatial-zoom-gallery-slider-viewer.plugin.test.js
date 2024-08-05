import SpatialZoomGallerySliderViewerPlugin from "src/plugin/spatial/spatial-zoom-gallery-slider-viewer.plugin";
import SpatialObjectLoaderUtil from "src/plugin/spatial/utils/spatial-object-loader-util";
import SpatialOrbitControlsUtil from "src/plugin/spatial/utils/spatial-orbit-controls-util";
import { loadThreeJs } from 'src/plugin/spatial/utils/spatial-threejs-load-util';

jest.mock('src/plugin/spatial/utils/spatial-threejs-load-util');
jest.mock('src/plugin/spatial/utils/spatial-orbit-controls-util');
jest.mock('src/plugin/spatial/utils/composition/spatial-light-composition-util');
jest.mock('src/plugin/spatial/utils/spatial-object-loader-util');
jest.mock('src/plugin/spatial/utils/spatial-zoom-gallery-slider-render-util');

/**
 * @package innovation
 */
describe('SpatialZoomGallerySliderViewerPlugin tests', function () {
    let spatialZoomGallerySliderViewerPlugin;
    let targetElement;
    let parentElement;

    beforeEach(() => {
        SpatialObjectLoaderUtil.mockClear();
        SpatialOrbitControlsUtil.mockClear();
        targetElement = document.createElement('div');
        parentElement = document.createElement('div');
        jest.useFakeTimers();
        window.threeJs = {};
        window.threeJs.PerspectiveCamera = function () {
            return {
                position: {
                    set: () => { }
                },
                lookAt: () => { }
            }
        };
        window.threeJs.Scene = function () {
            return {
                add: jest.fn(),
            }
        };
        window.threeJs.Clock = function () {
            return {
                getDelta: () => { }
            }
        };
        window.threeJs.WebGLRenderer = function () {
            return {
                setClearColor: () => { },
                domElement: document.createElement('canvas'),
                setAnimationLoop: () => { },
                render: () => { }
            }
        };

        spatialZoomGallerySliderViewerPlugin = new SpatialZoomGallerySliderViewerPlugin(targetElement, {
            sliderPosition: 1,
            lightIntensity: "100",
            modelUrl: "http://test/file.glb",
        });

        jest.clearAllMocks();
    });

    afterEach(() => {
        jest.useRealTimers();
        jest.clearAllMocks();
    });

    test('should initialize plugin', () => {
        expect(typeof spatialZoomGallerySliderViewerPlugin).toBe('object');
    });

    test('should not initialize if target element is not given ', () => {
        spatialZoomGallerySliderViewerPlugin.el = undefined;
        expect(spatialZoomGallerySliderViewerPlugin.sliderIndex).toBe(1);
        spatialZoomGallerySliderViewerPlugin.sliderIndex = undefined;

        spatialZoomGallerySliderViewerPlugin.init();

        expect(spatialZoomGallerySliderViewerPlugin.sliderIndex).toBe(undefined);
    });

    test('should create orbit controls', async () => {
        expect(SpatialOrbitControlsUtil).toHaveBeenCalled();
    });

    test.skip('should dispose of orbit controls before creation if orbit controls already exist', async () => {
        jest.spyOn(spatialZoomGallerySliderViewerPlugin.spatialObjectLoaderUtil, 'loadSingleObjectByUrl').mockResolvedValue('123');
        spatialZoomGallerySliderViewerPlugin.SpatialZoomGallerySliderRenderUtil = new SpatialOrbitControlsUtil();
        // 2 instances have been created: one during constructor of plugin, and one inside this test
        expect(SpatialOrbitControlsUtil.mock.instances).toHaveLength(2);
        spatialZoomGallerySliderViewerPlugin.initViewer();

        // Additional orbit control created after dispose() call
        expect(SpatialOrbitControlsUtil.mock.instances).toHaveLength(3);
        const orbitControlsMockInstance = SpatialOrbitControlsUtil.mock.instances[0];

        expect(orbitControlsMockInstance.dispose.mock.calls).toHaveLength(1);
    });

    test('initViewer with defined model will not load model again', () => {
        spatialZoomGallerySliderViewerPlugin.ready = false;
        spatialZoomGallerySliderViewerPlugin.model = {};
        jest.spyOn(SpatialObjectLoaderUtil.prototype, 'loadSingleObjectByUrl').mockReturnValue(Promise.resolve('123'));
        const loadSingleObjectByUrlSpy = jest.spyOn(spatialZoomGallerySliderViewerPlugin.spatialObjectLoaderUtil, 'loadSingleObjectByUrl');
        const initViewerSpy = jest.spyOn(spatialZoomGallerySliderViewerPlugin.SpatialZoomGallerySliderRenderUtil, 'initViewer');
        expect(loadSingleObjectByUrlSpy).toHaveBeenCalledTimes(1);

        spatialZoomGallerySliderViewerPlugin.initViewer();

        expect(spatialZoomGallerySliderViewerPlugin.ready).toBe(true);
        expect(loadSingleObjectByUrlSpy).toHaveBeenCalledTimes(1);
        expect(initViewerSpy).toHaveBeenCalled();
    });

    test('preRender will call in the right order', () => {
        const spatialCanvasSizeUpdateUtilUpdateSpy = jest.spyOn(spatialZoomGallerySliderViewerPlugin.spatialCanvasSizeUpdateUtil, 'update');
        const spatialOrbitControlsUtilUpdateSpy = jest.spyOn(spatialZoomGallerySliderViewerPlugin.spatialOrbitControlsUtil, 'update');
        spatialZoomGallerySliderViewerPlugin.rendering = true;

        spatialZoomGallerySliderViewerPlugin.render();

        expect(spatialCanvasSizeUpdateUtilUpdateSpy).toHaveBeenCalled();
        expect(spatialOrbitControlsUtilUpdateSpy).toHaveBeenCalled();
    });

    test('initViewer with model and light intensity', async () => {
        jest.spyOn(SpatialObjectLoaderUtil.prototype, 'loadSingleObjectByUrl').mockReturnValue(Promise.resolve('123'));

        spatialZoomGallerySliderViewerPlugin.initViewer(false);

        await new Promise(process.nextTick);

        expect(spatialZoomGallerySliderViewerPlugin.scene.add).toHaveBeenCalledTimes(1);
    });

    test('initViewer with defined spatial model url will load model', async () => {
        jest.spyOn(SpatialObjectLoaderUtil.prototype, 'loadSingleObjectByUrl').mockReturnValue(Promise.resolve('123'));

        spatialZoomGallerySliderViewerPlugin.initViewer(false);

        await new Promise(process.nextTick);

        expect(spatialZoomGallerySliderViewerPlugin.scene.add).toHaveBeenCalledTimes(1);
    });

    test('initViewer without spatial model url will not load model', async () => {
        spatialZoomGallerySliderViewerPlugin["model"] = null;
        spatialZoomGallerySliderViewerPlugin["options"]["modelUrl"] = null;
        const loadObjectMock = jest.spyOn(SpatialObjectLoaderUtil.prototype, 'loadSingleObjectByUrl').mockReturnValue(Promise.resolve('123'));

        // reset all prior calls to the mock function
        jest.clearAllMocks();
        expect(loadObjectMock).toHaveBeenCalledTimes(0);

        spatialZoomGallerySliderViewerPlugin.initViewer(false);

        // one time at plugin initialization, and NOT another time during initViewer() call (in this test)
        expect(loadObjectMock).toHaveBeenCalledTimes(0);
    });

    test('initViewer with incorrect uploaded model from url will disable slider canvas', async () => {
        const parentDiv = document.createElement('span');
        const middleDiv = document.createElement('div');
        jest.spyOn(SpatialObjectLoaderUtil.prototype, 'loadSingleObjectByUrl').mockReturnValue(Promise.reject('123'));
        middleDiv.appendChild(spatialZoomGallerySliderViewerPlugin.canvas);
        parentDiv.appendChild(middleDiv);

        spatialZoomGallerySliderViewerPlugin.initViewer(true);

        await new Promise(process.nextTick);

        expect(spatialZoomGallerySliderViewerPlugin.el.parentElement.parentElement.classList.contains('gallery-slider-canvas-disabled')).toBe(true);
    });
});
