/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */
export async function loadThreeJs(): Promise<void> {

    if (!window.loadThreeJsUtil) {
        window.loadThreeJsUtil = {
            isLoaded: false,
            promise: null,
            promiseResolve: null,
        };
    }

    /* eslint-disable */
    if (window.loadThreeJsUtil.isLoaded) {
        return;
    }

    if (window.loadThreeJsUtil.promise) {
        await window.loadThreeJsUtil.promise;
        return;
    }

    window.loadThreeJsUtil.promise = new Promise((resolve) => {
        window.loadThreeJsUtil.promiseResolve = resolve;
    });

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

    if (!window.threeJsAddons?.DRACOLoader) {
        const { DRACOLoader } = await import('three/examples/jsm/loaders/DRACOLoader.js');
        window.threeJsAddons.DRACOLoader = DRACOLoader;
    }

    if (!window.threeJsAddons?.DRACOLibPath) {
        window.threeJsAddons.DRACOLibPath = 'three/examples/jsm/libs/draco/';
    }

    window.loadThreeJsUtil.promiseResolve();
    window.loadThreeJsUtil.isLoaded = true;
    /* eslint-enable */
}
