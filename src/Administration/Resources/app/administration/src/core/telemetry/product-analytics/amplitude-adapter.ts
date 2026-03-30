/**
 * @sw-package data-services
 */
import type { EventPayload, TrackingClient } from './gateway-client';
import { createInstance, Types } from '@amplitude/analytics-browser';
import { amplitudePluginShopwareProperties } from './amplitude-plugin-shopware-properties';
import { CookieStorage } from 'cookie-storage';

const AMPLITUDE_BROWSER_API_KEY = 'placeholder-apikey';
const AMPLITUDE_COOKIE_PREFIX = AMPLITUDE_BROWSER_API_KEY.substring(0, 10);

const AMPLITUDE_MAX_RETRIES = 2;
const AMPLITUDE_LOG_LEVEL_NONE = Types.LogLevel.None;

/**
 * @private
 */
export class AmplitudeAdapter implements TrackingClient {
    #isInitialized: boolean;
    #serverUrl: string;
    #amplitudeInstance: ReturnType<typeof createInstance>;

    constructor(serverUrl: string, defaultLanguage: string) {
        this.#serverUrl = `${serverUrl}/v1/event`;
        this.#amplitudeInstance = createInstance();
        this.#amplitudeInstance.add(amplitudePluginShopwareProperties(defaultLanguage));
        this.#isInitialized = false;
    }

    clearStorage(): void {
        const storage = new CookieStorage({
            path: '/',
            sameSite: 'Lax',
        });

        [
            `AMP_${AMPLITUDE_COOKIE_PREFIX}`,
            `AMP_MKTG_${AMPLITUDE_COOKIE_PREFIX}`,
        ].forEach((cookieName) => {
            storage.removeItem(cookieName);
        });
    }

    getUserId(): string | null {
        return this.#amplitudeInstance.getUserId() ?? null;
    }

    identify(userId: string /* , userProperties?: EventPayload */): void {
        this.#amplitudeInstance.setUserId(userId);
    }

    init(): void {
        if (this.#isInitialized) {
            return;
        }

        this.#amplitudeInstance.init(AMPLITUDE_BROWSER_API_KEY, undefined, {
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
            serverUrl: this.#serverUrl,
        });

        this.#isInitialized = true;
    }

    get isInitialized() {
        return this.#isInitialized;
    }

    track(eventName: string, eventPayload?: EventPayload): void {
        if (!this.isInitialized) {
            return;
        }

        this.#amplitudeInstance.track(eventName, eventPayload);
    }

    flush() {
        this.#amplitudeInstance.flush();
    }

    setOptOut(optOut: boolean) {
        this.#amplitudeInstance.setOptOut(optOut);
    }
}
