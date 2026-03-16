/**
 * @sw-package framework
 */
import type { ConsentEventName, TrackableType } from './events';
import { ConsentEvent } from './events';

type TrackClient = {
    track: (eventName: string, eventProperties: Record<string, TrackableType>, time: number) => void;
};

type EventPayload = Record<string, TrackableType>;

function isConsentEvent(event: unknown): event is ConsentEvent<ConsentEventName> {
    return event instanceof ConsentEvent;
}

function isConsentEventType<N extends ConsentEventName>(
    event: ConsentEvent<ConsentEventName>,
    name: N,
): event is ConsentEvent<N> {
    return event.eventName === name;
}

/**
 * @private
 */
export default function createConsentEventHandler(anonymousAmplitude: TrackClient): (consentEvent: unknown) => void {
    return (consentEvent: unknown) => {
        if (!isConsentEvent(consentEvent)) {
            return;
        }

        if (isConsentEventType(consentEvent, 'consent_modal_viewed')) {
            anonymousAmplitude.track(consentEvent.eventName, {
                consents_shown: consentEvent.eventProperties.consents_shown,
            }, consentEvent.timestamp.getTime());

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

            anonymousAmplitude.track(consentEvent.eventName, eventProps, consentEvent.timestamp.getTime());
            return;
        }

        if (isConsentEventType(consentEvent, 'consent_status_change')) {
            if (
                consentEvent.eventProperties.consentName !== 'backend_data' &&
                consentEvent.eventProperties.consentName !== 'product_analytics'
            ) {
                return;
            }

            anonymousAmplitude.track(consentEvent.eventName, {
                consent: consentEvent.eventProperties.consentName,
                status: consentEvent.eventProperties.status,
            }, consentEvent.timestamp.getTime());

            return;
        }

        if (isConsentEventType(consentEvent, 'consent_legal_link_clicked')) {
            anonymousAmplitude.track(consentEvent.eventName, {
                link_target: consentEvent.eventProperties.link_target,
                source: consentEvent.eventProperties.source,
            }, consentEvent.timestamp.getTime());
        }
    };
}
