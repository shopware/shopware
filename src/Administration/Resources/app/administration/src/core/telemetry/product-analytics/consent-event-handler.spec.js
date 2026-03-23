import { ConsentEvent } from 'src/core/consent/events';
import createConsentEventHandler from './consent-event-handler';

describe('src/core/telemetry/product-analytics/consent-event-handlers.ts', () => {
    const gatewayClient = {
        trackConsentMetric: jest.fn(),
    };
    const handle = createConsentEventHandler(gatewayClient);

    beforeEach(() => {
        gatewayClient.trackConsentMetric.mockClear();
    });

    it('sends consent_modal_viewed event to amplitude', () => {
        handle(
            new ConsentEvent('consent_modal_viewed', {
                consents_shown: ['product_analytics'],
            }),
        );

        expect(gatewayClient.trackConsentMetric).toHaveBeenCalledWith(
            'consent_modal_viewed',
            {
                consents_shown: ['product_analytics'],
            },
            expect.any(Number),
        );
    });

    it('sends consent_modal_decision to amplitude', () => {
        handle(
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

        expect(gatewayClient.trackConsentMetric).toHaveBeenCalledWith(
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
        handle(
            new ConsentEvent('consent_status_change', {
                name: consentName,
                status: 'accepted',
            }),
        );

        expect(gatewayClient.trackConsentMetric).toHaveBeenCalledWith(
            'consent_status_change',
            {
                consent: consentName,
                status: 'accepted',
            },
            expect.any(Number),
        );
    });

    it('does not send consent_status_change to amplitude for unknown status', () => {
        handle(
            new ConsentEvent('consent_status_change', {
                consentName: 'my_cool_app_consent',
                action: 'accepted',
            }),
        );

        expect(gatewayClient.trackConsentMetric).not.toHaveBeenCalled();
    });

    it('sends consent_legal_link_clicked to amplitude', () => {
        handle(
            new ConsentEvent('consent_legal_link_clicked', {
                link_target: 'privacy_policy',
                source: 'modal',
            }),
        );

        expect(gatewayClient.trackConsentMetric).toHaveBeenCalledWith(
            'consent_legal_link_clicked',
            {
                link_target: 'privacy_policy',
                source: 'modal',
            },
            expect.any(Number),
        );
    });

    it('ignores fake/invalid consent events', () => {
        handle({
            eventName: 'haha I am a fake event',
            payload: {
                option: 'product_analytics',
                decision: 'accepted',
                time_spent_on_modal: '4',
                evil: 'payload',
            },
        });

        expect(gatewayClient.trackConsentMetric).not.toHaveBeenCalled();
    });
});
