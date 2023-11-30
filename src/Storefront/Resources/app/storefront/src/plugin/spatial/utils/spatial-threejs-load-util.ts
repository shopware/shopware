/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */
export async function loadThreeJs(): Promise<void> {
    /* eslint-disable */
    if (!window.threeJs) {
        window.threeJs = await import('three');
    }

    if (!window.threeJsAddons) {
        window.threeJsAddons = {};
    }

    if (!window.threeJsAddons?.OrbitControls) {
        const { OrbitControls } = await import('three/examples/jsm/controls/OrbitControls.js');
        window.threeJsAddons.OrbitControls = OrbitControls;
    }

    if (!window.threeJsAddons?.USDZExporter) {
        const { USDZExporter } = await import('three/examples/jsm/exporters/USDZExporter.js');
        window.threeJsAddons.USDZExporter = USDZExporter;
    }

    if (!window.threeJsAddons?.XREstimatedLight) {
        const { XREstimatedLight } = await import('three/examples/jsm/webxr/XREstimatedLight.js');
        window.threeJsAddons.XREstimatedLight = XREstimatedLight;
    }

    if (!window.threeJsAddons?.GLTFLoader) {
        const { GLTFLoader } = await import('three/examples/jsm/loaders/GLTFLoader.js');
        window.threeJsAddons.GLTFLoader = GLTFLoader;
    }
    /* eslint-enable */
}
