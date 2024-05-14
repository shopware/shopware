import SpatialBaseViewerPlugin from 'src/plugin/spatial/spatial-base-viewer.plugin';
import { loadThreeJs } from 'src/plugin/spatial/utils/spatial-threejs-load-util';

jest.mock('src/plugin/spatial/utils/spatial-threejs-load-util');

/**
 * @package innovation
 */
describe('SpatialBaseViewerPlugin tests', () => {
    let spatialBaseViewerPlugin;
    let parentDiv;
    let parentDivClassListAddSpy;
    let parentDivClassListRemoveSpy;
    let emitterPublishSpy;
    let requestAnimationFrameSpy;


    beforeEach(() => {
        document.body.innerHTML =  `
            <div id="parentDiv">
                <canvas id="canvasEl"></canvas>
            </div>
        `;
        parentDiv = document.getElementById('parentDiv');

        jest.useFakeTimers();
        window.threeJs = {};

        window.threeJs.PerspectiveCamera = function () {
            return {
                position: {
                    set: jest.fn()
                }
            }
        };
        window.threeJs.Scene = function () {
            return {
                add: function () {},
                remove: function () {}
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
                xr: {
                    enabled: null,
                    getController: () => {
                        return {
                            addEventListener: jest.fn()
                        }
                    },
                    setReferenceSpaceType: jest.fn(),
                    getReferenceSpace: jest.fn(()=> {
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

        spatialBaseViewerPlugin = new SpatialBaseViewerPlugin(document.getElementById('canvasEl'));
        parentDivClassListAddSpy = jest.spyOn(parentDiv.classList, 'add');
        parentDivClassListRemoveSpy = jest.spyOn(parentDiv.classList, 'remove');
        emitterPublishSpy = jest.spyOn(spatialBaseViewerPlugin.$emitter, 'publish');
        requestAnimationFrameSpy = jest.spyOn(window, 'requestAnimationFrame');
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    test('plugin initializes', () => {
        expect(typeof spatialBaseViewerPlugin).toBe('object');
    });

    test('setReady no action if already set ready property', () => {
        spatialBaseViewerPlugin.ready = false;

        spatialBaseViewerPlugin.setReady(true);

        expect(spatialBaseViewerPlugin.ready).toBe(true);
    });

    test('setReady makes no action if already set the same value as in the parameter', () => {
        spatialBaseViewerPlugin.ready = true;

        spatialBaseViewerPlugin.setReady(true);

        expect(parentDivClassListAddSpy).not.toHaveBeenCalled();
    });

    test('setReady with parameter `state` in true will add class `spatial-canvas-ready`', () => {
        spatialBaseViewerPlugin.ready = false;
        spatialBaseViewerPlugin.rendering = false;

        spatialBaseViewerPlugin.setReady(true);

        expect(parentDivClassListAddSpy).toHaveBeenCalledTimes(1);
        expect(parentDivClassListAddSpy).toHaveBeenCalledWith('spatial-canvas-ready');
    });

    test('setReady with parameter `state` in true and property `rendering` is true will add class `spatial-canvas-display`', () => {
        spatialBaseViewerPlugin.ready = false;
        spatialBaseViewerPlugin.rendering = true;

        spatialBaseViewerPlugin.setReady(true);

        expect(parentDivClassListAddSpy).toHaveBeenCalledTimes(2);
        expect(parentDivClassListAddSpy).toHaveBeenLastCalledWith('spatial-canvas-display');
    });

    test('setReady with parameter `state` in false will remove classes `spatial-canvas-ready` and `spatial-canvas-display`', () => {
        spatialBaseViewerPlugin.ready = true;

        spatialBaseViewerPlugin.setReady(false);

        expect(parentDivClassListRemoveSpy).toHaveBeenCalledTimes(2);
        expect(parentDivClassListRemoveSpy).toHaveBeenLastCalledWith('spatial-canvas-display');
    });

    test('onReady with undefined `canvas` will makes no actions', () => {
        spatialBaseViewerPlugin.ready = true;
        spatialBaseViewerPlugin.canvas = undefined;
        emitterPublishSpy.mockClear();

        spatialBaseViewerPlugin.setReady(false);

        expect(emitterPublishSpy).not.toHaveBeenCalled();
    });

    test('startRendering if already rendered will makes no actions', () => {
        spatialBaseViewerPlugin.rendering = true;

        spatialBaseViewerPlugin.startRendering();

        expect(requestAnimationFrameSpy).not.toHaveBeenCalled();
    });

    test('startRendering with `ready` property in false will not add the class `spatial-canvas-display`', () => {
        spatialBaseViewerPlugin.rendering = false;
        spatialBaseViewerPlugin.ready = false;

        spatialBaseViewerPlugin.startRendering();

        expect(requestAnimationFrameSpy).toHaveBeenCalled();
        expect(parentDivClassListAddSpy).toHaveBeenCalledTimes(1);
        expect(parentDivClassListAddSpy).toHaveBeenCalledWith('spatial-canvas-rendering');
        expect(emitterPublishSpy).toHaveBeenCalled();
    });

    test('startRendering with `ready` property in true will add the class `spatial-canvas-display`', () => {
        spatialBaseViewerPlugin.rendering = false;
        spatialBaseViewerPlugin.ready = true;

        spatialBaseViewerPlugin.startRendering();

        expect(parentDivClassListAddSpy).toHaveBeenCalledTimes(2);
        expect(parentDivClassListAddSpy).toHaveBeenCalledWith('spatial-canvas-display');
    });

    test('stopRendering will stop rendering loop', () => {
        spatialBaseViewerPlugin.stopRendering();

        expect(spatialBaseViewerPlugin.rendering).toBe(false);
        expect(parentDivClassListRemoveSpy).toHaveBeenCalledWith('spatial-canvas-rendering');
        expect(emitterPublishSpy).toHaveBeenCalledWith('Viewer/stopRendering');
    });

    test('render with an undefined click will make no actions', () => {
        const prerenderSpy = jest.spyOn(spatialBaseViewerPlugin, 'preRender');
        spatialBaseViewerPlugin.clock = undefined;

        spatialBaseViewerPlugin.rendering = true;

        spatialBaseViewerPlugin.render();

        expect(requestAnimationFrameSpy).toHaveBeenCalledTimes(1);
        expect(requestAnimationFrameSpy).toHaveBeenCalled();
        expect(prerenderSpy).not.toHaveBeenCalled();
    });

    test('render by default will render through the renderer', () => {
        const rendererSpy = jest.spyOn(spatialBaseViewerPlugin.renderer, 'render');
        const clockGetDeltaSpy = jest.spyOn(spatialBaseViewerPlugin.clock, 'getDelta');
        const postRenderSpy = jest.spyOn(spatialBaseViewerPlugin, 'postRender');
        clockGetDeltaSpy.mockReturnValue(0.1);
        spatialBaseViewerPlugin.rendering = true;

        spatialBaseViewerPlugin.render();

        expect(rendererSpy).toHaveBeenCalledTimes(1);
        expect(postRenderSpy).toHaveBeenCalledWith(0.1);
    });

    test('render with undefined camera, scene and renderer will not call renderer', () => {
        const rendererSpy = jest.spyOn(spatialBaseViewerPlugin.renderer, 'render');
        const clockGetDeltaSpy = jest.spyOn(spatialBaseViewerPlugin.clock, 'getDelta');
        const postRenderSpy = jest.spyOn(spatialBaseViewerPlugin, 'postRender');
        clockGetDeltaSpy.mockReturnValue(0.1);
        spatialBaseViewerPlugin.rendering = true;
        spatialBaseViewerPlugin.camera = undefined;
        spatialBaseViewerPlugin.scene = undefined;
        spatialBaseViewerPlugin.renderer = undefined;

        spatialBaseViewerPlugin.render();

        expect(rendererSpy).not.toHaveBeenCalled();
    });

    test('render with rendering property in false will not render', () => {
        spatialBaseViewerPlugin.rendering = false;

        spatialBaseViewerPlugin.render();

        expect(requestAnimationFrameSpy).not.toHaveBeenCalled();
    });
});
