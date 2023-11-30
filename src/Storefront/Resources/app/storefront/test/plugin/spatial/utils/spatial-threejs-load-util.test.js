import { loadThreeJs } from 'src/plugin/spatial/utils/spatial-threejs-load-util';

jest.mock('three', () => 'three');
jest.mock('three/examples/jsm/controls/OrbitControls.js', () => {return { OrbitControls: {}}});
jest.mock('three/examples/jsm/exporters/USDZExporter.js', () => {return { USDZExporter: {}}});
jest.mock('three/examples/jsm/webxr/XREstimatedLight.js', () => {return { XREstimatedLight: {}}});
jest.mock('three/examples/jsm/loaders/GLTFLoader.js', () => {return { GLTFLoader: {}}});

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

        await loadThreeJs();

        expect(typeof window.threeJs).toBe('object');
        expect(typeof window.threeJsAddons.OrbitControls).toBe('object');
        expect(typeof window.threeJsAddons.USDZExporter).toBe('object');
        expect(typeof window.threeJsAddons.XREstimatedLight).toBe('object');
        expect(typeof window.threeJsAddons.GLTFLoader).toBe('object');
    });
});
