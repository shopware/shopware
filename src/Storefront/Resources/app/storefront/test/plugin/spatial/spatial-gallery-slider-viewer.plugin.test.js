import SpatialGallerySliderViewerPlugin from 'src/plugin/spatial/spatial-gallery-slider-viewer.plugin';
import SpatialOrbitControlsUtil from "src/plugin/spatial/utils/spatial-orbit-controls-util";
import SpatialMovementNoteUtil from "src/plugin/spatial/utils/spatial-movement-note-util";
import SpatialCanvasSizeUpdateUtil from "src/plugin/spatial/utils/spatial-canvas-size-update-util";
import SpatialLightCompositionUtil from "src/plugin/spatial/utils/composition/spatial-light-composition-util";
import SpatialObjectLoaderUtil from "src/plugin/spatial/utils/spatial-object-loader-util";
import { loadThreeJs } from 'src/plugin/spatial/utils/spatial-threejs-load-util';

jest.mock('src/plugin/spatial/utils/spatial-threejs-load-util');
jest.mock('src/plugin/spatial/utils/spatial-orbit-controls-util');
jest.mock('src/plugin/spatial/utils/spatial-movement-note-util');
jest.mock('src/plugin/spatial/utils/spatial-canvas-size-update-util');
jest.mock('src/plugin/spatial/utils/composition/spatial-light-composition-util');
jest.mock('src/plugin/spatial/utils/spatial-object-loader-util');

/**
 * @package innovation
 */
