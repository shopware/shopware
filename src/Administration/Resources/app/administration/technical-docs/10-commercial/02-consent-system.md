# Consent System

The Shopware Administration provides a consent management system that tracks user decisions about data sharing and product analytics. It is used internally to gate telemetry and to forward consent-change events to the analytics pipeline.

> **Feature flag**: All consent event _dispatch_ is gated behind `PRODUCT_ANALYTICS`. The store actions (`accept`, `revoke`, `update`) operate independently of the flag.

## Data model — `ConsentDTO`

```typescript
type ConsentDTO = {
    readonly name: string;                                         // human-readable label
    readonly identifier: string;                                   // unique key, e.g. 'product_analytics'
    readonly scopeName: 'system' | 'admin_user';                   // consent scope
    readonly actor: string | null;                                 // who last changed it
    readonly status: 'unset' | 'declined' | 'accepted' | 'revoked';
    readonly updated_at: string | null;
};
```

Known consent identifiers:

| Identifier | Scope | Purpose |
|-----------|-------|---------|
| `product_analytics` | `admin_user` | Amplitude product-analytics tracking |
| `backend_data` | `system` | Aggregated shop statistics |

## Consent store (`Shopware.Store.get('consent')`)

The consent store is a Pinia store registered under the key `consent`. It exposes:

### State

```typescript
{
    consents: Record<string, ConsentDTO>  // keyed by consent identifier
}
```

### Actions

| Action | Description |
|--------|-------------|
| `update(): Promise<void>` | Fetches the current consent list from the backend (`consentApiService.list()`) and replaces the local state. |
| `accept(name: string): Promise<void>` | Accepts the named consent if it is not already accepted. Calls `consentApiService.accept(name)`, updates local state, and dispatches a `consent_status_change` event. Throws if the consent is not loaded. |
| `revoke(name: string): Promise<void>` | Revokes (or declines) the named consent if it is not already revoked/declined. Calls `consentApiService.revoke(name)`, updates local state, and dispatches a `consent_status_change` event. Throws if the consent is not loaded. |
| `isAccepted(name: string): boolean` | Returns `true` if the named consent has `status === 'accepted'`. Throws if the consent is not loaded. |

### Usage example

```typescript
const consentStore = Shopware.Store.get('consent');

// Check current state
if (consentStore.isAccepted('product_analytics')) {
    // telemetry is active
}

// Accept from code
await consentStore.accept('product_analytics');

// Revoke
await consentStore.revoke('product_analytics');
```

## Consent events

Consent changes are published on the global event bus under the channel `'consent'`. All events are typed instances of `ConsentEvent<N>`.

### Listening to consent events

```typescript
import type { ConsentEvent } from 'src/core/consent/events';

const handler = (event: ConsentEvent<ConsentEventName>) => {
    console.log(event.eventName, event.eventProperties, event.timestamp);
};

Shopware.Utils.EventBus.on('consent', handler);

// Remove when no longer needed
Shopware.Utils.EventBus.off('consent', handler);
```

### Type guards

```typescript
import { isConsentEvent, isConsentEventType } from 'src/core/consent/events';

Shopware.Utils.EventBus.on('consent', (event) => {
    if (!isConsentEvent(event)) return;

    if (isConsentEventType(event, 'consent_status_change')) {
        // event.eventProperties is ConsentDTO
    }
});
```

### Event reference

All events carry a monotonically increasing `timestamp` (at least 1 ms apart) to allow reliable ordering.

#### `consent_status_change`

Dispatched when `consentStore.accept()` or `consentStore.revoke()` completes. The payload is the full updated `ConsentDTO`.

```typescript
{
    eventName: 'consent_status_change';
    eventProperties: ConsentDTO;
    timestamp: Date;
}
```

#### `consent_modal_viewed` _(internal)_

Dispatched when `sw-settings-usage-data-consent-modal` becomes visible.

```typescript
{
    eventName: 'consent_modal_viewed';
    eventProperties: {
        consents_shown: Array<'backend_data' | 'product_analytics'>;
    };
    timestamp: Date;
}
```

#### `consent_modal_decision` _(internal)_

Dispatched when the user clicks a confirm/decline button in the consent modal.

```typescript
{
    eventName: 'consent_modal_decision';
    eventProperties: {
        backend_data?: {
            status: ConsentDTO['status'];
            changed: boolean;
        };
        product_analytics: {
            status: ConsentDTO['status'];
            changed: boolean;
        };
        time_spent_on_modal: number;  // ms
    };
    timestamp: Date;
}
```

#### `consent_legal_link_clicked` _(internal)_

Dispatched when a user clicks a privacy-policy or data-sharing-policy link in any consent UI.

```typescript
{
    eventName: 'consent_legal_link_clicked';
    eventProperties: {
        link_target: 'privacy_policy' | 'data_use_details';
        source: 'modal' | 'setting' | 'user';
    };
    timestamp: Date;
}
```

## Single-option consent UI

When only one consent type is applicable (`showStoreDataConsent === false`), the consent modal exposes explicit **Accept** / **Decline** action buttons instead of a toggle switch. The `sw-settings-usage-data-user-data-consent-card` component accepts a `hideSwitch` prop (boolean, default `false`) to suppress the toggle in this scenario.

## Consent event forwarding to Amplitude

The Amplitude consumer (`amplitude.init.ts`) forwards all `consent` bus events to the anonymous analytics gateway **without** requiring prior consent. This allows the analytics pipeline to record the fact that consent was changed, even when product analytics is currently off.

## Cross-tab state

Consent state changes are broadcast across browser tabs via `broadcast-changes.ts`, which uses the `BroadcastChannel` API (key: `sw-consent-changes`). When a consent change is detected in another tab, the store's `update()` action is called to re-fetch current state from the backend.
