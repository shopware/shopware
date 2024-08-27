import { type Object3D } from 'three';

/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */
export default async function iosQuickLook(scene: Object3D) {
    const modelUrl = await generateUSDZ(scene);

    const anchor = document.createElement('a');
    anchor.innerHTML = '<picture></picture>'; // This is actually needed so the viewer opens instantly
    anchor.setAttribute('rel', 'ar');
    anchor.setAttribute('download', 'model.usdz');
    anchor.setAttribute('href', modelUrl);
    anchor.style.display = 'none';

    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
}

/**
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */
async function generateUSDZ(scene: Object3D) {
    // eslint-disable-next-line
    const usdz = new window.threeJsAddons.USDZExporter();
    // eslint-disable-next-line
    const arrayBuffer = await usdz.parse(scene);
    const blob = new Blob([arrayBuffer], { type: 'model/vnd.usdz+zip' });
    return URL.createObjectURL(blob);
}
