# Telemetry (`Shopware.Telemetry`)

The Administration ships a built-in telemetry system that tracks user interactions and system events to help the Shopware team understand how the product is used. It extends the global `Shopware` object with a `Telemetry` property and is intentionally designed to be **opt-in** for custom tracking: automated DOM instrumentation handles the common cases, while a programmatic API covers the rest.

> **Source**: `src/core/telemetry/`

## Architecture

### Initialization

The telemetry system is bootstrapped during the `init-post` phase (after the Vue application is mounted). It:

1. Registers a Vue Router `afterEach` guard to emit `page_change` events.
2. Subscribes to the `loginService` for `identify` (login) and `reset` (logout) events.
3. Attaches a `MutationObserver` on `document.body` to instrument DOM elements added dynamically after boot.

### Data Flow

All events are delivered through the Administration's internal event bus. Consumers subscribe to the `'telemetry'` channel and receive typed `TelemetryEvent` objects:

```typescript
Shopware.Utils.EventBus.on('telemetry', (event) => {
    console.log(event.eventType, event.eventData, event.timestamp);
});
```

Separating producers (telemetry system) from consumers (e.g., Amplitude handler) keeps the core system side-effect–free and allows new consumers to be added without touching telemetry code.

## Event Types

The event model is intentionally close to the [Segment analytics API](https://segment.com/docs/):

| Event type | When fired | Key payload fields |
|---|---|---|
| `identify` | User logs in | `userId`, `locale`, `isAdmin` |
| `page_change` | Route changes | `from` (RouteLocation), `to` (RouteLocation) |
| `user_interaction` | Instrumented DOM element interacted with | `target` (HTMLElement), `originalEvent` (Event) |
| `programmatic` | Manually dispatched via `Shopware.Telemetry.track()` | `eventName` (string), any additional key/value pairs |
| `reset` | User logs out | — |

`user_interaction` and `programmatic` are intentionally split from the generic Segment `track` event to distinguish automated DOM tracking from developer-dispatched events.

Full TypeScript definitions live in `src/core/telemetry/types.ts`.

## Automated DOM Instrumentation

The `MutationObserver` runs element queries against newly added DOM nodes. Elements that match are automatically given event listeners. The following patterns are currently instrumented:

- Clicks on `<a>` (link) elements.
- Clicks on `<button>` elements that carry a `data-analytics-id` attribute.
- Any element with a `data-product-analytics` attribute.

### Customizing the Tracked Event

By default the listener type is `click`. Override it per element with `data-product-analytics-event`:

```html
<mt-button
    data-analytics-id="my-save-button"
    data-product-analytics-event="mouseover"
>
    Save
</mt-button>
```

### Adding Structured Data to an Instrumented Element

Extra attributes prefixed with `data-analytics-` are collected alongside the event:

```html
<mt-button
    data-analytics-id="export-button"
    data-analytics-format="csv"
    data-analytics-entity="product"
>
    Export
</mt-button>
```

## Programmatic Tracking

Call `Shopware.Telemetry.track()` to dispatch a `programmatic` event from JavaScript code. The payload must include an `eventName` property; additional fields are forwarded as-is to consumers.

```typescript
Shopware.Telemetry.track({
    eventName: 'plugin_installed',
    pluginName: 'MyPlugin',
    pluginVersion: '1.0.0',
});
```

This is the recommended approach for tracking long-running processes (plugin installation, bulk operations), Admin-SDK interactions, or any server-side-triggered events that have no direct DOM counterpart.

## Amplitude Consumer

The built-in Amplitude consumer (`src/core/telemetry/amplitude/`) subscribes to the telemetry event bus and forwards events to Amplitude Analytics. It maps internal event types to Amplitude event names using **snake_case** conventions:

| Internal event type | Amplitude event name |
|---|---|
| `identify` | `login` (plus `amplitude.setUserId()`) |
| `page_change` | `page_viewed` |
| `user_interaction` on `<a>` | `link_visited` |
| `user_interaction` on other elements | `snake_case(tagName + eventType)` — e.g. `button_click` |
| `programmatic` | value of `eventName` field (passed through as-is) |
| `reset` | `logout` |

The Amplitude client is configured with `autocapture: false` (no automatic page-view or session capture), EU server zone, `flushMaxRetries: 2`, and SDK logging disabled (`logLevel: 0`). The real API key is injected by the analytics gateway — the client is initialized with a placeholder key.

## Debugging

Enable verbose logging of all emitted telemetry events in the browser DevTools console:

```javascript
Shopware.Telemetry.debug = true;
```

Set back to `false` to silence the output.
