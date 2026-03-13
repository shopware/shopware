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
let amplitudeModulePromise: Promise<AmplitudeModule> | null = null;
let telemetryStateChangeToken = 0;

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
    let isTelemetryLogoutListenerRegistered = false;
    let isDefaultShopwarePropertiesPluginRegistered = false;
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

    const ensureAmplitudeModuleLoaded = async (): Promise<AmplitudeModule> => {
        if (amplitude !== null) {
            return amplitude;
        }

        amplitudeModulePromise ??= import('@amplitude/analytics-browser').catch((error: unknown) => {
            amplitudeModulePromise = null;

            throw error;
        });
        amplitude = await amplitudeModulePromise;
        pushTelemetryEventToAmplitude ??= createTelemetryEventHandler(amplitude);

        if (!isTelemetryLogoutListenerRegistered) {
            registerTelemetryLogoutListener(amplitude);
            isTelemetryLogoutListenerRegistered = true;
        }

        return amplitude;
    };

    const ensureTelemetryInitialized = async (stateChangeToken: number): Promise<void> => {
        if (isTelemetryInitialized) {
            return;
        }

        const loadedAmplitude = await ensureAmplitudeModuleLoaded();

        let defaultLanguageName = '';

        try {
            defaultLanguageName = await getDefaultLanguageName();
        } catch {
            defaultLanguageName = 'N/A';
        }

        if (stateChangeToken !== telemetryStateChangeToken || !isTelemetryConsentAccepted.value) {
            return;
        }

        if (!isDefaultShopwarePropertiesPluginRegistered) {
            addDefaultShopwarePropertiesPlugin(loadedAmplitude, defaultLanguageName);
            isDefaultShopwarePropertiesPluginRegistered = true;
        }

        initTelemetryAmplitude(loadedAmplitude, analyticsGatewayUrl);

        isTelemetryInitialized = true;
    };

    const enableTelemetryTracking = async (stateChangeToken: number): Promise<void> => {
        if (isTelemetryListenerRegistered) {
            return;
        }

        await ensureTelemetryInitialized(stateChangeToken);

        if (
            stateChangeToken !== telemetryStateChangeToken ||
            isTelemetryListenerRegistered ||
            !isTelemetryConsentAccepted.value ||
            !isTelemetryInitialized ||
            amplitude === null ||
            pushTelemetryEventToAmplitude === null
        ) {
            return;
        }

        amplitude.setOptOut(false);
        Shopware.Utils.EventBus.on('telemetry', pushTelemetryEventToAmplitude);
        isTelemetryListenerRegistered = true;
    };

    const disableTelemetryTracking = async (shouldDeleteUserData: boolean): Promise<void> => {
        if (isTelemetryListenerRegistered && pushTelemetryEventToAmplitude !== null) {
            Shopware.Utils.EventBus.off('telemetry', pushTelemetryEventToAmplitude);
            isTelemetryListenerRegistered = false;
        }

        const shopId = Shopware.Store.get('context').app.config.shopId;
        const userId = Shopware.Store.get('session').currentUser?.id;
        let loadedAmplitude = amplitude;

        if (shouldDeleteUserData && typeof userId === 'string') {
            loadedAmplitude = await ensureAmplitudeModuleLoaded();
            const privacyAmplitude = createPrivacyAmplitudeClient(loadedAmplitude, analyticsGatewayUrl);

            privacyAmplitude.track('delete_user', {
                shop_id: shopId,
                user_id: userId,
                amplitude_user_id: `${shopId}:${userId}`,
            });
            privacyAmplitude.flush();
        }

        if (isTelemetryInitialized && loadedAmplitude !== null) {
            loadedAmplitude.setOptOut(true);
            loadedAmplitude.flush();
            loadedAmplitude.reset();
        }

        clearAmplitudeCookies();
    };

    const syncTelemetryTracking = async (consentAccepted: boolean, shouldDeleteUserData = false): Promise<void> => {
        clearPendingTelemetryActivation();
        telemetryStateChangeToken += 1;
        const stateChangeToken = telemetryStateChangeToken;

        if (consentAccepted) {
            await enableTelemetryTracking(stateChangeToken);

            return;
        }

        await disableTelemetryTracking(shouldDeleteUserData);
    };

    await syncTelemetryTracking(isTelemetryConsentAccepted.value);
    clearPendingTelemetryActivation();
    stopTelemetryConsentWatch?.();
    stopTelemetryConsentWatch = watch(isTelemetryConsentAccepted, (consentAccepted, previousConsentAccepted) => {
        clearPendingTelemetryActivation();

        if (!consentAccepted) {
            void syncTelemetryTracking(false, previousConsentAccepted);

            return;
        }

        // delay runtime activation so the consent interaction itself is only tracked anonymously
        pendingTelemetryActivationTimeout = window.setTimeout(() => {
            pendingTelemetryActivationTimeout = null;
            void syncTelemetryTracking(true);
        }, 0);
    });
}
