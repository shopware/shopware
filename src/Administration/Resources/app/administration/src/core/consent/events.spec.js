import { ConsentEvent, dispatchConsentEvent } from './events';

describe('src/core/consent/events.ts', () => {
    beforeEach(() => {
        jest.clearAllMocks();
    });

    it('creates a consent event with timestamp', () => {
        const consentEvent = new ConsentEvent('consent_modal_viewed', {
            option: ['user_tracking'],
        });

        expect(consentEvent.eventName).toBe('consent_modal_viewed');
        expect(consentEvent.eventProperties).toEqual({
            option: ['user_tracking'],
        });
        expect(consentEvent.timestamp).toBeInstanceOf(Date);
    });

    it('dispatches consent event when PRODUCT_ANALYTICS feature is active', () => {
        global.activeFeatureFlags = ['PRODUCT_ANALYTICS'];
        const emitSpy = jest.spyOn(Shopware.Utils.EventBus, 'emit');

        dispatchConsentEvent('consent_modal_viewed', {
            consents_shown: [
                'user_tracking',
                'backend_data',
            ],
        });

        expect(emitSpy).toHaveBeenCalledWith('consent', expect.any(ConsentEvent));
        expect(emitSpy.mock.calls[0][1]).toMatchObject({
            eventName: 'consent_modal_viewed',
            eventProperties: {
                consents_shown: [
                    'user_tracking',
                    'backend_data',
                ],
            },
        });
    });

    it('does not dispatch consent event when PRODUCT_ANALYTICS feature is inactive', () => {
        global.activeFeatureFlags = [];
        const emitSpy = jest.spyOn(Shopware.Utils.EventBus, 'emit');

        dispatchConsentEvent('consent_modal_viewed', {
            consents_shown: ['user_tracking'],
        });

        expect(emitSpy).not.toHaveBeenCalled();
    });
});
