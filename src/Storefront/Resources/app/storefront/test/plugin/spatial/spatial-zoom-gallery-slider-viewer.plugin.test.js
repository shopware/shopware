import SpatialZoomGallerySliderViewerPlugin from 'src/plugin/spatial/spatial-zoom-gallery-slider-viewer.plugin';
import SpatialBaseViewerPlugin from 'src/plugin/spatial/spatial-base-viewer.plugin';

jest.mock('src/plugin/spatial/utils/spatial-dive-load-util');
jest.mock('src/plugin/spatial/utils/spatial-zoom-gallery-slider-render-util');

const mockDive = {
    engine: {
        start: jest.fn(),
    }
};
window.DIVEClass = {
    QuickView: jest.fn().mockResolvedValue(mockDive)
};

/**
 * @package innovation
 */
describe('SpatialZoomGallerySliderViewerPlugin tests', function () {
    let spatialZoomGallerySliderViewerPlugin;
    let targetElement;

    beforeEach(() => {
        targetElement = document.createElement('div');
        jest.useFakeTimers();

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

    test('initViewer with defined spatial model url will load model', async () => {
        spatialZoomGallerySliderViewerPlugin.initViewer();

        process.nextTick(() =>
            expect(spatialZoomGallerySliderViewerPlugin.scene.add).toHaveBeenCalledTimes(1)
        );
    });

    test('initViewer with incorrect uploaded model from url will disable slider canvas', async () => {
        const parentDiv = document.createElement('span');
        const middleDiv = document.createElement('div');
        middleDiv.appendChild(spatialZoomGallerySliderViewerPlugin.canvas);
        parentDiv.appendChild(middleDiv);

        spatialZoomGallerySliderViewerPlugin.initViewer();

        process.nextTick(() =>
            expect(spatialZoomGallerySliderViewerPlugin.el.parentElement.parentElement.classList.contains('gallery-slider-canvas-disabled')).toBe(true)
        );
    });

    test('should disable slider canvas if super.initViewer throws', async () => {
        // Spy on base class initViewer to throw an error
        jest.spyOn(SpatialBaseViewerPlugin.prototype, 'initViewer').mockRejectedValueOnce(new Error('test error'));
        // Setup nested parent elements to match el.parentElement.parentElement
        const parent = document.createElement('div');
        const grandParent = document.createElement('div');
        parent.appendChild(targetElement);
        grandParent.appendChild(parent);
        // Call initViewer
        await spatialZoomGallerySliderViewerPlugin.initViewer();
        // Assert the disabled class is added to the grand parent element
        expect(grandParent.classList.contains('gallery-slider-canvas-disabled')).toBe(true);
    });
});
