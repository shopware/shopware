/**
 * @sw-package data-services
 */
import type { ConsentEventName, TrackableType } from 'src/core/consent/events';

const JSON_POST_OPTIONS: RequestInit = {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
    },
    credentials: 'omit',
    keepalive: true,
};

const FLUSH_INTERVAL_MS = 1000;
const MAX_BATCH_SIZE = 30;
const MAX_QUEUE_SIZE = 300;
const RETRY_DELAY_MS = 1000;
const MAX_RETRY_DELAY_MS = 30000;
const MAX_RETRY_ATTEMPTS = 2;
const DEVICE_ID_STORAGE_KEY = 'sw-product-analytics-device-id';
const SESSION_ID_STORAGE_KEY = 'sw-product-analytics-session-id';

/**
 * @private
 */
export type EventPayload = Record<string, TrackableType>;

type GatewayEvent = {
    device_id: string;
    insert_id: string;
    name: string;
    session_id: number;
    timestamp: number;
    properties: EventPayload;
};

type GatewayContext = {
    sw_version: string;
    sw_app_url: string;
    sw_browser_url: string;
    sw_user_agent: string;
    sw_default_language: string;
    sw_default_currency: string;
    sw_screen_width: number;
    sw_screen_height: number;
    sw_screen_orientation: string;
};

type GatewayUser = {
    shop_id: string;
    id: string;
};

type GatewayEventRequest = {
    user: GatewayUser;
    context: GatewayContext;
    events: GatewayEvent[];
};

type GatewayAnonymousEvent = {
    name: string;
    timestamp: number;
    properties: EventPayload;
};

type GatewayAnonymousEventRequest = {
    context: Pick<GatewayContext, 'sw_version'>;
    events: GatewayAnonymousEvent[];
};

/**
 * @private
 */
export interface TrackingClient {
    init(): void;
    track(eventName: string, eventPayload?: EventPayload): void;
    identify(userId: string): void;
    getUserId(): string | null;
    clearStorage(): void;
    flush(): Promise<void>;
    isInitialized: boolean;
}

/**
 * @private
 */
export class GatewayClient implements TrackingClient {
    #activeFlushPromise: Promise<void> | null;
    #flushTimer: ReturnType<typeof setTimeout> | null;
    #isInitialized: boolean;
    #isOptedOut: boolean;
    #queue: GatewayEvent[];
    #retryAttempt: number;
    #retryTimer: ReturnType<typeof setTimeout> | null;
    #sessionId: number | null;
    #userId: string | null;

    constructor(
        private readonly gateWayUrl: string,
        private readonly defaultLanguage: string,
    ) {
        this.#activeFlushPromise = null;
        this.#flushTimer = null;
        this.#isInitialized = false;
        this.#isOptedOut = true;
        this.#queue = [];
        this.#retryAttempt = 0;
        this.#retryTimer = null;
        const storedSessionId = this.#readStorage(window.sessionStorage, SESSION_ID_STORAGE_KEY);
        const numericSessionId = storedSessionId !== null ? Number(storedSessionId) : NaN;
        this.#sessionId = Number.isFinite(numericSessionId) ? numericSessionId : null;
        this.#userId = null;
    }

    init(): void {
        if (this.#sessionId === null) {
            this.#sessionId = Date.now();
            this.#writeStorage(window.sessionStorage, SESSION_ID_STORAGE_KEY, String(this.#sessionId));
        }

        this.#isInitialized = true;
    }

    get isInitialized() {
        return this.#isInitialized;
    }

