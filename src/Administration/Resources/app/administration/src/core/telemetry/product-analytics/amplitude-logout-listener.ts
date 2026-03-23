/**
 * @sw-package data-services
 */
import type { AmplitudeAdapter } from './amplitude-adapter';

let originalSendBeacon: typeof navigator.sendBeacon | null = null;
let wrappedBeaconCallbacks = 0;

/**
 * @private
 */
export function registerAmplitudeLogoutListener(adapter: AmplitudeAdapter, analyticsGatewayUrl: string): void {
    Shopware.Service('loginService').addOnLogoutListener(() => {
        const restoreSendBeacon = wrapJsonBeaconPayload(`${analyticsGatewayUrl}/v1/event`);
        adapter.setTransport('beacon');
        setTimeout(() => {
            adapter.flush();
            adapter.reset();
            setTimeout(() => restoreSendBeacon?.(), 0);
        }, 0);
    });
}

function wrapJsonBeaconPayload(targetUrl: string): (() => void) | null {
    if (typeof navigator === 'undefined' || typeof navigator.sendBeacon !== 'function' || typeof Blob === 'undefined') {
        return null;
    }

    if (originalSendBeacon === null) {
        originalSendBeacon = navigator.sendBeacon.bind(navigator);
        const nativeSendBeacon = originalSendBeacon;

        navigator.sendBeacon = ((url: string | URL, data?: BodyInit | null): boolean => {
            if (typeof data === 'string' && url.toString() === targetUrl) {
                return nativeSendBeacon(url, new Blob([data], { type: 'application/json' }));
            }

            return nativeSendBeacon(url, data);
        }) as typeof navigator.sendBeacon;
    }

    wrappedBeaconCallbacks += 1;
    let restored = false;

    return () => {
        if (restored) {
            return;
        }

        restored = true;
        wrappedBeaconCallbacks -= 1;

        if (wrappedBeaconCallbacks <= 0 && originalSendBeacon !== null) {
            wrappedBeaconCallbacks = 0;
            navigator.sendBeacon = originalSendBeacon;
            originalSendBeacon = null;
        }
    };
}
