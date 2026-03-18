/**
 * @sw-package framework
 */
import type * as AmplitudeClient from '@amplitude/analytics-browser';

type AmplitudeModule = typeof AmplitudeClient;
type PrivacyAmplitudeClient = ReturnType<AmplitudeModule['createInstance']>;

const AMPLITUDE_BROWSER_API_KEY = 'placeholder-apikey';
const AMPLITUDE_MAX_RETRIES = 2;
const AMPLITUDE_LOG_LEVEL_NONE = 0;
let originalSendBeacon: typeof navigator.sendBeacon | null = null;
let wrappedBeaconCallbacks = 0;

/**
 * @private
 */
export function registerTelemetryLogoutListener(amplitude: AmplitudeModule, analyticsGatewayUrl: string): void {
    Shopware.Service('loginService').addOnLogoutListener(() => {
        const restoreSendBeacon = wrapJsonBeaconPayload(`${analyticsGatewayUrl}/event`);
        amplitude.setTransport('beacon');
        setTimeout(() => {
            amplitude.flush();
            amplitude.reset();
            setTimeout(() => restoreSendBeacon?.(), 0);
        }, 0);
    });
}

/**
 * @private
 */
export function initTelemetryAmplitude(amplitude: AmplitudeModule, analyticsGatewayUrl: string): void {
    // The real key will be added by the gateway
    amplitude.init(AMPLITUDE_BROWSER_API_KEY, undefined, createAmplitudeInitOptions(`${analyticsGatewayUrl}/event`));
}

/**
 * @private
 */
export function createPrivacyAmplitudeClient(
    amplitude: AmplitudeModule,
    analyticsGatewayUrl: string,
): PrivacyAmplitudeClient {
    const privacyAmplitude = amplitude.createInstance();

    // The real key will be added by the gateway
    privacyAmplitude.init(
        AMPLITUDE_BROWSER_API_KEY,
        undefined,
        createAmplitudeInitOptions(`${analyticsGatewayUrl}/delete-user`),
    );

    return privacyAmplitude;
}

/**
 * @private
 */
export function getAmplitudeBrowserApiKeyPrefix(): string {
    return AMPLITUDE_BROWSER_API_KEY.substring(0, 10);
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

function createAmplitudeInitOptions(serverUrl: string) {
    return {
        autocapture: false,
        serverZone: 'EU' as const,
        appVersion: Shopware.Store.get('context').app.config.version as string,
        flushMaxRetries: AMPLITUDE_MAX_RETRIES,
        logLevel: AMPLITUDE_LOG_LEVEL_NONE,
        trackingOptions: {
            ipAddress: false,
            language: false,
            platform: false,
        },
        fetchRemoteConfig: false,
        serverUrl,
    };
}
