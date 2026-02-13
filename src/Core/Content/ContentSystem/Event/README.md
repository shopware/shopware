# Event

Lifecycle events dispatched during content hydration. Both events share identical structure — `elements` (mutable `list<ContentElement>`) is the only writable property.

## Key Classes

- `PreContentHydrationEvent` - Dispatched before hydration; allows layout preparation
- `PostHydrationEvent` - Dispatched after hydration; allows layout finalization

## Lifecycle

```
PreContentHydrationEvent → Hydration (FULL mode only) → PostHydrationEvent
```

Priority ranges and extension guidelines are documented in Event/Listener/ and in the event PHPDoc.
