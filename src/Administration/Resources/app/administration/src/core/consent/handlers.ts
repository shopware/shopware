/**
 * @sw-package framework
 */
import type { ConsentEventName, ConsentEvents, TrackableType } from './events';

type TrackClient = {
    track: (eventName: string, eventProperties: Record<string, TrackableType>, time: number) => void;
};

const ANONYMOUS_ALLOWED_PROPERTIES: { [Property in keyof ConsentEvents]: ReadonlyArray<keyof ConsentEvents[Property]> } = {
    consent_modal_viewed: ['option'],
    consent_decision_made: [
        'option',
        'decision',
        'time_spent_on_modal',
    ],
    consent_option_changed: [
        'option',
        'state',
    ],
    consent_legal_link_clicked: [
        'link_target',
        'source',
    ],
    consent_revoked: [
        'accepted_options',
        'declined_options',
    ],
};

function isConsentEventName(value: unknown): value is ConsentEventName {
    return typeof value === 'string' && value in ANONYMOUS_ALLOWED_PROPERTIES;
}

function isTrackableProperties(value: unknown): value is Record<string, TrackableType> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function sanitizeAnonymousProperties(
    eventName: ConsentEventName,
    properties: Record<string, TrackableType>,
): Record<string, TrackableType> {
    return ANONYMOUS_ALLOWED_PROPERTIES[eventName].reduce<Record<string, TrackableType>>((sanitized, key) => {
        const value = properties[key];

        if (value === undefined) {
            return sanitized;
        }

        if (key === 'time_spent_on_modal' && typeof value !== 'number') {
            return sanitized;
        }

        sanitized[key] = value;

        return sanitized;
    }, {});
}

/**
 * @private
 */
export default function createConsentEventHandler(anonymousAmplitude: TrackClient): (consentEvent: unknown) => void {
    return (consentEvent: unknown) => {
        if (typeof consentEvent !== 'object' || consentEvent === null) {
            return;
        }

        const { eventName, eventProperties } = consentEvent as {
            eventName: unknown;
            eventProperties: unknown;
        };
        const timestamp = 'timestamp' in consentEvent ? consentEvent.timestamp : undefined;

        if (!isConsentEventName(eventName) || !isTrackableProperties(eventProperties) || !(timestamp instanceof Date)) {
            return;
        }

        anonymousAmplitude.track(eventName, sanitizeAnonymousProperties(eventName, eventProperties), timestamp.getTime());
    };
}
