/**
 * @sw-package framework
 */
import { computed, watch, type WatchHandle } from 'vue';
import useConsentStore from 'src/core/consent/consent.store';
import { GatewayClient } from 'src/core/telemetry/product-analytics/gateway-client';
import createConsentEventHandler from 'src/core/telemetry/product-analytics/consent-event-handler';
import createTelemetryEventHandler from 'src/core/telemetry/product-analytics/telemetry-event-handler';
import { EventBus } from 'shopware:utils';
import useContextStore from 'shopware:stores/context';
import useSessionStore from 'shopware:stores/session';

/**
 * @private
 */
export default async function (): Promise<WatchHandle | undefined> {
    const analyticsGatewayUrl = useContextStore().app.analyticsGatewayUrl;

    if (!analyticsGatewayUrl) {
        return;
    }

    /*
     * register consent event handler
     */

    const gatewayClient = new GatewayClient(analyticsGatewayUrl, await getDefaultLanguageName());

    const consentEventHandler = createConsentEventHandler(gatewayClient);

    // eslint-disable-next-line listeners/no-missing-remove-event-listener
    EventBus.on('consent', consentEventHandler);

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

    gatewayClient.setOptOut(true);

    const eventHandlers = createTelemetryEventHandler(gatewayClient);

    return watch(
        isTelemetryConsentAccepted,
        (newValue: boolean) => {
            if (newValue) {
                if (!gatewayClient.isInitialized) {
                    gatewayClient.init();
                }

                gatewayClient.setOptOut(false);
                EventBus.on('telemetry', eventHandlers);

                Shopware.Telemetry.identify();
            } else {
                if (!gatewayClient.isInitialized) {
                    return;
                }

                gatewayClient.setOptOut(true);
                EventBus.off('telemetry', eventHandlers);
                void gatewayClient.flushWithoutRetry().finally(() => {
                    deleteUser(gatewayClient);
                    gatewayClient.clearStorage();
                });
            }
        },
        { immediate: true },
    );
}

function deleteUser(client: GatewayClient) {
    const shopId = useContextStore().app.config.shopId;
    const userId = useSessionStore().currentUser?.id ?? null;

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
