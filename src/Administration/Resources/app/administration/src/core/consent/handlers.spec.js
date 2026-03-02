import createConsentEventHandler from './handlers';
import { ConsentEvent } from './events';

describe('src/core/consent/handlers.ts', () => {
    it('routes consent events to anonymous amplitude', () => {
        const anonymousAmplitude = {
            track: jest.fn(),
        };

        const pushConsentEventToAmplitude = createConsentEventHandler(anonymousAmplitude);

        pushConsentEventToAmplitude(
            new ConsentEvent('consent_modal_viewed', {
                option: ['user_tracking'],
            }),
        );

        expect(anonymousAmplitude.track).toHaveBeenCalledWith('consent_modal_viewed', {
            option: ['user_tracking'],
        });
    });

    it('sanitizes consent event payload before forwarding to anonymous amplitude', () => {
        const anonymousAmplitude = {
            track: jest.fn(),
        };
        const pushConsentEventToAmplitude = createConsentEventHandler(anonymousAmplitude);

        pushConsentEventToAmplitude(
            new ConsentEvent('consent_decision_made', {
                option: 'user_tracking',
                decision: 'accepted',
                // invalid type and unknown field must be filtered out
                time_spent_on_modal: '4',
                evil: 'payload',
            }),
        );

        expect(anonymousAmplitude.track).toHaveBeenCalledWith('consent_decision_made', {
            option: 'user_tracking',
            decision: 'accepted',
        });
    });

    it('ignores fake/invalid consent events', () => {
        const anonymousAmplitude = {
            track: jest.fn(),
        };
        const pushConsentEventToAmplitude = createConsentEventHandler(anonymousAmplitude);

        pushConsentEventToAmplitude({
            message: "Haha, I'm a fake event.",
        });

        expect(anonymousAmplitude.track).not.toHaveBeenCalled();
    });
});
