# Shopware.Telemetry

`Shopware.Telemetry` is the public telemetry facade exposed on the global `Shopware` object. It collects anonymised behavioural signals from the Administration UI and dispatches them over an internal event bus. Downstream consumers (e.g. the product-analytics init module) subscribe to that bus and route events to the actual analytics backend.

**Source files:**
- `src/core/telemetry/index.ts` — `Telemetry` class, singleton export, `Shopware.Telemetry` assignment
- `src/core/telemetry/types.ts` — event type definitions
- `src/core/telemetry/product-analytics/` — Amplitude adapter and gateway client (internal)

---

## Public API on `Shopware.Telemetry`

### `initialize()`

Activates the telemetry system: registers the DOM mutation observer, page-change router hook, login/logout listeners, and debug listener.

Throws `Error('Telemetry is already initialized')` if called a second time. Must be called once during application boot; never call it from extension code.

### `identify()`

Dispatches an `identify` event carrying the current user's `id`, `locale` (from `Shopware.Store.get('session').currentLocale`), and `isAdmin` flag.

Called automatically:
- on every successful login (via `loginService.addOnLoginListener`)
- when product-analytics consent is granted (inside `product-analytics.init.ts`)

### `track(eventData)`

Dispatches a `programmatic` telemetry event with an arbitrary payload.

```typescript
Shopware.Telemetry.track({
    eventName: 'my_custom_event',
    someProperty: 'value',
});
```

### `debug` (setter)

```typescript
Shopware.Telemetry.debug = true;
```

When enabled, every `telemetry` event bus emission is printed to `console.debug`. Useful for development. Has no effect until `initialize()` has been called.

### `isInitialized` (getter)

Returns `true` after `initialize()` has been called.

---

## Event types

All events are wrapped in a `TelemetryEvent<N>` envelope (defined in `types.ts`) and emitted on `Shopware.Utils.EventBus` under the `'telemetry'` channel.

| Event type | Payload | Description |
|---|---|---|
| `user_interaction` | `{ target: HTMLElement; originalEvent: Event }` | DOM element interaction tracked by the MutationObserver and ElementQuery matchers |
| `page_change` | `{ from: RouteLocation; to: RouteLocation }` | Emitted after each router navigation when route name changes |
| `programmatic` | `{ eventName: string; [key: string]: TrackableType }` | Manual tracking via `Shopware.Telemetry.track()` |
| `identify` | `{ userId: string \| null; locale: string \| null; isAdmin: boolean \| null }` | User identity snapshot |
| `reset` | `{}` | Emitted on logout; consumers should clear user state |

`TrackableType = string | string[] | number | boolean | null`

---

## DOM auto-tracking

The MutationObserver watches `document.body` for subtree changes. For each new element that matches an `ElementQuery`, it attaches an event listener that fires `user_interaction` events.

Built-in element queries (registered in the default `Telemetry` singleton):

| Query | Tracked attribute / selector |
|---|---|
| `AnchorTags` | `<a>` tags |
| `TaggedButtons` | Elements with `data-product-analytics-event` attribute |
| `ProductAnalyticsTag` | Elements with `data-product-analytics-event` attribute |

The `data-product-analytics-event` attribute overrides the default `click` event name (e.g. `data-product-analytics-event="focus"`).

---

## Product-analytics consumer — architecture

The Telemetry class itself has no knowledge of Amplitude or any analytics backend. The backend integration lives entirely in `src/app/init-post/product-analytics.init.ts` and the `src/core/telemetry/product-analytics/` module.

### Initialization flow (`product-analytics.init.ts`)

1. Reads `analyticsGatewayUrl` from the context store. If absent, exits — no tracking takes place.
2. Creates an `AmplitudeAdapter` (wraps the Amplitude Browser SDK) and a `GatewayClient` (delegates to the adapter + handles server-side calls).
3. Registers a `consent` event handler (`createConsentEventHandler`) that forwards consent metrics to the gateway anonymously.
4. Creates a consent-gated watcher on `consentStore.isAccepted('product_analytics')`:
   - **Consent granted** → `gatewayClient.init()` (once), `amplitudeAdapter.setOptOut(false)`, subscribe telemetry event handler, call `Shopware.Telemetry.identify()`.
   - **Consent revoked** → `amplitudeAdapter.setOptOut(true)`, unsubscribe telemetry event handler, call `gatewayClient.deleteUser(shopId, userId)`, flush, then asynchronously clear Amplitude cookies.
5. Registers a logout listener via `registerAmplitudeLogoutListener` that wraps `navigator.sendBeacon` to send JSON payloads, switches Amplitude transport to `beacon`, flushes, and resets.

### `GatewayClient`

`GatewayClient` implements `TrackingClient` by delegating to the supplied adapter. Additionally it exposes:

| Method | Description |
|---|---|
| `trackConsentMetric(metric, props, time)` | POST to `{gatewayUrl}/v1/event/anonymous` — does not require user consent |
| `deleteUser(shopId, userId)` | POST to `{gatewayUrl}/v1/delete-user` |

All server-side requests from `GatewayClient` use `fetch` with `credentials: 'omit'` and `keepalive: true`. Errors are swallowed (best-effort).

### `AmplitudeAdapter`

Wraps `@amplitude/analytics-browser` with the following fixed settings:

- `autocapture: false`
- `serverZone: 'EU'`
- `ipAddress: false`, `language: false`, `platform: false` (privacy)
- `fetchRemoteConfig: false`
- `logLevel: None`
- Server URL: `{gatewayUrl}/v1/event`

The API key (`AMPLITUDE_BROWSER_API_KEY`) is a placeholder; the actual routing to Amplitude happens server-side via the gateway.

---

## Feature flag removal (PRODUCT_ANALYTICS)

Prior to `#15736`, all telemetry dispatches (`dispatchEvent`, `initialize`) and consent event dispatches were gated behind `Feature.isActive('PRODUCT_ANALYTICS')`. This flag has been removed. Telemetry and consent events are now always enabled when `analyticsGatewayUrl` is present. Feature-flagging is no longer part of the telemetry lifecycle.

---

## See Also

- [Global Shopware Object](./01-global-shopware-object.md)
- [Context & Auth](./03-context-auth.md)
- [Consent System](../10-commercial/02-consent-system.md)