describe('SpatialGallerySliderViewerPlugin tests', () => {
    let spatialGallerySliderViewerPlugin;
    let mockElement;

    beforeEach(() => {
        mockElement = document.createElement('div');
        window.threeJs = {};
        window.threeJs.PerspectiveCamera = function () {
            return {
                position: {
                    set: jest.fn()
                },
                lookAt: jest.fn()
            }
        };
        window.threeJs.Scene = function () {
            return {
                add: jest.fn(),
                remove: function () { }
            }
        };

        window.threeJs.Clock = function () {
            return {
                start: jest.fn(),
                getDelta: jest.fn()
            }
        };
        window.threeJs.WebGLRenderer = function () {
            return {
                setPixelRatio: jest.fn(),
                setSize: jest.fn,
                setClearColor: jest.fn(),
                xr: {
                    enabled: null,
                    getController: () => {
                        return {
                            addEventListener: jest.fn()
                        }
                    },
                    setReferenceSpaceType: jest.fn(),
                    getReferenceSpace: jest.fn(() => {
                        return {
                            addEventListener: jest.fn()
                        }
                    }),
                    setSession: jest.fn()
                },
                domElement: document.createElement('canvas'),
                setAnimationLoop: jest.fn(),
                render: jest.fn()
            }
        };

        spatialGallerySliderViewerPlugin = new SpatialGallerySliderViewerPlugin(mockElement, {
            sliderPosition: "1",
            lightIntensity: "100",
            modelUrl: "http://test/file.glb",
        });

        jest.clearAllMocks();
    });

    test('plugin initializes', () => {
        expect(typeof spatialGallerySliderViewerPlugin).toBe('object');
        expect(spatialGallerySliderViewerPlugin.sliderIndex).toBe(1);
    });

    test('init with undefined element will do nothing', () => {
        spatialGallerySliderViewerPlugin.el = undefined;
        spatialGallerySliderViewerPlugin.sliderIndex = undefined;

        spatialGallerySliderViewerPlugin.init();

        expect(spatialGallerySliderViewerPlugin.sliderIndex).toBe(undefined);
    });

    test('initViewer with undefined light will set it from scene', () => {
        spatialGallerySliderViewerPlugin.spatialLightCompositionUtil = undefined;
        jest.spyOn(SpatialObjectLoaderUtil.prototype, 'loadSingleObjectByUrl').mockReturnValue(Promise.resolve('123'));

        spatialGallerySliderViewerPlugin.initViewer(true);

        expect(typeof spatialGallerySliderViewerPlugin.spatialLightCompositionUtil).toBe('object');

    });

    test('initViewer with defined model will not load model again', () => {
        spatialGallerySliderViewerPlugin.ready = false;
        spatialGallerySliderViewerPlugin.model = {};
        jest.spyOn(SpatialObjectLoaderUtil.prototype, 'loadSingleObjectByUrl').mockReturnValue(Promise.resolve('123'));
        const loadSingleObjectByUrlSpy = jest.spyOn(spatialGallerySliderViewerPlugin.spatialObjectLoaderUtil, 'loadSingleObjectByUrl');
        const initRenderSpy = jest.spyOn(spatialGallerySliderViewerPlugin.spatialProductSliderRenderUtil, 'initRender');
        expect(loadSingleObjectByUrlSpy).toHaveBeenCalledTimes(1);

        spatialGallerySliderViewerPlugin.initViewer(false);

        expect(spatialGallerySliderViewerPlugin.ready).toBe(true);
        expect(loadSingleObjectByUrlSpy).toHaveBeenCalledTimes(1);
        expect(initRenderSpy).toHaveBeenCalledTimes(1);
    });

    test('preRender will call in the right order', () => {
        const spatialCanvasSizeUpdateUtilUpdateSpy = jest.spyOn(spatialGallerySliderViewerPlugin.spatialCanvasSizeUpdateUtil, 'update');
        const spatialOrbitControlsUtilUpdateSpy = jest.spyOn(spatialGallerySliderViewerPlugin.spatialOrbitControlsUtil, 'update');
        spatialGallerySliderViewerPlugin.rendering = true;

        spatialGallerySliderViewerPlugin.render();

        expect(spatialCanvasSizeUpdateUtilUpdateSpy).toHaveBeenCalled();
        expect(spatialOrbitControlsUtilUpdateSpy).toHaveBeenCalled();
    });

    test('initViewer with model and light intensity', async () => {
        spatialGallerySliderViewerPlugin.el.setAttribute('data-spatial-light-intensity', '100');
        spatialGallerySliderViewerPlugin.el.setAttribute('data-spatial-model-url', 'http://test/file.glb');
        jest.spyOn(SpatialObjectLoaderUtil.prototype, 'loadSingleObjectByUrl').mockReturnValue(Promise.resolve('123'));

        spatialGallerySliderViewerPlugin.initViewer(false);

        await new Promise(process.nextTick);

        expect(spatialGallerySliderViewerPlugin.scene.add).toHaveBeenCalledTimes(1);
    });

    test('initViewer with defined spatial model url will load model', async () => {
        spatialGallerySliderViewerPlugin.el.setAttribute('data-spatial-model-url', 'http://test/file.glb');
        jest.spyOn(SpatialObjectLoaderUtil.prototype, 'loadSingleObjectByUrl').mockReturnValue(Promise.resolve('123'));

        spatialGallerySliderViewerPlugin.initViewer(true);

        await new Promise(process.nextTick);

        expect(spatialGallerySliderViewerPlugin.scene.add).toHaveBeenCalledTimes(1);
    });

    test('initViewer with incorrect uploaded model from url will disable slider canvas', async () => {
        const parentDiv = document.createElement('span');
        const middleDiv = document.createElement('div');
        spatialGallerySliderViewerPlugin.el.setAttribute('data-spatial-model-url', 'http://test/file.glb');
        jest.spyOn(SpatialObjectLoaderUtil.prototype, 'loadSingleObjectByUrl').mockReturnValue(Promise.reject('123'));
        middleDiv.appendChild(spatialGallerySliderViewerPlugin.canvas);
        parentDiv.appendChild(middleDiv);

        spatialGallerySliderViewerPlugin.initViewer(true);

        await new Promise(process.nextTick);

        expect(spatialGallerySliderViewerPlugin.el.parentElement.parentElement.classList.contains('gallery-slider-canvas-disabled')).toBe(true);
    });
});
