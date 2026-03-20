/**
 * @sw-package framework
 */
import { isConsentEvent, isConsentEventType, type TrackableType } from 'src/core/consent/events';
import type { GatewayClient } from './gateway-client';

type EventPayload = Record<string, TrackableType>;

/**
 * @private
 */
export default function createConsentEventHandler(client: GatewayClient): (consentEvent: unknown) => void {
    return (consentEvent: unknown) => {
        if (!isConsentEvent(consentEvent)) {
            return;
        }

        if (isConsentEventType(consentEvent, 'consent_modal_viewed')) {
            client.trackConsentMetric(
                consentEvent.eventName,
                {
                    consents_shown: consentEvent.eventProperties.consents_shown,
                },
                consentEvent.timestamp.getTime(),
            );

            return;
        }

        if (isConsentEventType(consentEvent, 'consent_modal_decision')) {
            const eventProps: EventPayload = {
                product_analytics_state: consentEvent.eventProperties.product_analytics.status,
                product_analytics_changed: consentEvent.eventProperties.product_analytics.changed,
                time_spent_on_modal: consentEvent.eventProperties.time_spent_on_modal,
            };

            if (consentEvent.eventProperties.backend_data) {
                eventProps.backend_data_state = consentEvent.eventProperties.backend_data.status;
                eventProps.backend_data_changed = consentEvent.eventProperties.backend_data.changed;
            }

            client.trackConsentMetric(consentEvent.eventName, eventProps, consentEvent.timestamp.getTime());
            return;
        }

        if (isConsentEventType(consentEvent, 'consent_status_change')) {
            if (
                consentEvent.eventProperties.name !== 'backend_data' &&
                consentEvent.eventProperties.name !== 'product_analytics'
            ) {
                return;
            }

            client.trackConsentMetric(
                consentEvent.eventName,
                {
                    consent: consentEvent.eventProperties.name,
                    status: consentEvent.eventProperties.status,
                },
                consentEvent.timestamp.getTime(),
            );

            return;
        }

        if (isConsentEventType(consentEvent, 'consent_legal_link_clicked')) {
            client.trackConsentMetric(
                consentEvent.eventName,
                {
                    link_target: consentEvent.eventProperties.link_target,
                    source: consentEvent.eventProperties.source,
                },
                consentEvent.timestamp.getTime(),
            );
        }
    };
}
