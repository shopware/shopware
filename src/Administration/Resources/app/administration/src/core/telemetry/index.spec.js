import { createMemoryHistory, createRouter } from 'vue-router';
import { Telemetry } from './index';
import { TelemetryEvent } from './types';

describe('src/core/telemetry/index.js', () => {
    beforeEach(() => {
        global.activeFeatureFlags = ['PRODUCT_ANALYTICS'];
        jest.useFakeTimers({
            now: new Date('2025-09-23'),
        });

        document.body = document.createElement('body');
    });

    describe('manual tracking', () => {
        it('should track a custom event', () => {
            let expectedEvent;
            const telemetry = new Telemetry([]);
            const listener = jest.fn((event) => {
                expectedEvent = event;
            });

            telemetry.addListener(listener);

            telemetry.track({ test: 'test-action' });

            expect(listener).toHaveBeenCalled();
            expect(expectedEvent).toBeInstanceOf(TelemetryEvent);
            expect(expectedEvent.detail).toEqual({
                eventType: 'programmatic',
                eventData: {
                    test: 'test-action',
                },
                timestamp: new Date('2025-09-23'),
            });
        });

        it('should track identify events', () => {
            let expectedEvent;
            const telemetry = new Telemetry([]);
            const listener = jest.fn((event) => {
                expectedEvent = event;
            });

            telemetry.addListener(listener);

            telemetry.identify('user-id', 'device-id', 'en-US', ['product:read']);

            expect(listener).toHaveBeenCalled();
            expect(expectedEvent).toBeInstanceOf(TelemetryEvent);
            expect(expectedEvent.detail).toEqual({
                eventType: 'identify',
                eventData: {
                    userId: 'user-id',
                    deviceId: 'device-id',
                    locale: 'en-US',
                    permissions: ['product:read'],
                },
                timestamp: new Date('2025-09-23'),
            });
        });
    });

    describe('page changes', () => {
        it('emits page change event after a router push', async () => {
            let expectedEvent;
            const telemetry = new Telemetry({ queries: [] });
            const listener = jest.fn((event) => {
                expectedEvent = event;
            });
            telemetry.addListener(listener);

            const router = createRouter({
                routes: [
                    {
                        path: '/',
                        name: 'home',
                        component: { template: '<div>Home</div>' },
                    },
                    {
                        path: '/test',
                        name: 'test',
                        component: { template: '<div>Test</div>' },
                    },
                ],
                history: createMemoryHistory(),
            });
            Shopware.Application.view.router = router;
            await await router.push({ name: 'home' });

            Shopware.Application.viewInitialized = new Promise((resolve) => {
                resolve();
            });

            telemetry.initialize();
            await Shopware.Application.viewInitialized;

            await router.push({ name: 'test' });

            expect(listener).toHaveBeenCalled();
            expect(expectedEvent).toBeInstanceOf(TelemetryEvent);
            expect(expectedEvent.detail).toEqual({
                eventType: 'page_change',
                eventData: {
                    from: expect.objectContaining({
                        name: 'home',
                        path: '/',
                    }),
                    to: expect.objectContaining({
                        name: 'test',
                        path: '/test',
                    }),
                },
                timestamp: new Date('2025-09-23'),
            });
        });
    });

    describe('auto tracked elements', () => {
        it('registers click listener on elements', async () => {
            const telemetry = new Telemetry({
                queries: [
                    () =>
                        document ? [document.getElementById('tested-element')] : [],
                ],
            });
            const listener = jest.fn();

            telemetry.initialize();
            telemetry.addListener(listener);

            const element = document.createElement('div');
            element.setAttribute('id', 'tested-element');
            document.body.appendChild(element);

            await flushPromises();

            element.click();
            expect(listener).toHaveBeenCalled();
        });

        it('does not register listener twice', async () => {
            const telemetry = new Telemetry({
                queries: [
                    () =>
                        document ? [document.getElementById('tested-element')] : [],
                    () =>
                        document ? [document.getElementById('tested-element')] : [],
                ],
            });
            const listener = jest.fn();

            telemetry.initialize();
            telemetry.addListener(listener);

            const element = document.createElement('div');
            element.setAttribute('id', 'tested-element');
            document.body.appendChild(element);

            await flushPromises();

            element.click();
            expect(listener).toHaveBeenCalled();
            expect(listener).toHaveBeenCalledTimes(1);
        });

        it('emit user_interaction on clickable elements', async () => {
            const telemetry = new Telemetry({
                queries: [
                    () =>
                        document ? [document.getElementById('tested-element')] : [],
                ],
            });
            const listener = jest.fn();

            telemetry.initialize();
            telemetry.addListener(listener);

            const element = document.createElement('div');
            element.setAttribute('id', 'tested-element');
            document.body.appendChild(element);

            await flushPromises();

            element.click();
            expect(listener).toHaveBeenCalled();

            const telemetryEvent = listener.mock.calls[0][0];

            expect(telemetryEvent).toBeInstanceOf(TelemetryEvent);
            expect(telemetryEvent.detail).toEqual({
                eventType: 'user_interaction',
                eventData: {
                    target: element,
                    originalEvent: expect.anything(),
                },
                timestamp: new Date('2025-09-23'),
            });
        });

        it('overrides the event listened to if data-analytics-event is set', async () => {
            const telemetry = new Telemetry({
                queries: [
                    () =>
                        document ? [document.getElementById('tested-element')] : [],
                ],
            });
            const listener = jest.fn();

            telemetry.initialize();
            telemetry.addListener(listener);

            const element = document.createElement('div');
            element.setAttribute('id', 'tested-element');
            element.setAttribute('data-analytics-event', 'test-event');
            document.body.appendChild(element);

            await flushPromises();

            element.click();
            expect(listener).not.toHaveBeenCalled();

            element.dispatchEvent(new Event('test-event'));
            expect(listener).toHaveBeenCalled();
        });
    });
});
