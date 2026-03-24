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
                consents_shown: ['product_analytics'],
            }),
        );

        expect(anonymousAmplitude.track).toHaveBeenCalledWith(
            'consent_modal_viewed',
            {
                consents_shown: ['product_analytics'],
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

        expect(anonymousAmplitude.track).toHaveBeenCalledWith(
            'consent_modal_decision',
            {
                backend_data_state: 'revoked',
                backend_data_changed: false,
                product_analytics_state: 'accepted',
                product_analytics_changed: true,
                time_spent_on_modal: 30000,
            },
            expect.any(Number),
        );
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
                name: consentName,
                status: 'accepted',
            }),
        );

        expect(anonymousAmplitude.track).toHaveBeenCalledWith(
            'consent_status_change',
            {
                consent: consentName,
                status: 'accepted',
            },
            expect.any(Number),
        );
    });

    it('does not send consent_status_change to amplitude for unknown status', () => {
        const anonymousAmplitude = {
            track: jest.fn(),
        };

        const pushConsentEventToAmplitude = createConsentEventHandler(anonymousAmplitude);

        pushConsentEventToAmplitude(
            new ConsentEvent('consent_status_change', {
                consentName: 'my_cool_app_consent',
                action: 'accepted',
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
            }),
        );

        expect(anonymousAmplitude.track).toHaveBeenCalledWith(
            'consent_legal_link_clicked',
            {
                link_target: 'privacy_policy',
                source: 'modal',
            },
            expect.any(Number),
        );
    });

    it('ignores fake/invalid consent events', () => {
        const anonymousAmplitude = {
            track: jest.fn(),
        };
        const pushConsentEventToAmplitude = createConsentEventHandler(anonymousAmplitude);

        pushConsentEventToAmplitude({
            eventName: 'haha I am a fake event',
            payload: {
                option: 'product_analytics',
                decision: 'accepted',
                time_spent_on_modal: '4',
                evil: 'payload',
            },
        });

        expect(anonymousAmplitude.track).not.toHaveBeenCalled();
    });
});
