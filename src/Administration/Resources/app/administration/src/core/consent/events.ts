/**
 * @sw-package framework
 */

type TrackableType = string | string[] | number | boolean | null;

type ConsentOption = 'backend_data' | 'user_tracking';
type ConsentEvents = {
    consent_modal_viewed: {
        option: ConsentOption[];
    };
    consent_decision_made: {
        option: ConsentOption;
        decision: 'accepted' | 'revoked';
        time_spent_on_modal: number;
    };
    consent_option_changed: {
        option: ConsentOption;
        state: 'enabled' | 'disabled';
    };
    consent_legal_link_clicked: {
        link_target: 'privacy_policy' | 'data_use_details';
        source: 'modal' | 'setting' | 'user';
    };
    consent_revoked: {
        accepted_options: ConsentOption[];
        declined_options: ConsentOption[];
    };
};

type ConsentEventName = keyof ConsentEvents;

class ConsentEvent {
    public readonly timestamp: Date;

    constructor(
        public readonly eventName: ConsentEventName,
        public readonly eventProperties: Record<string, TrackableType>,
    ) {
        this.timestamp = new Date();
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
