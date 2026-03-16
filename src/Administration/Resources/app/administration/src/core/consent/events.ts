/**
 * @sw-package framework
 */
import type { ConsentDTO } from './consent.store';

type TrackableType = string | string[] | number | boolean | null;

type ModalConsents = 'backend_data' | 'product_analytics';
type ConsentAction = 'accepted' | 'revoked';

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
    consent_status_change: {
        consentName: string;
        status: ConsentAction;
        newValue: ConsentDTO;
    };
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
    if (!Shopware.Feature.isActive('PRODUCT_ANALYTICS')) {
        return;
    }

    Shopware.Utils.EventBus.emit('consent', new ConsentEvent(eventName, eventProperties));
}

/** @private */
export { ConsentEvent, dispatchConsentEvent, type ConsentEventName, type ConsentEvents, type TrackableType };
