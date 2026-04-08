/**
 * @sw-package framework
 */
import type { ConsentDTO } from './consent.store';

type TrackableType = string | string[] | number | boolean | null;

type ModalConsents = 'backend_data' | 'product_analytics';
type ConsentAction = ConsentDTO['status'];

type ConsentEvents = {
    consent_modal_viewed: {
        consents_shown: ModalConsents[];
    };
    consent_modal_decision: {
        backend_data?: {
            status: ConsentAction;
            changed: boolean;
        };
        product_analytics: {
            status: ConsentAction;
            changed: boolean;
        };
        time_spent_on_modal: number;
    };
    consent_status_change: ConsentDTO;
    consent_legal_link_clicked: {
        link_target: 'privacy_policy' | 'data_use_details';
        source: 'modal' | 'setting' | 'user';
    };
};

type ConsentEventName = keyof ConsentEvents;

class ConsentEvent<N extends ConsentEventName> {
    static #lastConsentEventTimestamp = 0;

    public readonly timestamp: Date;

    constructor(
        public readonly eventName: N,
        public readonly eventProperties: ConsentEvents[N],
        timestamp = new Date(Math.max(Date.now(), ConsentEvent.#lastConsentEventTimestamp + 1)),
    ) {
        this.timestamp = timestamp;
        ConsentEvent.#lastConsentEventTimestamp = this.timestamp.getTime();
    }
}

function dispatchConsentEvent<N extends ConsentEventName>(eventName: N, eventProperties: ConsentEvents[N]): void {
    Shopware.Utils.EventBus.emit('consent', new ConsentEvent(eventName, eventProperties));
}

function isConsentEvent(event: unknown): event is ConsentEvent<ConsentEventName> {
    return event instanceof ConsentEvent;
}

function isConsentEventType<N extends ConsentEventName>(
    event: ConsentEvent<ConsentEventName>,
    name: N,
): event is ConsentEvent<N> {
    return event.eventName === name;
}

/** @private */
export {
    ConsentEvent,
    dispatchConsentEvent,
    isConsentEvent,
    isConsentEventType,
    type ConsentEventName,
    type ConsentEvents,
    type TrackableType,
};
