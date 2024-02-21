import { loadThreeJs } from 'src/plugin/spatial/utils/spatial-threejs-load-util';

jest.mock('three', () => 'three');
jest.mock('three/examples/jsm/controls/OrbitControls.js', () => {return { OrbitControls: {}}});
jest.mock('three/examples/jsm/exporters/USDZExporter.js', () => {return { USDZExporter: {}}});
jest.mock('three/examples/jsm/webxr/XREstimatedLight.js', () => {return { XREstimatedLight: {}}});
jest.mock('three/examples/jsm/loaders/GLTFLoader.js', () => {return { GLTFLoader: {}}});
jest.mock('three/examples/jsm/loaders/DRACOLoader.js', () => {return { DRACOLoader: {}}});

/**
 * @package innovation
 */
describe('loadThreeJs', () => {
    beforeEach(() => {
        jest.clearAllMocks();
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    test('should load threeJs', async () => {
        expect(typeof window.threeJs).toBe('undefined');
        expect(typeof window.threeJsAddons).toBe('undefined');
        expect(typeof window.loadThreeJsUtil).toBe('undefined');

        await loadThreeJs();

        expect(typeof window.threeJs).toBe('object');
        expect(typeof window.threeJsAddons.OrbitControls).toBe('object');
        expect(typeof window.threeJsAddons.USDZExporter).toBe('object');
        expect(typeof window.threeJsAddons.XREstimatedLight).toBe('object');
        expect(typeof window.threeJsAddons.GLTFLoader).toBe('object');
        expect(typeof window.threeJsAddons.DRACOLoader).toBe('object');
        expect(typeof window.loadThreeJsUtil.promise).toBe('object');
        expect(window.loadThreeJsUtil.isLoaded).toBe(true);
    });

    test('should not load threeJs if already loaded', async () => {
        window.threeJs = 'threeJs';
        window.threeJsAddons = 'threeJsAddons';
        window.loadThreeJsUtil = {
            isLoaded: true
        };
        await loadThreeJs();
        expect(window.threeJs).toBe('threeJs');
        expect(window.threeJsAddons).toBe('threeJsAddons');
    });

    test('should not run import when threeJs is already loading', async () => {
        window.loadThreeJsUtil = {
            promise: new Promise((resolve) => { resolve(); }),
            isLoaded: false
        }
        await loadThreeJs();
        expect(window.loadThreeJsUtil.isLoaded).toBe(false);
    });
});
