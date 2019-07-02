[titleEn]: <>(Symfony Event names removed)

Symfony as of version 4.3 allows to dispatch events without an event name. In this case the class name can be used to subscribe to events.

We removed the event names where it was possible in favor of using the class name for subscribing to events.
We also removed the `getName()` method from the `ShopwareEvent` interface.

Especially the StorefrontPage events are affected here, as we removed the names from all of them.
Furthermore the BusinessEvents aren't dispatched by their name anymore, but by the class, the name is only used to add Actions to the given BusinessEvents.

The generic DAL events that get dispatched when an Entity gets written or loaded are untouched from this changes.
If you have custom `NestedEvents` that are generic and therefore need to be dispatched by name implement the new `GenericEvent` interface.
