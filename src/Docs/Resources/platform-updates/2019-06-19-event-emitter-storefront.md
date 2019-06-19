[titleEn]: <>(Event Emitter Storefront)

The storefront contains a new event emitter based on native Custom Events. It allows to publish events either globally
or per plugin. The plugin class initializes the event emitter automatically.

The global event emitter is available under `document.$emitter` & `window.eventEmitter`. Inside a plugin the emitter
is available under the property `this.$emitter`;

**Basic usage**

```
// Subscribe to an event
document.$emitter.subscribe('my-event-name', (event) => {
    console.log(event);
});

// Publish event
document.$emitter.publish('my-event-name');
```

**Provide additional data to the event**

```
document.$emitter.subscribe('my-event-name', (event) => {
    console.log(event.detail);
});

document.$emitter.publish('my-event-name', {
    custom: 'data'
});
```

**Providing an different scope***

```
document.$emitter.subscribe('my-event-name', (event) => {
    console.log(event.detail);
}, { scope: myScope });

document.$emitter.publish('my-event-name', {
    custom: 'data'
});
```

**Event listeners which will be fired once***

```
document.$emitter.subscribe('my-event-name', (event) => {
    console.log(event.detail);
}, { once: true });

document.$emitter.publish('my-event-name', {
    custom: 'data'
});
```

**Namespaced events**

```
document.$emitter.publish('my-event-name.my-plugin');
```

**Unsubscribe events**

```
document.$emitter.unsubscribe('my-event-name.my-plugin');
```

**Reset & remove all listeners from emitter**

```
document.$emitter.reset();
```