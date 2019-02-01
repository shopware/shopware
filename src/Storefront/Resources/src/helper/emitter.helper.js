const emitter = new WeakMap();

export default class Emitter {
	constructor(){
		emitter.set(this, {
			events: {}
		});

		this.eventLength = 0;
	}

	on(event, cb, once = false){
		if(typeof cb === 'undefined') {
			throw new Error('You must provide a callback method.');
		}

		if(typeof cb !== 'function') {
			throw new TypeError('Listener must be a function');
		}

		this.events[event] = this.events[event] || [];
		this.events[event].push({
      cb,
      once
    });

		this.eventLength++;

		return this;
	}

	off(event, cb){
		if(typeof cb === 'undefined') {
			throw new Error('You must provide a callback method.');
		}

		if(typeof cb !== 'function') {
			throw new TypeError('Listener must be a function');
		}

		if(typeof this.events[event] === 'undefined'){
			throw new Error(`Event not found - the event you provided is: ${event}`);
		}

		const listeners = this.events[event];

		listeners.forEach((v, i) => {
			if(v.cb === cb) {
				listeners.splice(i, 1);
			}
		});

		if(listeners.length === 0){
			delete this.events[event];

			this.eventLength--;
		}

		return this;
	}

	trigger(event, ...args){
		if(typeof event === 'undefined'){
			throw new Error('You must provide an event to trigger.');
		}

		const listeners = this.events[event];
    const onceListeners = [];

		if(typeof listeners !== 'undefined') {
			listeners.forEach((v, k) => {
        v.cb.apply(this, args);

        if(v.once) onceListeners.unshift(k);

        onceListeners.forEach((v, k) => {
          listeners.splice(k, 1);
        });
			});
		}

		return this;
	}

  once(event, cb){
    this.on(event, cb, true);
  }

  destroy(){
    emitter.delete(this);

    this.eventLength = 0;
  }

	get events(){
		return emitter.get(this).events;
	}
}