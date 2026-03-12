/**
 * @sw-package framework
 */
import createConsentEventHandler from 'src/core/consent/handlers';
import useConsentStore from 'src/core/consent/consent.store';
import {
    createPrivacyAmplitudeClient,
    initTelemetryAmplitude,
    registerTelemetryLogoutListener,
} from 'src/core/telemetry/amplitude/amplitude.browser-client';
import clearAmplitudeCookies from 'src/core/telemetry/amplitude/amplitude.browser-storage';
import createAnonymousGatewayClient from 'src/core/telemetry/amplitude/amplitude.gateway-client';
import {
    addDefaultShopwarePropertiesPlugin,
    getDefaultLanguageName,
} from 'src/core/telemetry/amplitude/amplitude.shopware-properties';
import createTelemetryEventHandler from 'src/core/telemetry/amplitude/amplitude.telemetry-handlers';
import type * as AmplitudeClient from '@amplitude/analytics-browser';
import { computed, watch } from 'vue';

type AmplitudeModule = typeof AmplitudeClient;

let stopTelemetryConsentWatch: (() => void) | null = null;
let pendingTelemetryActivationTimeout: number | null = null;

/**
 * @private
 */
export default async function (): Promise<void> {
    const analyticsGatewayUrl = Shopware.Store.get('context').app.analyticsGatewayUrl;

    if (!analyticsGatewayUrl) {
        return;
    }

    const consentStore = useConsentStore();
    const isTelemetryConsentAccepted = computed((): boolean => {
        try {
            return consentStore.isAccepted('product_analytics');
        } catch {
            return false;
        }
    });
    const anonymousGatewayClient = createAnonymousGatewayClient(analyticsGatewayUrl);
    const pushConsentEventToAmplitude = createConsentEventHandler(anonymousGatewayClient);
    let isTelemetryInitialized = false;
    let isTelemetryListenerRegistered = false;
    let amplitude: AmplitudeModule | null = null;
    let pushTelemetryEventToAmplitude: ReturnType<typeof createTelemetryEventHandler> | null = null;

    const clearPendingTelemetryActivation = (): void => {
        if (pendingTelemetryActivationTimeout === null) {
            return;
        }

        window.clearTimeout(pendingTelemetryActivationTimeout);
        pendingTelemetryActivationTimeout = null;
    };

    // eslint-disable-next-line listeners/no-missing-remove-event-listener
    Shopware.Utils.EventBus.on('consent', pushConsentEventToAmplitude);

    const ensureTelemetryInitialized = async (): Promise<void> => {
        if (isTelemetryInitialized) {
            return;
        }

        amplitude = await import('@amplitude/analytics-browser');
        pushTelemetryEventToAmplitude = createTelemetryEventHandler(amplitude);
        registerTelemetryLogoutListener(amplitude);

        let defaultLanguageName = '';

        try {
            defaultLanguageName = await getDefaultLanguageName();
        } catch {
            defaultLanguageName = 'N/A';
        }

        addDefaultShopwarePropertiesPlugin(amplitude, defaultLanguageName);
        initTelemetryAmplitude(amplitude, analyticsGatewayUrl);

        isTelemetryInitialized = true;
    };

    const enableTelemetryTracking = async (): Promise<void> => {
        if (isTelemetryListenerRegistered) {
            return;
        }

        await ensureTelemetryInitialized();

        if (
            isTelemetryListenerRegistered ||
            !isTelemetryConsentAccepted.value ||
            amplitude === null ||
            pushTelemetryEventToAmplitude === null
        ) {
            return;
        }

        amplitude.setOptOut(false);
        Shopware.Utils.EventBus.on('telemetry', pushTelemetryEventToAmplitude);
        isTelemetryListenerRegistered = true;
    };

    const disableTelemetryTracking = (): void => {
        if (!isTelemetryInitialized || amplitude === null) {
            return;
        }

        if (isTelemetryListenerRegistered && pushTelemetryEventToAmplitude !== null) {
            Shopware.Utils.EventBus.off('telemetry', pushTelemetryEventToAmplitude);
            isTelemetryListenerRegistered = false;
        }

        const shopId = Shopware.Store.get('context').app.config.shopId;
        const userId = Shopware.Store.get('session').currentUser?.id;

        if (typeof userId === 'string') {
            const privacyAmplitude = createPrivacyAmplitudeClient(amplitude, analyticsGatewayUrl);

            privacyAmplitude.track('delete_user', {
                shop_id: shopId,
                user_id: userId,
                amplitude_user_id: `${shopId}:${userId}`,
            });
            privacyAmplitude.flush();
        }
        amplitude.setOptOut(true);
        amplitude.flush();
        amplitude.reset();
        clearAmplitudeCookies();
    };

    const syncTelemetryTracking = async (consentAccepted: boolean): Promise<void> => {
        clearPendingTelemetryActivation();

        if (consentAccepted) {
            await enableTelemetryTracking();

            return;
        }

        disableTelemetryTracking();
    };

    await syncTelemetryTracking(isTelemetryConsentAccepted.value);
    clearPendingTelemetryActivation();
    stopTelemetryConsentWatch?.();
    stopTelemetryConsentWatch = watch(isTelemetryConsentAccepted, (consentAccepted) => {
        clearPendingTelemetryActivation();

        if (!consentAccepted) {
            void syncTelemetryTracking(false);

            return;
        }

        // delay runtime activation so the consent interaction itself is only tracked anonymously
        pendingTelemetryActivationTimeout = window.setTimeout(() => {
            pendingTelemetryActivationTimeout = null;
            void syncTelemetryTracking(true);
        }, 0);
    });
}
