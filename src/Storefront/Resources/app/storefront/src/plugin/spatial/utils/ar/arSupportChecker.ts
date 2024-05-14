/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */

/**
 * Returns true if the device supports AR in any way.
 * @returns {Promise<boolean>}
 */
export async function supportsAr(): Promise<boolean> {
    return (await supportWebXR()) || supportQuickLook();
}

/**
 * Returns true if the device supports QuickLook.
 * QuickLook is a feature of iOS 12 and above
 * @returns {boolean}
 */
export function supportQuickLook(): boolean {
    const a = document.createElement('a');
    return a.relList.supports('ar');
}

/**
 * Returns true if the device supports WebXR with immersive-ar mode
 * @returns {Promise<boolean>}
 */
export async function supportWebXR(): Promise<boolean> {
    if (!navigator.xr) { return false; }
    return await navigator.xr.isSessionSupported('immersive-ar');
}
