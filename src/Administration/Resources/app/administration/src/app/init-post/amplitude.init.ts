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

/**
 * @private
 */
export default function (): Promise<void> {
    const analyticsGatewayUrl = Shopware.Store.get('context').app.analyticsGatewayUrl;

    if (!analyticsGatewayUrl) {
        return Promise.resolve();
    }

    /*
     * register consent event handler
     */
    const anonymousGatewayClient = createAnonymousGatewayClient(analyticsGatewayUrl);
    const pushConsentEventToAmplitude = createConsentEventHandler(anonymousGatewayClient);

    // eslint-disable-next-line listeners/no-missing-remove-event-listener
    Shopware.Utils.EventBus.on('consent', pushConsentEventToAmplitude);

    const consentStore = useConsentStore();
    const isTelemetryConsentAccepted = computed((): boolean => {
        try {
            return consentStore.isAccepted('product_analytics');
        } catch {
            return false;
        }
    });

    /*
     * initialize product analytics
     */
    let amplitude: AmplitudeModule;

    watch(
        isTelemetryConsentAccepted,
        async (newValue: boolean) => {
            if (newValue) {
                if (!amplitude) {
                    amplitude = await initializeAmplitude(analyticsGatewayUrl);
                }

                amplitude.setOptOut(false);
                Shopware.Telemetry.identify();
            } else {
                if (!amplitude) {
                    return;
                }

                amplitude.setOptOut(true);
                deleteUser(amplitude, analyticsGatewayUrl);

                amplitude.flush();
                clearAmplitudeCookies();
            }
        },
        { immediate: true },
    );

    return Promise.resolve();
}
async function initializeAmplitude(analyticsGatewayUrl: string) {
    const amplitude = await import('@amplitude/analytics-browser');

    let defaultLanguage: string;
    try {
        defaultLanguage = await getDefaultLanguageName();
    } catch {
        defaultLanguage = 'N/A';
    }

    addDefaultShopwarePropertiesPlugin(amplitude, defaultLanguage);
    initTelemetryAmplitude(amplitude, analyticsGatewayUrl);

    registerTelemetryLogoutListener(amplitude);
    const eventHandlers = createTelemetryEventHandler(amplitude);

    // eslint-disable-next-line listeners/no-missing-remove-event-listener
    Shopware.Utils.EventBus.on('telemetry', eventHandlers);

    return amplitude;
}

function deleteUser(amplitude: AmplitudeModule, analyticsGatewayUrl: string) {
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
}
