const emitter = new WeakMap();

export default class Emitter {
    /**
     * @constructor
     */
    constructor() {
        emitter.set(this, {
            events: {}
        });

        this.eventLength = 0;
    }

    /**
     * Registers an event listener using the provided event name and callback method. The event listener can remove it
     * itself after getting called once using the `once` parameter.
     *
     * @param {String} event
     * @param {Function} callback
     * @param {Boolean} [once=false]
     * @returns {Emitter}
     */
    on(event, callback, once = false) {
        if (typeof cb === 'undefined') {
            throw new Error('Please provide a callback method.');
        }

        if (typeof callback !== 'function') {
            throw new TypeError('Listener must be a function');
        }

        this.events[event] = this.events[event] || [];
        this.events[event].push({
            callback,
            once
        });

        this.eventLength += 1;

        return this;
    }

    /**
     * Removes an event listener using the provided event name and callback method.
     *
     * @param {String} event
     * @param {Function} callback
     * @returns {Emitter}
     */
    off(event, callback) {
        if (typeof callback === 'undefined') {
            throw new Error('Please provide a callback method.');
        }

        if (typeof callback !== 'function') {
            throw new TypeError('Listener must be a function');
        }

        if (typeof this.events[event] === 'undefined') {
            throw new Error(`Event not found - the event you provided is: ${event}`);
        }

        const listeners = this.events[event];

        listeners.forEach((v, i) => {
            if (v.callback === callback) {
                listeners.splice(i, 1);
            }
        });

        if (listeners.length === 0) {
            delete this.events[event];

            this.eventLength -= 1;
        }

        return this;
    }

    /**
     * Fires an event using the provided event name.
     * @param {String} event
     * @param {...any} args
     * @returns {Emitter}
     */
    trigger(event, ...args) {
        if (typeof event === 'undefined') {
            throw new Error('Please provide an event to trigger.');
        }

        const listeners = this.events[event];
        const onceListeners = [];

        if (typeof listeners !== 'undefined') {
            listeners.forEach((value, key) => {
                value.cb.apply(this, args);

                if (value.once) onceListeners.unshift(key);

                onceListeners.forEach((v, k) => {
                    listeners.splice(k, 1);
                });
            });
        }

        return this;
    }

    /**
     * Registers an event listener which will be fired once and will remove itself.
     *
     * @param {String} event
     * @param {Function} callback
     */
    once(event, callback) {
        this.on(event, callback, true);
    }

    /**
     * Destroys all event listeners and resets the events counter.
     *
     * @returns {void}
     */
    destroy() {
        emitter.delete(this);

        this.eventLength = 0;
    }

    /**
     * Returns all registered event listeners.
     *
     * @returns {Object}
     */
    get events() {
        return emitter.get(this).events;
    }
}
