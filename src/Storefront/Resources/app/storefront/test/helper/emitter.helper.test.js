/* eslint-disable */
import NativeEventEmitter from 'src/helper/emitter.helper';

/**
 * @package storefront
 */
describe('NativeEventEmitter tests', () => {
    test('global event emitter', () => {
        const emitter = new NativeEventEmitter();
        expect(emitter.el).toBe(document);
    });

    test('scoped event emitter', () => {
        const el = document.createElement('div');
        const emitter = new NativeEventEmitter(el);
        expect(emitter.el).toBe(el);
    });

    test('resetting event emitter', () => {
        const emitter = new NativeEventEmitter();
        const noop = () => {};

        emitter.subscribe('foo', noop);
        emitter.subscribe('bar', noop);
        emitter.subscribe('bat', noop);
        expect(emitter.listeners.length).toBe(3);
        emitter.reset();
        expect(emitter.listeners.length).toBe(0);
    });

    describe('emitter specific tests', () => {
        // Create shared instance of the event emitter
        const emitter = new NativeEventEmitter();

        // Reset the event emitter instance
        beforeEach(() => {
            emitter.reset();
        });

        test('publish & subscribe event without data', (done) => {
            const eventName = 'my-custom-event';

            emitter.subscribe(eventName, (event) => {
                expect(event.type).toBe(eventName);
                expect(event.constructor).toBe(CustomEvent);
                done();
            });

            emitter.publish(eventName);
        });

        test('publish & subscribe with additional data', (done) => {
            const eventName = 'my-custom-event';
            const customData = { custom: 'prop' };

            emitter.subscribe(eventName, (event) => {
                expect(event.constructor).toBe(CustomEvent);
                expect(event.detail).toBe(customData);
                done();
            });

            emitter.publish(eventName, customData);
        });

        test('publish & subscribe with a different scope', (done) => {
            const eventName = 'my-custom-event';
            const scope = { prop: 'test' };

            emitter.subscribe(eventName, function scopedListener(event) { // eslint-disable-line
                expect(event.constructor).toBe(CustomEvent);
                expect(this).toBe(scope);
                done();
            }, { scope });

            emitter.publish(eventName);
        });

        test('once listeners', (done) => {
            const eventName = 'my-custom-event-once';

            emitter.subscribe(eventName, (event) => { // eslint-disable-line
                expect(event.constructor).toBe(CustomEvent);
                expect(event.type).toBe(eventName);
                expect(emitter.listeners.length).toBe(0);
                done();
            }, { once: true });

            emitter.publish(eventName);
        });

        test('unsubscribe event listeners', (done) => {
            const eventName = 'my-custom-event';

            emitter.subscribe(eventName, (event) => { // eslint-disable-line
                expect(event.constructor).toBe(CustomEvent);
                expect(emitter.listeners.length).toBe(1);
                emitter.unsubscribe(eventName);
                expect(emitter.listeners.length).toBe(0);
                done();
            });

            emitter.publish(eventName);
        });

        test('namespaced events', (done) => {
            const noop = jest.mock();
            const eventName = 'foo';

            emitter.subscribe(`${eventName}.test`, (event) => {
                expect(event.type).toBe(eventName);
                done();
            });
            emitter.subscribe(eventName, noop);
            emitter.publish(eventName);

            expect(emitter.listeners.length).toBe(2);
            emitter.unsubscribe(`${eventName}.test`);
            expect(emitter.listeners.length).toBe(1);
        });
    });

    describe('element specific tests', () => {
        test('general publish & subscribe', (done) => {
            const el = document.createElement('div');
            const eventName = 'my-custom-event';
            new NativeEventEmitter(el); // eslint-disable-line

            el.addEventListener(eventName, (event) => {
                expect(event.constructor).toBe(CustomEvent);
                done();
            });

            el.$emitter.publish(eventName);
        });

        test('element should have access to the emitter', () => {
            const el = document.createElement('div');
            const emitter = new NativeEventEmitter(el);

            expect(el.hasOwnProperty('$emitter')).toBeTruthy();
            expect(el.$emitter).toBe(emitter);
        });


    });

    describe('it can change element after creation', () => {
        const eventName = 'custom-event';
        const emmiter = new NativeEventEmitter();

        const emittingTarget = document.createElement('div');
        document.body.append(emittingTarget);

        emmiter.el = emittingTarget;
        emmiter.subscribe(eventName, (event) => {
            expect(event.target).toStrictEqual(emittingTarget);
        });

        emmiter.publish('custom-event');
    })
});
