/**
 * @sw-package data-services
 */
import { dispatchConsentEvent, type ConsentEventName, type TrackableType } from 'src/core/consent/events';

type ConsentOption = 'backend_data' | 'user_tracking';
type ConsentDecision = 'accepted' | 'revoked';
type ConsentState = 'enabled' | 'disabled';
type LegalLinkTarget = 'privacy_policy' | 'data_use_details';
function sendEvent(eventName: ConsentEventName, properties: Record<string, TrackableType>): void {
    dispatchConsentEvent(eventName, properties);
}

/**
 * @private
 */
export function trackConsentModalViewed(options: ConsentOption[]): void {
    sendEvent('consent_modal_viewed', {
        option: options,
    });
}

/**
 * @private
 */
export function trackConsentDecisionMade(option: ConsentOption, decision: ConsentDecision, timeSpentOnModal?: number): void {
    sendEvent('consent_decision_made', {
        option,
        decision,
        ...(typeof timeSpentOnModal === 'number' ? { time_spent_on_modal: timeSpentOnModal } : {}),
    });
}

/**
 * @private
 */
export function trackConsentOptionChanged(option: ConsentOption, state: ConsentState): void {
    sendEvent('consent_option_changed', {
        option,
        state,
    });
}

/**
 * @private
 */
export function trackConsentLegalLinkClicked(linkTarget: LegalLinkTarget, source: 'modal' | 'setting' | 'user'): void {
    sendEvent('consent_legal_link_clicked', {
        link_target: linkTarget,
        source,
    });
}

/**
 * @private
 */
export function trackConsentRevoked(acceptedOptions: ConsentOption[], declinedOptions: ConsentOption[]): void {
    sendEvent('consent_revoked', {
        accepted_options: acceptedOptions,
        declined_options: declinedOptions,
    });
}
