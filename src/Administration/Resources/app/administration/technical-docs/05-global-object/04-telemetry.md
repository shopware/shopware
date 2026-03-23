# Telemetry

The Shopware Administration ships a built-in telemetry layer (`Shopware.Telemetry`) that collects product-analytics events and routes them to an Amplitude gateway. The entire subsystem is gated behind the `PRODUCT_ANALYTICS` feature flag and requires explicit user consent before any data is transmitted.

## Architecture overview

```
Shopware.Telemetry          ← public API (identify, track)
    │
    ▼
EventBus.emit('telemetry')  ← internal event bus
    │
    ▼
amplitude.init.ts           ← Amplitude consumer (consent-gated watcher)
    │
    ▼
Amplitude SDK               ← forwards to analyticsGatewayUrl
```

`Shopware.Telemetry` is a singleton instance of the `Telemetry` class defined in `src/core/telemetry/index.ts`. It must be initialized once during the boot sequence via `Shopware.Telemetry.initialize()`. Calling `initialize()` more than once throws an error.

## Initialization

`initialize()` sets up three internal subsystems:

| Subsystem | What it does |
|-----------|-------------|
| DOM observables | `MutationObserver` on `document.body` watches for elements matching configured `ElementQuery` rules (anchor tags, tagged buttons, `data-product-analytics-event` attributes) and attaches click/custom-event listeners. |
| Page changes | After `Shopware.Application.viewInitialized`, hooks into Vue Router's `afterEach` to emit a `page_change` event on route transitions (skips same-name navigations). |
| User changes | Registers login/logout listeners on `loginService`: login → `identify()`, logout → `reset`. |

The `PRODUCT_ANALYTICS` feature flag is checked at initialization time and again inside every `dispatchEvent` call, so events are silently dropped when the flag is inactive.

## Public API

### `Shopware.Telemetry.identify()`

Reads the current user from the `session` Pinia store and dispatches an `identify` event with:

```typescript
{
    userId: string | null,   // currentUser.id or null
    locale: string | null,   // currentLocale from session store
    isAdmin: boolean | null, // currentUser.admin or null
}
```

This is called automatically on login. Consumers of the Amplitude integration also call it reactively when consent is granted (so that user identity is established for the current session).

### `Shopware.Telemetry.track(eventData)`

Dispatches a `programmatic` event. Use this for deliberate, code-triggered tracking that doesn't map to a DOM interaction.

```typescript
Shopware.Telemetry.track({
    eventName: 'my_feature_used',
    // ...arbitrary event payload
});
```

### `Shopware.Telemetry.debug`

Settable boolean. When `true`, all telemetry events are logged to the browser console via `console.debug`. Automatically sets up/tears down a debug listener on the `telemetry` event bus channel.

## Event types

All events flow through `Shopware.Utils.EventBus.emit('telemetry', event)`. The internal event type map (`EventTypes` in `src/core/telemetry/types.ts`) defines:

| Type | Trigger | Key payload fields |
|------|---------|-------------------|
| `identify` | Login / consent granted | `userId`, `locale`, `isAdmin` |
| `reset` | Logout | _(empty)_ |
| `page_change` | Vue Router `afterEach` | `from`, `to` (RouteLocation) |
| `user_interaction` | DOM element event | `target` (HTMLElement), `originalEvent` |
| `programmatic` | `Shopware.Telemetry.track()` | arbitrary `eventName` + payload |

## Amplitude consumer (consent-gated watcher)

`src/app/init-post/amplitude.init.ts` connects the event bus to the Amplitude SDK. It runs only when `analyticsGatewayUrl` is configured and implements the following lifecycle:

1. **Consent watch** — a Vue `computed` wrapping `consentStore.isAccepted('product_analytics')` is passed to `watch()` with `{ immediate: true }`. When consent transitions `false → true`:
   - Amplitude is initialized (`initTelemetryAmplitude`) if not yet done.
   - `amplitude.setOptOut(false)` — re-enables data transmission.
   - The telemetry event handler is registered on the event bus.
   - `Shopware.Telemetry.identify()` is called so the current user is associated with the session.
2. **Consent revoked** (`true → false`):
   - `amplitude.setOptOut(true)` — halts transmission.
   - Event handler is removed from the event bus.
   - A `delete_user` privacy event is sent (best-effort, non-blocking).
   - `amplitude.flush()` drains the queue; `clearAmplitudeCookies()` runs asynchronously.
3. **Logout beacon** — `registerTelemetryLogoutListener` wraps `navigator.sendBeacon` to ensure the logout flush payload is sent as `application/json` (Amplitude's gateway requirement). The wrapper is reference-counted and restored after all logout events complete.
4. **Consent event forwarding** — `consent` events from the event bus are forwarded to the anonymous Amplitude gateway unconditionally (no consent required for consent-tracking itself).

## DOM auto-tracking

Elements are tracked automatically when they match one of the built-in `ElementQuery` rules:

- **AnchorTags** — all `<a>` elements receive click tracking.
- **TaggedButtons** — elements with a `data-product-analytics-tag` attribute.
- **ProductAnalyticsTag** — elements with a `data-product-analytics-event` attribute; the attribute value overrides the default `click` event name.

Custom `ElementQuery` rules can be passed to the `Telemetry` constructor via the `queries` config array (internal API, not available for plugins).

## Amplitude identity model

The Amplitude user ID is composed as `${shopId}:${userId}`, making it globally unique across shops while remaining stable across sessions for the same admin user.

## Feature flag guard

All event dispatch is guarded:

```typescript
if (!Shopware.Feature.isActive('PRODUCT_ANALYTICS')) {
    return;
}
```

When the flag is inactive, `Telemetry.initialize()` returns immediately (no observers, no listeners) and every subsequent `dispatchEvent` call is a no-op.
