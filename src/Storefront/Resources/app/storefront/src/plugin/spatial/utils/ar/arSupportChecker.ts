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
    // Native IOS Support (Safari)
    const a = document.createElement('a');
    if (a.relList.supports('ar')) {
        return true;
    }

    // Other Browser support
    const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    const iosVersion = navigator.userAgent.match(/OS (\d+)_(\d+)_?(\d+)?/);
    if (!isIos || !iosVersion) { return false; }
    if (parseInt(iosVersion[1], 10) < 12) { return false; }

    // These browsers currently support AR Quick Look on iOS
    const isChromeOrVivaldi = /CriOS/.test(navigator.userAgent);
    const isEdge = /EdgiOS/.test(navigator.userAgent);
    const isDuckDuckGo = /Ddg/.test(navigator.userAgent);

    return isChromeOrVivaldi || isEdge || isDuckDuckGo;
}

/**
 * Returns true if the device supports WebXR with immersive-ar mode
 * @returns {Promise<boolean>}
 */
export async function supportWebXR(): Promise<boolean> {
    if (!navigator.xr) { return false; }
    return await navigator.xr.isSessionSupported('immersive-ar');
}
