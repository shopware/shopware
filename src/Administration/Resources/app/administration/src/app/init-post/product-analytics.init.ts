/**
 * @sw-package framework
 */
import { computed, watch, type WatchHandle } from 'vue';
import useConsentStore from 'src/core/consent/consent.store';
import { GatewayClient } from 'src/core/telemetry/product-analytics/gateway-client';
import { AmplitudeAdapter } from 'src/core/telemetry/product-analytics/amplitude-adapter';
import createConsentEventHandler from 'src/core/telemetry/product-analytics/consent-event-handler';
import createTelemetryEventHandler from 'src/core/telemetry/product-analytics/telemetry-event-handler';
import { registerAmplitudeLogoutListener } from 'src/core/telemetry/product-analytics/amplitude-logout-listener';

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

    const amplitudeAdapter = new AmplitudeAdapter(analyticsGatewayUrl, await getDefaultLanguageName());

    const gatewayClient = new GatewayClient(analyticsGatewayUrl, amplitudeAdapter);

    const consentEventHandler = createConsentEventHandler(gatewayClient);

    // eslint-disable-next-line listeners/no-missing-remove-event-listener
    Shopware.Utils.EventBus.on('consent', consentEventHandler);

    /*
     * initialize product analytics
     */
    const consentStore = useConsentStore();
    const isTelemetryConsentAccepted = computed((): boolean => {
        try {
            return consentStore.isAccepted('product_analytics');
        } catch {
            return false;
        }
    });

    amplitudeAdapter.setOptOut(true);
    registerAmplitudeLogoutListener(amplitudeAdapter, analyticsGatewayUrl);

    const eventHandlers = createTelemetryEventHandler(gatewayClient);

    return watch(
        isTelemetryConsentAccepted,
        (newValue: boolean) => {
            if (newValue) {
                if (!gatewayClient.isInitialized) {
                    gatewayClient.init();
                }

                amplitudeAdapter.setOptOut(false);
                Shopware.Utils.EventBus.on('telemetry', eventHandlers);

                Shopware.Telemetry.identify();
            } else {
                if (!gatewayClient.isInitialized) {
                    return;
                }

                amplitudeAdapter.setOptOut(true);
                Shopware.Utils.EventBus.off('telemetry', eventHandlers);

                deleteUser(gatewayClient);

                gatewayClient.flush();
                setTimeout(() => gatewayClient.clearStorage(), 0);
            }
        },
        { immediate: true },
    );
}

function deleteUser(client: GatewayClient) {
    const shopId = Shopware.Store.get('context').app.config.shopId;
    const userId = Shopware.Store.get('session').currentUser?.id ?? null;

    if (typeof shopId === 'string' && typeof userId === 'string') {
        client.deleteUser(shopId, userId);
    }
}

async function getDefaultLanguageName(): Promise<string> {
    const languageRepository = Shopware.Service('repositoryFactory').create('language');

    try {
        const defaultLanguage = await languageRepository.get(Shopware.Context.api.systemLanguageId!);

        return defaultLanguage!.name;
    } catch {
        return 'N/A';
    }
}
