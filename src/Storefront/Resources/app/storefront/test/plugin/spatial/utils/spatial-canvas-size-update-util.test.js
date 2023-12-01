import SpatialCanvasSizeUpdateUtil from 'src/plugin/spatial/utils/spatial-canvas-size-update-util';

/**
 * @package innovation
 */
describe('spatial-canvas-size-update-util', () => {
    let spatialCanvasSizeUpdateUtil = undefined;
    let pluginMock = undefined;

    beforeEach(() => {
        const wrapperEl = document.createElement('div');
        const canvasEl = document.createElement('canvas')
        wrapperEl.appendChild(canvasEl);

        const customPrototype = Object.create(Object.getPrototypeOf(wrapperEl));
        Object.defineProperties(customPrototype, {
            clientHeight: {
                value: 150,
                writable: false,
                configurable: true,
            },
            clientWidth: {
                value: 300,
                writable: false,
                configurable: true,
            },
        });

        // Set the new prototype to mockDiv
        Object.setPrototypeOf(wrapperEl, customPrototype);
        Object.defineProperty(canvasEl, 'clientHeight', { value: 150 });
        Object.defineProperty(canvasEl, 'clientWidth', { value: 300 });

        pluginMock = {
            canvas: canvasEl,
            camera: {
                aspect: 1,
                updateProjectionMatrix: jest.fn(),
            },
            renderer: {
                setSize: jest.fn(),
            },
            $emitter: {
                publish: jest.fn(),
            },
        }

        spatialCanvasSizeUpdateUtil = new SpatialCanvasSizeUpdateUtil(pluginMock);
        spatialCanvasSizeUpdateUtil.init();
    });

    test('util should exist', () => {
        expect(typeof spatialCanvasSizeUpdateUtil).toBe('object');
    });

    test('util is initialized with correct values', () => {
        expect(spatialCanvasSizeUpdateUtil.plugin).toBe(pluginMock);
        expect(spatialCanvasSizeUpdateUtil.lastWidth).toBe(300);
        expect(spatialCanvasSizeUpdateUtil.lastHeight).toBe(150);
    });

    test('util should not update canvas size if wrapper size is the same', () => {
        expect(pluginMock.canvas.width).toBe(300);
        expect(pluginMock.canvas.height).toBe(150);

        expect(pluginMock.camera.updateProjectionMatrix).not.toHaveBeenCalled();
        expect(pluginMock.renderer.setSize).not.toHaveBeenCalled();

        spatialCanvasSizeUpdateUtil.update();

        expect(pluginMock.canvas.width).toBe(300);
        expect(pluginMock.canvas.height).toBe(150);

        expect(pluginMock.camera.updateProjectionMatrix).not.toHaveBeenCalled();
        expect(pluginMock.renderer.setSize).not.toHaveBeenCalled();
    });

    test('util should update canvas size if wrapper size is different', () => {
        expect(pluginMock.canvas.width).toBe(300);
        expect(pluginMock.canvas.height).toBe(150);

        expect(pluginMock.camera.updateProjectionMatrix).not.toHaveBeenCalled();
        expect(pluginMock.renderer.setSize).not.toHaveBeenCalled();
        expect(pluginMock.$emitter.publish).not.toHaveBeenCalled();

        Object.defineProperty(pluginMock.canvas.parentElement, 'clientHeight', { value: 200 });
        Object.defineProperty(pluginMock.canvas.parentElement, 'clientWidth', { value: 400 });

        spatialCanvasSizeUpdateUtil.update();

        expect(pluginMock.canvas.width).toBe(400);
        expect(pluginMock.canvas.height).toBe(200);

        expect(pluginMock.camera.aspect).toBe(2);

        expect(pluginMock.camera.updateProjectionMatrix).toHaveBeenCalled();
        expect(pluginMock.renderer.setSize).toHaveBeenCalled();
        expect(pluginMock.$emitter.publish).toHaveBeenCalled();
    });
});
