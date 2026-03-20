/**
 * @sw-package framework
 */
import * as amplitude from '@amplitude/analytics-browser';
import { computed, watch, type WatchHandle } from 'vue';
import useConsentStore from 'src/core/consent/consent.store';
import { GatewayClient } from 'src/core/telemetry/product-analytics/gateway-client';
import createConsentEventHandler from 'src/core/telemetry/product-analytics/consent-event-handler';
import {
    initTelemetryAmplitude,
    registerTelemetryLogoutListener,
} from 'src/core/telemetry/amplitude/amplitude.browser-client';
import clearAmplitudeCookies from 'src/core/telemetry/amplitude/amplitude.browser-storage';
import {
    addDefaultShopwarePropertiesPlugin,
    getDefaultLanguageName,
} from 'src/core/telemetry/amplitude/amplitude.shopware-properties';
import createTelemetryEventHandler from 'src/core/telemetry/amplitude/amplitude.telemetry-handlers';

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
    const gatewayClient = new GatewayClient(analyticsGatewayUrl);
    const pushConsentEventToAmplitude = createConsentEventHandler(gatewayClient);

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

                deleteUser(gatewayClient);

                amplitude.flush();
                setTimeout(() => clearAmplitudeCookies(), 0);
            }
        },
        { immediate: true },
    );
}

function deleteUser(client: GatewayClient) {
    const shopId = Shopware.Store.get('context').app.config.shopId;
    const userId = Shopware.Store.get('session').currentUser?.id ?? null;

    if (userId !== null && shopId !== null) {
        client.deleteUser(shopId, userId);
    }
}
