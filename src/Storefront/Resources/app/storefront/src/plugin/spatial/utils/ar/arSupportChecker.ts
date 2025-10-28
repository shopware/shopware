/**
 * @package innovation
 *
 * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
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
    // Check if we're in a secure context (HTTPS)
    if (!window.isSecureContext) {
        return false;
    }

    // Check if XRSystem is available
    if (!navigator.xr) {
        return false;
    }

    try {
        // Check specifically for immersive-ar support
        return await navigator.xr.isSessionSupported('immersive-ar');
    } catch (error) {
        return false;
    }
}
