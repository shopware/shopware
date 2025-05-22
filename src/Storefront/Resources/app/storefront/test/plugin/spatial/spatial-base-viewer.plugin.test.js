import SpatialBaseViewerPlugin from 'src/plugin/spatial/spatial-base-viewer.plugin';

jest.mock('src/plugin/spatial/utils/spatial-dive-load-util');

/**
 * @package innovation
 */
describe('SpatialBaseViewerPlugin tests', () => {
    let spatialBaseViewerPlugin;
    let parentDiv;
    let parentDivClassListAddSpy;
    let parentDivClassListRemoveSpy;
    let emitterPublishSpy;
    const mockDive = {
        engine: {
            start: jest.fn(),
        }
    };
    window.DIVEClass = {
        QuickView: jest.fn().mockResolvedValue(mockDive)
    };

    beforeEach(() => {
        document.body.innerHTML =  `
            <div id="parentDiv">
                <canvas id="canvasEl"></canvas>
            </div>
        `;
        parentDiv = document.getElementById('parentDiv');

        jest.useFakeTimers();

        spatialBaseViewerPlugin = new SpatialBaseViewerPlugin(document.getElementById('canvasEl'));
        parentDivClassListAddSpy = jest.spyOn(parentDiv.classList, 'add');
        parentDivClassListRemoveSpy = jest.spyOn(parentDiv.classList, 'remove');
        emitterPublishSpy = jest.spyOn(spatialBaseViewerPlugin.$emitter, 'publish');
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

        expect(mockDive.engine.start).not.toHaveBeenCalled();
    });

    test('startRendering with `ready` property in false will not add the class `spatial-canvas-display`', () => {
        spatialBaseViewerPlugin.rendering = false;
        spatialBaseViewerPlugin.ready = false;

        spatialBaseViewerPlugin.startRendering();

        expect(mockDive.engine.start).not.toHaveBeenCalled();
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
});
