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
import * as amplitude from '@amplitude/analytics-browser';
import { computed, watch, type WatchHandle } from 'vue';

type AmplitudeModule = typeof amplitude;

/**
 * @private
 */
export default async function (): Promise<WatchHandle | undefined> {
    const analyticsGatewayUrl = Shopware.Store.get('context').app.analyticsGatewayUrl;

    if (!analyticsGatewayUrl) {
        return;
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
    let isAmplitudeInitialized = false;
    amplitude.setOptOut(true);
    addDefaultShopwarePropertiesPlugin(amplitude, await getDefaultLanguageName());
    registerTelemetryLogoutListener(amplitude, analyticsGatewayUrl);
    const eventHandlers = createTelemetryEventHandler(amplitude);

    return watch(
        isTelemetryConsentAccepted,
        (newValue: boolean) => {
            if (newValue) {
                if (!isAmplitudeInitialized) {
                    initTelemetryAmplitude(amplitude, analyticsGatewayUrl);
                    isAmplitudeInitialized = true;
                }

                amplitude.setOptOut(false);
                Shopware.Utils.EventBus.on('telemetry', eventHandlers);

                Shopware.Telemetry.identify();
            } else {
                if (!isAmplitudeInitialized) {
                    return;
                }

                amplitude.setOptOut(true);
                Shopware.Utils.EventBus.off('telemetry', eventHandlers);

                deleteUser(amplitude, analyticsGatewayUrl);

                amplitude.flush();
                setTimeout(() => clearAmplitudeCookies(), 0);
            }
        },
        { immediate: true },
    );
}

function deleteUser(amplitudeModule: AmplitudeModule, analyticsGatewayUrl: string) {
    const shopId = Shopware.Store.get('context').app.config.shopId;
    const userId = Shopware.Store.get('session').currentUser?.id;

    if (typeof userId === 'string') {
        const privacyAmplitude = createPrivacyAmplitudeClient(amplitudeModule, analyticsGatewayUrl);

        privacyAmplitude.track('delete_user', {
            shop_id: shopId,
            user_id: userId,
            amplitude_user_id: `${shopId}:${userId}`,
        });
        privacyAmplitude.flush();
    }
}