    track(eventName: string, eventPayload: EventPayload = {}): void {
        if (!this.#isInitialized || this.#isOptedOut) {
            return;
        }

        this.#queue.push({
            device_id: this.#getOrCreateDeviceId(),
            insert_id: this.#createInsertId(),
            name: eventName,
            session_id: this.#sessionId ?? Date.now(),
            timestamp: Date.now(),
            properties: {
                ...eventPayload,
                ...this.#getRouteProperties(),
            },
        });

        if (this.#queue.length > MAX_QUEUE_SIZE) {
            const dropped = this.#queue.length - MAX_QUEUE_SIZE;
            this.#queue.splice(0, dropped);
        }

        this.#scheduleFlush(FLUSH_INTERVAL_MS);
    }

    identify(userId: string): void {
        this.#userId = userId;

        if (this.#queue.length > 0) {
            this.#scheduleFlush(0);
        }
    }

    getUserId(): string | null {
        return this.#userId;
    }

    clearStorage(): void {
        this.#clearFlushTimer();
        this.#clearRetryTimer();
        this.#queue = [];
        this.#removeStorage(window.localStorage, DEVICE_ID_STORAGE_KEY);
        this.#removeStorage(window.sessionStorage, SESSION_ID_STORAGE_KEY);
        this.#isInitialized = false;
        this.#sessionId = null;
        this.#userId = null;
        this.#retryAttempt = 0;
    }

    flush(): Promise<void> {
        this.#clearFlushTimer();
        this.#clearRetryTimer();
        return this.#flushQueue();
    }

    flushWithoutRetry(): Promise<void> {
        this.#clearFlushTimer();
        this.#clearRetryTimer();
        return this.#flushQueue(false);
    }

    setOptOut(optOut: boolean): void {
        this.#isOptedOut = optOut;

        if (optOut) {
            this.#clearFlushTimer();
            this.#clearRetryTimer();
        } else if (this.#queue.length > 0) {
            this.#scheduleFlush(0);
        }
    }

    trackConsentMetric(metric: ConsentEventName, eventProperties: Record<string, TrackableType>, time: number) {
        const appConfig = Shopware.Store.get('context')?.app?.config;
        const payload: GatewayAnonymousEventRequest = {
            context: {
                sw_version: appConfig?.version ?? '',
            },
            events: [{ name: metric, timestamp: time, properties: eventProperties }],
        };
        void this.#sendJsonRequest(`${this.gateWayUrl}/v2/event/anonymous`, payload);
    }

    deleteUser(shopId: string, userId: string) {
        void this.#sendJsonRequest(`${this.gateWayUrl}/v1/delete-user`, {
            shop_id: shopId,
            user_id: userId,
        });
    }

    #scheduleFlush(delay: number): void {
        if (!this.#isInitialized || this.#isOptedOut) {
            return;
        }

        if (this.#flushTimer && delay > 0) {
            return;
        }

        this.#clearFlushTimer();
        this.#flushTimer = setTimeout(() => {
            this.#flushTimer = null;
            void this.#flushQueue();
        }, delay);
    }

    async #flushQueue(retryOnFailure = true): Promise<void> {
        if (this.#activeFlushPromise) {
            return this.#activeFlushPromise;
        }

        if (this.#queue.length === 0) {
            return;
        }

        const payload = this.#buildTrackedEventRequest();
        if (!payload) {
            return;
        }

        this.#activeFlushPromise = this.#runFlush(payload, retryOnFailure);

        return this.#activeFlushPromise;
    }

    async #runFlush(payload: GatewayEventRequest, retryOnFailure: boolean): Promise<void> {
        const batchSize = payload.events.length;

        try {
            const response = await fetch(`${this.gateWayUrl}/v2/event`, {
                ...JSON_POST_OPTIONS,
                body: JSON.stringify(payload),
            });

            if (response.ok || !this.#isRetryableStatus(response.status)) {
                this.#drainBatch(batchSize);
                return;
            }

            if (retryOnFailure && !this.#isOptedOut && this.#retryAttempt < MAX_RETRY_ATTEMPTS) {
                this.#scheduleRetry();
            }
        } catch {
            if (retryOnFailure && !this.#isOptedOut && this.#retryAttempt < MAX_RETRY_ATTEMPTS) {
                this.#scheduleRetry();
            }
        } finally {
            this.#activeFlushPromise = null;
        }
    }

    #drainBatch(batchSize: number): void {
        this.#queue.splice(0, batchSize);
        this.#retryAttempt = 0;

        if (this.#queue.length > 0) {
            this.#scheduleFlush(0);
        }
    }

    #scheduleRetry(): void {
        if (this.#retryTimer) {
            return;
        }

        const retryDelay = Math.min(RETRY_DELAY_MS * 2 ** this.#retryAttempt, MAX_RETRY_DELAY_MS);
        this.#retryAttempt += 1;
        this.#retryTimer = setTimeout(() => {
            this.#retryTimer = null;
            void this.#flushQueue();
        }, retryDelay);
    }

    #buildTrackedEventRequest(): GatewayEventRequest | null {
        if (!this.#userId) {
            return null;
        }

        const appConfig = Shopware.Store.get('context')?.app?.config;
        const shopId = appConfig.shopId ?? '';
        if (!shopId) {
            return null;
        }

        return {
            user: {
                shop_id: shopId,
                id: this.#userId,
            },
            context: this.#getTrackedContext(),
            events: this.#queue.slice(0, MAX_BATCH_SIZE),
        };
    }

    #getTrackedContext(): GatewayContext {
        const appConfig = Shopware.Store.get('context')?.app?.config;

        return {
            sw_version: appConfig?.version ?? '',
            sw_app_url: appConfig?.appUrl ?? '',
            sw_browser_url: window.location.origin,
            sw_user_agent: window.navigator.userAgent,
            sw_default_language: this.defaultLanguage,
            sw_default_currency: Shopware.Context.app.systemCurrencyISOCode ?? '',
            sw_screen_width: window.screen.width,
            sw_screen_height: window.screen.height,
            sw_screen_orientation: window.screen.orientation?.type?.split('-')[0] ?? '',
        };
    }

    #getRouteProperties(): EventPayload {
        const route = Shopware.Application.view?.router?.currentRoute;
        if (!route) {
            return {};
        }

        return {
            sw_page_name: route.value.name as TrackableType,
            sw_page_path: route.value.path,
            sw_page_full_path: route.value.fullPath,
        };
    }

    #isRetryableStatus(status: number): boolean {
        return status === 429 || status >= 500;
    }

    #getOrCreateDeviceId(): string {
        const storedDeviceId = this.#readStorage(window.localStorage, DEVICE_ID_STORAGE_KEY);
        if (storedDeviceId) {
            return storedDeviceId;
        }

        const deviceId = this.#createInsertId();
        this.#writeStorage(window.localStorage, DEVICE_ID_STORAGE_KEY, deviceId);

        return deviceId;
    }

    #readStorage(storage: Storage, key: string): string | null {
        try {
            return storage.getItem(key);
        } catch {
            return null;
        }
    }

    #createInsertId(): string {
        return globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random()}`;
    }

    #writeStorage(storage: Storage, key: string, value: string): void {
        try {
            storage.setItem(key, value);
        } catch {
            // ignore storage failures, the client can still send events without persistence
        }
    }

    #removeStorage(storage: Storage, key: string): void {
        try {
            storage.removeItem(key);
        } catch {
            // ignore storage failures during cleanup
        }
    }

    #clearFlushTimer(): void {
        if (this.#flushTimer) {
            clearTimeout(this.#flushTimer);
            this.#flushTimer = null;
        }
    }

    #clearRetryTimer(): void {
        if (this.#retryTimer) {
            clearTimeout(this.#retryTimer);
            this.#retryTimer = null;
        }
    }

    async #sendJsonRequest(url: string, json: unknown) {
        try {
            await fetch(url, { ...JSON_POST_OPTIONS, body: JSON.stringify(json) });
        } catch {
            // best-effort anonymous and privacy requests must not affect the admin runtime
        }
    }
}
