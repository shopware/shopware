import createConsentEventHandler from './handlers';
import { ConsentEvent } from './events';

describe('src/core/consent/handlers.ts', () => {
    it('sends consent_modal_viewed event to amplitude', () => {
        const anonymousAmplitude = {
            track: jest.fn(),
        };

        const pushConsentEventToAmplitude = createConsentEventHandler(anonymousAmplitude);

        pushConsentEventToAmplitude(
            new ConsentEvent('consent_modal_viewed', {
                consents_shown: ['user_tracking'],
            }),
        );

        expect(anonymousAmplitude.track).toHaveBeenCalledWith(
            'consent_modal_viewed',
            {
                consents_shown: ['user_tracking'],
            },
            expect.any(Number),
        );
    });

    it('sends consent_modal_decision to amplitude', () => {
        const anonymousAmplitude = {
            track: jest.fn(),
        };

        const pushConsentEventToAmplitude = createConsentEventHandler(anonymousAmplitude);

        pushConsentEventToAmplitude(
            new ConsentEvent('consent_modal_decision', {
                backend_data: {
                    status: 'revoked',
                    changed: false,
                },
                product_analytics: {
                    status: 'accepted',
                    changed: true,
                },
                time_spent_on_modal: 30000,
            }),
        );

        expect(anonymousAmplitude.track).toHaveBeenCalledWith('consent_modal_decision', {
            backend_data_state: 'revoked',
            backend_data_changed: false,
            product_analytics_state: 'accepted',
            product_analytics_changed: true,
            time_spent_on_modal: 30000,
        });
    });

    it.each([
        ['backend_data'],
        ['product_analytics'],
    ])('sends consent_status_change to amplitude', (consentName) => {
        const anonymousAmplitude = {
            track: jest.fn(),
        };

        const pushConsentEventToAmplitude = createConsentEventHandler(anonymousAmplitude);

        pushConsentEventToAmplitude(
            new ConsentEvent('consent_status_change', {
                consentName,
                status: 'accepted',
            }),
        );

        expect(anonymousAmplitude.track).toHaveBeenCalledWith('consent_status_change', {
            consent: consentName,
            status: 'accepted',
        }, expect.any(Number));
    });

    it('does not send consent_status_change to amplitude for unknown status', () => {
        const anonymousAmplitude = {
            track: jest.fn(),
        };

        const pushConsentEventToAmplitude = createConsentEventHandler(anonymousAmplitude);

        pushConsentEventToAmplitude(
            new ConsentEvent('consent_status_change', {
                consentName: 'my_cool_app_consent',
                status: 'accepted',
            }),
        );

        expect(anonymousAmplitude.track).not.toHaveBeenCalled();
    });

    it('sends consent_legal_link_clicked to amplitude', () => {
        const anonymousAmplitude = {
            track: jest.fn(),
        };
        const pushConsentEventToAmplitude = createConsentEventHandler(anonymousAmplitude);

        pushConsentEventToAmplitude(
            new ConsentEvent('consent_legal_link_clicked', {
                link_target: 'privacy_policy',
                source: 'modal',
            }, expect.any(Number)),
        );

        expect(anonymousAmplitude.track).toHaveBeenCalledWith('consent_legal_link_clicked', {
            link_target: 'privacy_policy',
            source: 'modal',
        });
    });

    it('ignores fake/invalid consent events', () => {
        const anonymousAmplitude = {
            track: jest.fn(),
        };
        const pushConsentEventToAmplitude = createConsentEventHandler(anonymousAmplitude);

        pushConsentEventToAmplitude({
            eventName: 'consent_decision_made',
            payload: {
                option: 'user_tracking',
                decision: 'accepted',
                time_spent_on_modal: '4',
                evil: 'payload',
            },
        });

        expect(anonymousAmplitude.track).not.toHaveBeenCalled();
    });
});
