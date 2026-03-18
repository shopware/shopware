# Telemetry

The Administration ships a built-in telemetry layer (`Shopware.Telemetry`) that captures user interactions and lifecycle events and forwards them to registered consumers via the internal event bus. It is gated behind the `PRODUCT_ANALYTICS` feature flag: when the flag is inactive, no events are emitted and all DOM instrumentation is skipped.

Source: `src/core/telemetry/`

## Architecture

```
[DOM / Router / Login]
        │
        ▼
  Telemetry class              ← Shopware.Telemetry
  (src/core/telemetry/index.ts)
        │  emits TelemetryEvent
        ▼
  EventBus ('telemetry')
        │
        ▼
  Consumer(s)
  e.g. Amplitude gateway
  (src/core/telemetry/amplitude/)
```

The `Telemetry` instance is created once during the post-init phase and exposed as `Shopware.Telemetry`. It must not be instantiated again.

## Initialization

```typescript
// Called once by the post-init sequence — do not call manually.
Shopware.Telemetry.initialize();
```

During initialization the system:
1. Attaches a `MutationObserver` to `document.body` to detect newly inserted DOM elements and add tracking listeners to elements that match the configured element queries.
2. Subscribes to `router.afterEach` (waits for `viewInitialized`) to emit `page_change` events on route transitions.
3. Subscribes to `loginService` login / logout callbacks to emit `identify` and `reset` events.

`initialize()` throws if called a second time. Use `Shopware.Telemetry.isInitialized` to guard against this.

## Event Types

| Event type | When it fires |
|---|---|
| `identify` | User logs in; carries `userId`, `isAdmin`, `locale` |
| `page_change` | Route changes (same-name routes are skipped) |
| `user_interaction` | DOM element click (or custom event) tracked by an element query |
| `programmatic` | Manually dispatched via `Shopware.Telemetry.track()` |
| `reset` | User logs out |

Event payloads are defined in `src/core/telemetry/types.ts`.

## Listening to Telemetry Events

Consumers subscribe via the global event bus:

```typescript
import type { TelemetryEvent, EventTypes } from 'src/core/telemetry/types';

Shopware.Utils.EventBus.on('telemetry', (event: TelemetryEvent<EventTypes>) => {
    console.log(event.eventType, event.eventData);
});
```

## DOM Auto-Tracking

The `MutationObserver` tests every newly added DOM node against a set of *element queries*. Currently three queries are active:

| Query | Matches |
|---|---|
| `AnchorTags` | `<a>` elements |
| `TaggedButtons` | Any element with `data-analytics-id` attribute |
| `ProductAnalyticsTag` | Any element with `data-product-analytics` attribute |

Once matched, a click listener (or the event specified by `data-product-analytics-event`) is attached. On trigger, a `user_interaction` event is emitted with the element and original event as payload.

### Controlling the Tracked Event

By default, clicks are tracked. Override this per element:

```html
<mt-button
    data-analytics-id="my-action"
    data-product-analytics-event="mouseover"
>
    Hover me
</mt-button>
```

### Attaching Extra Properties

All `data-analytics-*` attributes on a tracked element are forwarded as snake_case event properties (prefixed with `sw_element_`):

```html
<mt-button
    data-analytics-id="export-btn"
    data-analytics-export-format="csv"
>
    Export
</mt-button>
<!-- results in: { sw_element_export_format: 'csv' } -->
```

## Programmatic Tracking

Use `Shopware.Telemetry.track()` to fire an event from JavaScript code:

```typescript
Shopware.Telemetry.track({
    eventName: 'plugin_installed',
    pluginName: 'MyPlugin',
    version: '1.0.0',
});
```

The `eventName` field is required; all other fields are forwarded as event properties.

## Amplitude Consumer

The bundled Amplitude consumer (`src/core/telemetry/amplitude/`) maps internal event types to Amplitude calls using **snake_case** event names:

| Internal event | Amplitude call |
|---|---|
| `identify` | `amplitude.setUserId(shopId + ':' + userId)` + `amplitude.track('login')` |
| `page_change` | `amplitude.track('page_viewed', { sw_route_* })` |
| `user_interaction` on `<a>` | `amplitude.track('link_visited', { sw_link_href, sw_link_type })` |
| `user_interaction` on other elements | `amplitude.track(snakeCase(tagName + eventType))` |
| `programmatic` | `amplitude.track(eventData.eventName, eventData)` |
| `reset` | `amplitude.track('logout')` + `amplitude.flush()` + `amplitude.reset()` |

Amplitude is configured with `flushMaxRetries: 2`, `logLevel: 0` (silent), `autocapture: false`, and EU server zone.

## Debugging

Enable console output for all telemetry events in the browser:

```javascript
Shopware.Telemetry.debug = true;
```

While debug mode is active, every `TelemetryEvent` is logged to the console via `console.debug`. Disable it again by setting the flag back to `false`.

For inspecting which DOM nodes are currently being observed:

```javascript
Shopware.Telemetry.observedNodes; // Array<Node>
```
