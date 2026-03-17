import { ConsentEvent, dispatchConsentEvent } from './events';

describe('src/core/consent/events.ts', () => {
    beforeEach(() => {
        jest.clearAllMocks();
        jest.useFakeTimers();
    });

    afterEach(() => {
        jest.useRealTimers();
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

    it('creates consent events with strictly increasing timestamps', () => {
        jest.setSystemTime(new Date('2026-01-01T10:00:00.000Z'));

        const firstEvent = new ConsentEvent('consent_modal_viewed', {
            option: ['user_tracking'],
        });
        const secondEvent = new ConsentEvent('consent_option_changed', {
            option: 'user_tracking',
            state: 'enabled',
        });

        expect(secondEvent.timestamp.getTime()).toBe(firstEvent.timestamp.getTime() + 1);
    });

    it('dispatches consent event when PRODUCT_ANALYTICS feature is active', () => {
        global.activeFeatureFlags = ['PRODUCT_ANALYTICS'];
        const emitSpy = jest.spyOn(Shopware.Utils.EventBus, 'emit');

        dispatchConsentEvent('consent_option_changed', {
            option: 'backend_data',
            state: 'enabled',
        });

        expect(emitSpy).toHaveBeenCalledWith('consent', expect.any(ConsentEvent));
        expect(emitSpy.mock.calls[0][1]).toMatchObject({
            eventName: 'consent_option_changed',
            eventProperties: {
                option: 'backend_data',
                state: 'enabled',
            },
        });
    });

    it('does not dispatch consent event when PRODUCT_ANALYTICS feature is inactive', () => {
        global.activeFeatureFlags = [];
        const emitSpy = jest.spyOn(Shopware.Utils.EventBus, 'emit');

        dispatchConsentEvent('consent_modal_viewed', {
            option: ['user_tracking'],
        });

        expect(emitSpy).not.toHaveBeenCalled();
    });
});
