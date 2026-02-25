import { ConsentEvent } from 'src/core/consent/events';
import {
    trackConsentDecisionMade,
    trackConsentLegalLinkClicked,
    trackConsentModalViewed,
    trackConsentOptionChanged,
    trackConsentRevoked,
} from './tracking';

describe('core/consent/tracking', () => {
    let emitSpy;

    beforeEach(() => {
        jest.clearAllMocks();
        global.activeFeatureFlags = ['PRODUCT_ANALYTICS'];

        emitSpy = jest.spyOn(Shopware.Utils.EventBus, 'emit');
    });

    it('tracks modal viewed anonymously', () => {
        trackConsentModalViewed([
            'backend_data',
            'user_tracking',
        ]);

        expect(emitSpy).toHaveBeenCalledWith('consent', expect.any(ConsentEvent));
        expect(emitSpy.mock.calls[0][1]).toMatchObject({
            eventName: 'consent_modal_viewed',
            eventProperties: {
                option: [
                    'backend_data',
                    'user_tracking',
                ],
            },
        });
    });

    it('tracks decision and option changes anonymously', () => {
        trackConsentDecisionMade('user_tracking', 'accepted', 4);
        trackConsentOptionChanged('backend_data', 'disabled');

        expect(emitSpy).toHaveBeenNthCalledWith(1, 'consent', expect.any(ConsentEvent));
        expect(emitSpy).toHaveBeenNthCalledWith(2, 'consent', expect.any(ConsentEvent));

        expect(emitSpy.mock.calls[0][1]).toMatchObject({
            eventName: 'consent_decision_made',
            eventProperties: {
                option: 'user_tracking',
                decision: 'accepted',
                time_spent_on_modal: 4,
            },
        });

        expect(emitSpy.mock.calls[1][1]).toMatchObject({
            eventName: 'consent_option_changed',
            eventProperties: {
                option: 'backend_data',
                state: 'disabled',
            },
        });
    });

    it('sanitizes anonymous decision payload and drops invalid time_spent_on_modal values', () => {
        // @ts-expect-error runtime guard is validated by sanitizer
        trackConsentDecisionMade('user_tracking', 'accepted', '4');

        expect(emitSpy).toHaveBeenCalledWith('consent', expect.any(ConsentEvent));
        expect(emitSpy.mock.calls[0][1]).toMatchObject({
            eventName: 'consent_decision_made',
            eventProperties: {
                option: 'user_tracking',
                decision: 'accepted',
            },
        });
    });

    it('maps consent tracking inputs to sanitized anonymous payload outputs', () => {
        const cases = [
            {
                invoke: () =>
                    trackConsentModalViewed([
                        'backend_data',
                        'user_tracking',
                    ]),
                expected: {
                    event: 'consent_modal_viewed',
                    properties: {
                        option: [
                            'backend_data',
                            'user_tracking',
                        ],
                    },
                },
            },
            {
                // @ts-expect-error runtime guard is validated by sanitizer
                invoke: () => trackConsentDecisionMade('user_tracking', 'accepted', '4'),
                expected: {
                    event: 'consent_decision_made',
                    properties: {
                        option: 'user_tracking',
                        decision: 'accepted',
                    },
                },
            },
            {
                invoke: () => trackConsentLegalLinkClicked('privacy_policy', 'modal'),
                expected: {
                    event: 'consent_legal_link_clicked',
                    properties: {
                        link_target: 'privacy_policy',
                        source: 'modal',
                    },
                },
            },
        ];

        cases.forEach((testCase) => {
            emitSpy.mockClear();
            testCase.invoke();

            expect(emitSpy).toHaveBeenCalledWith('consent', expect.any(ConsentEvent));
            expect(emitSpy.mock.calls[0][1]).toMatchObject({
                eventName: testCase.expected.event,
                eventProperties: testCase.expected.properties,
            });
        });
    });

    it('tracks legal-link clicks and revocations', () => {
        trackConsentLegalLinkClicked('privacy_policy', 'setting');
        trackConsentRevoked(['backend_data'], ['user_tracking']);

        expect(emitSpy).toHaveBeenNthCalledWith(1, 'consent', expect.any(ConsentEvent));
        expect(emitSpy).toHaveBeenNthCalledWith(2, 'consent', expect.any(ConsentEvent));

        expect(emitSpy.mock.calls[0][1]).toMatchObject({
            eventName: 'consent_legal_link_clicked',
            eventProperties: {
                link_target: 'privacy_policy',
                source: 'setting',
            },
        });

        expect(emitSpy.mock.calls[1][1]).toMatchObject({
            eventName: 'consent_revoked',
            eventProperties: {
                accepted_options: ['backend_data'],
                declined_options: ['user_tracking'],
            },
        });
    });
});
