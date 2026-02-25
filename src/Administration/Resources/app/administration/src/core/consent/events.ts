/**
 * @sw-package framework
 */

type TrackableType = string | string[] | number | boolean | null;

type ConsentEventName =
    | 'consent_modal_viewed'
    | 'consent_decision_made'
    | 'consent_option_changed'
    | 'consent_legal_link_clicked'
    | 'consent_revoked';

class ConsentEvent {
    public readonly timestamp: Date;

    constructor(
        public readonly eventName: ConsentEventName,
        public readonly eventProperties: Record<string, TrackableType>,
    ) {
        this.timestamp = new Date();
    }
}

function dispatchConsentEvent(eventName: ConsentEventName, eventProperties: Record<string, TrackableType>): void {
    if (!Shopware.Feature.isActive('PRODUCT_ANALYTICS')) {
        return;
    }

    Shopware.Utils.EventBus.emit('consent', new ConsentEvent(eventName, eventProperties));
}

/** @private */
export { ConsentEvent, dispatchConsentEvent, type ConsentEventName, type TrackableType };
