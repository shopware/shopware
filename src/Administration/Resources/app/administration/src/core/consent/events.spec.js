import { ConsentEvent, dispatchConsentEvent, isConsentEvent, isConsentEventType } from './events';

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
            option: ['product_analytics'],
        });

        expect(consentEvent.eventName).toBe('consent_modal_viewed');
        expect(consentEvent.eventProperties).toEqual({
            option: ['product_analytics'],
        });
        expect(consentEvent.timestamp).toBeInstanceOf(Date);
    });

    it('creates consent events with strictly increasing timestamps', () => {
        jest.setSystemTime(new Date('2026-01-01T10:00:00.000Z'));

        const firstEvent = new ConsentEvent('consent_modal_viewed', {
            option: ['product_analytics'],
        });
        const secondEvent = new ConsentEvent('consent_option_changed', {
            option: 'product_analytics',
            state: 'enabled',
        });

        expect(secondEvent.timestamp.getTime()).toBe(firstEvent.timestamp.getTime() + 1);
    });

    it('dispatches consent events', () => {
        const emitSpy = jest.spyOn(Shopware.Utils.EventBus, 'emit');

        dispatchConsentEvent('consent_modal_viewed', {
            consents_shown: [
                'product_analytics',
                'backend_data',
            ],
        });

        expect(emitSpy).toHaveBeenCalledWith('consent', expect.any(ConsentEvent));
        expect(emitSpy.mock.calls[0][1]).toMatchObject({
            eventName: 'consent_modal_viewed',
            eventProperties: {
                consents_shown: [
                    'product_analytics',
                    'backend_data',
                ],
            },
        });
    });

    describe('isConsentEvent', () => {
        it('checks the prototype', () => {
            expect(isConsentEvent(new ConsentEvent('consent_modal_viewed', { consents_shown: ['product_analytics'] }))).toBe(
                true,
            );

            expect(
                isConsentEvent({
                    eventName: 'consent_modal_viewed',
                    eventProperties: { consents_shown: ['product_analytics'] },
                }),
            ).toBe(false);
        });

        it('compares the eventName property', () => {
            const event = new ConsentEvent('consent_modal_viewed', {
                consents_shown: ['product_analytics'],
            });

            expect(isConsentEventType(event, 'consent_modal_viewed')).toBe(true);
            expect(isConsentEventType(event, 'consent_modal_decision')).toBe(false);
        });
    });
});
