import { createMemoryHistory, createRouter } from 'vue-router';
import { Telemetry } from './index';
import { TelemetryEvent } from './types';
import TaggedButtons from './ElementQueries/tagged-buttons';

describe('src/core/telemetry/index.js', () => {
    beforeEach(() => {
        global.activeFeatureFlags = ['PRODUCT_ANALYTICS'];
        jest.useFakeTimers({
            now: new Date('2025-09-23'),
        });

        document.body = document.createElement('body');
    });

    it('throws exception if initialized twice', () => {
        const telemetry = new Telemetry({ queries: [] });

        telemetry.initialize();

        expect(() => {
            telemetry.initialize();
        }).toThrow('Telemetry is already initialized');
    });

    describe('manual tracking', () => {
        it('should track a custom event', () => {
            const telemetry = new Telemetry([]);
            const eventBusSpy = jest.spyOn(Shopware.Utils.EventBus, 'emit');

            telemetry.track({ test: 'test-action' });

            expect(eventBusSpy).toHaveBeenCalled();
            expect(eventBusSpy).toHaveBeenCalledWith(
                'telemetry',
                new TelemetryEvent('programmatic', { test: 'test-action' }),
            );
        });

        it('should track identify events', () => {
            const telemetry = new Telemetry([]);
            const eventBusSpy = jest.spyOn(Shopware.Utils.EventBus, 'emit');

            telemetry.identify('user-id', 'device-id', 'en-US', ['product:read']);

            expect(eventBusSpy).toHaveBeenCalled();
            expect(eventBusSpy).toHaveBeenCalledWith(
                'telemetry',
                new TelemetryEvent('identify', {
                    userId: 'user-id',
                    deviceId: 'device-id',
                    locale: 'en-US',
                    permissions: ['product:read'],
                }),
            );
        });
    });

    describe('page changes', () => {
        it('emits page change event after a router push', async () => {
            const telemetry = new Telemetry({ queries: [] });
            const eventBusSpy = jest.spyOn(Shopware.Utils.EventBus, 'emit');

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
            await router.push({ name: 'home' });

            Shopware.Application.viewInitialized = new Promise((resolve) => {
                resolve();
            });

            telemetry.initialize();
            await Shopware.Application.viewInitialized;

            await router.push({ name: 'test' });

            expect(eventBusSpy).toHaveBeenCalled();
            expect(eventBusSpy).toHaveBeenCalledWith(
                'telemetry',
                new TelemetryEvent('page_change', {
                    from: router.resolve('/'),
                    to: router.resolve('/test'),
                }),
            );
        });
    });

    describe('auto tracked elements', () => {
        it('emit user_interaction on clickable elements', async () => {
            const telemetry = new Telemetry({
                queries: [
                    () =>
                        document ? [document.getElementById('tested-element')] : [],
                ],
            });
            const eventBusSpy = jest.spyOn(Shopware.Utils.EventBus, 'emit');

            telemetry.initialize();

            const element = document.createElement('div');
            element.setAttribute('id', 'tested-element');
            document.body.appendChild(element);

            await flushPromises();

            element.click();

            expect(eventBusSpy).toHaveBeenCalled();
            expect(eventBusSpy).toHaveBeenCalledWith(
                'telemetry',
                new TelemetryEvent('user_interaction', {
                    target: element,
                    originalEvent: expect.anything(),
                }),
            );
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
            const eventBusSpy = jest.spyOn(Shopware.Utils.EventBus, 'emit');

            telemetry.initialize();

            const element = document.createElement('div');
            element.setAttribute('id', 'tested-element');
            document.body.appendChild(element);

            await flushPromises();

            element.click();
            expect(eventBusSpy).toHaveBeenCalled();
            expect(eventBusSpy).toHaveBeenCalledTimes(1);
        });

        it('overrides the event listened to if data-analytics-event is set', async () => {
            const telemetry = new Telemetry({
                queries: [
                    () =>
                        document ? [document.getElementById('tested-element')] : [],
                ],
            });
            const eventBusSpy = jest.spyOn(Shopware.Utils.EventBus, 'emit');

            telemetry.initialize();

            const element = document.createElement('div');
            element.setAttribute('id', 'tested-element');
            element.setAttribute('data-analytics-event', 'test-event');
            document.body.appendChild(element);

            await flushPromises();

            element.click();
            expect(eventBusSpy).not.toHaveBeenCalled();

            element.dispatchEvent(new Event('test-event'));
            expect(eventBusSpy).toHaveBeenCalled();
        });
    });

    describe('debug', () => {
        it('registers a listener if debug is turned on', async () => {
            const telemetry = new Telemetry({ queries: [] }, true);
            telemetry.initialize();

            const onSpy = jest.spyOn(Shopware.Utils.EventBus, 'on');
            const offSpy = jest.spyOn(Shopware.Utils.EventBus, 'off');

            telemetry.debug = true;
            await flushPromises();
            expect(onSpy).toHaveBeenCalled();

            telemetry.debug = false;
            await flushPromises();
            expect(offSpy).toHaveBeenCalled();
        });

        it('collects all observed nodes when debug is turned on', async () => {
            const telemetry = new Telemetry({ queries: [TaggedButtons] }, true);

            telemetry.initialize();
            telemetry.debug = true;

            const element = document.createElement('button');
            element.setAttribute('data-analytics-id', 'tested-element');
            document.body.appendChild(element);

            await flushPromises();

            expect(telemetry.observedNodes).toEqual([element]);
        });
    });
});
