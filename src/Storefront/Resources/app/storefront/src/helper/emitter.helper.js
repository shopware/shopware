/**
 * @package storefront
 */
export default class NativeEventEmitter {
    /**
     * Event Emitter which works with the provided DOM element. The class isn't meant to be
     * extended. It should rather being used as a mixin component to provide the ability to
     * publish events.
     *
     * @example
     * const emitter = new NativeEventEmitter();
     * emitter.publish('my-event-name');
     *
     * @example using custom data
     * const emitter = new NativeEventEmitter();
     * emitter.subscribe('my-event-name', (event) => {
     *     console.log(event.detail);
     * });
     * emitter.publish('my-event-name', { custom: 'data' });
     *
     * @example using a custom scope
     * const emitter = new NativeEventEmitter();
     * emitter.subscribe('my-event-name', (event) => {
     *     console.log(event.detail);
     * }, { scope: myScope });
     * emitter.publish('my-event-name', { custom: 'data' });
     *
     * @example once listeners
     * const emitter = new NativeEventEmitter();
     * emitter.subscribe('my-event-name', (event) => {
     *     console.log(event.detail);
     * }, { once: true });
     * emitter.publish('my-event-name', { custom: 'data' });
     *
     * @constructor
     * @param {Document|HTMLElement} [el = document]
     */
    constructor(el = document) {
        this._el = el;
        el.$emitter = this;
        this._listeners = [];
    }

    /**
     * Publishes an event on the element. Additional information can be added using the `data` parameter.
     * The data are accessible in the event handler in `event.detail` which represents the standard
     * implementation.
     *
     * @param {Boolean} cancelable
     * @return {CustomEvent}
     */
    publish(eventName, detail = {}, cancelable = false) {
        const event = new CustomEvent(eventName, {
            detail,
            cancelable,
        });

        this.el.dispatchEvent(event);

        return event;
    }

    /**
     * Subscribes to an event and adds a listener.
     *
     * @param {String} eventName
     * @param {Function} callback
     * @param {Object} [opts = {}]
     */
    subscribe(eventName, callback, opts = {}) {
        const emitter = this;
        const splitEventName = eventName.split('.');
        let cb = opts.scope ? callback.bind(opts.scope) : callback;

        // Support for listeners which are fired once
        if (opts.once && opts.once === true) {
            const onceCallback = cb;
            cb = function onceListener(event) {
                emitter.unsubscribe(eventName);
                onceCallback(event);
            };
        }

        this.el.addEventListener(splitEventName[0], cb);

        this.listeners.push({
            splitEventName,
            opts,
            cb,
        });

        return true;
    }

    /**
     * Removes an event listener.
     *
     * @param {String} eventName
     */
    unsubscribe(eventName) {
        const splitEventName = eventName.split('.');
        this.listeners = this.listeners.reduce((accumulator, listener) => {
            const foundEvent = [...listener.splitEventName].sort().toString() === splitEventName.sort().toString();

            if (foundEvent) {
                this.el.removeEventListener(listener.splitEventName[0], listener.cb);
                return accumulator;
            }

            accumulator.push(listener);
            return accumulator;
        }, []);

        return true;
    }

    /**
     * Resets the listeners
     *
     * @return {boolean}
     */
    reset() {
        // Loop through the event listener and remove them from the element
        this.listeners.forEach((listener) => {
            this.el.removeEventListener(listener.splitEventName[0], listener.cb);
        });

        // Reset registry
        this.listeners = [];
        return true;
    }

    get el() {
        return this._el;
    }

    set el(value) {
        this._el = value;
    }

    get listeners() {
        return this._listeners;
    }

    set listeners(value) {
        this._listeners = value;
    }
}
