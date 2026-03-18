# Consent System

The Administration includes a consent management layer that reads and updates user consent decisions for external data-processing services. It mirrors the PHP-side `Shopware\Core\System\Consent` namespace and provides a reactive Pinia store plus an event bus integration.

All consent events are gated behind the `PRODUCT_ANALYTICS` feature flag.

Source: `src/core/consent/`

## ConsentDTO

Every consent entry is represented as an immutable value object:

```typescript
type ConsentDTO = {
    readonly name: string;               // e.g. 'backend_data'
    readonly identifier: string;         // UUID
    readonly scopeName: 'system' | 'admin_user';
    readonly actor: string | null;       // User ID who last changed the status
    readonly status: 'unset' | 'declined' | 'accepted' | 'revoked';
    readonly updated_at: string | null;  // ISO date string
};
```

Known consent names in the core:

| Name | Scope | Purpose |
|---|---|---|
| `backend_data` | `system` | Shop analytics sent from the PHP backend |
| `product_analytics` | `admin_user` | Admin UI usage analytics (Amplitude) |

## Reading Consent State

Use the `useConsentStore` composable to access the current consent state reactively:

```typescript
import useConsentStore from 'src/core/consent/consent.store';

const consentStore = useConsentStore();

// Reactive reference — updates when the consent changes
const isProductAnalyticsAccepted = computed(() =>
    consentStore.consents?.product_analytics?.status === 'accepted'
);

// One-shot check — throws if the consent name does not exist in the store
if (consentStore.isAccepted('product_analytics')) {
    // analytics are enabled
}
```

`consentStore.consents` is a `Record<string, ConsentDTO>` that is populated by calling `consentStore.update()` (done automatically during post-init).

## Updating Consent State

Always use the store actions — do not call `consentApiService` directly. The actions keep the store synchronized and dispatch the corresponding event bus event.

```typescript
import useConsentStore from 'src/core/consent/consent.store';

const consentStore = useConsentStore();

// Accept a consent (no-op if already accepted)
await consentStore.accept('product_analytics');

// Revoke a consent (no-op if already revoked or declined)
await consentStore.revoke('product_analytics');
```

Both actions throw if the consent name is not found in the store.

## Event Bus Integration

The consent system emits events on the global `EventBus` under the `'consent'` channel:

```typescript
import { isConsentEvent, isConsentEventType } from 'src/core/consent/events';

Shopware.Utils.EventBus.on('consent', (event) => {
    if (!isConsentEvent(event)) return;

    if (isConsentEventType(event, 'consent_status_change')) {
        const dto = event.eventProperties; // ConsentDTO
        console.log(dto.name, dto.status);
    }
});

Shopware.Utils.EventBus.off('consent', handler);
```

### Event Types

#### `consent_status_change`

Dispatched by `consentStore.accept()` and `consentStore.revoke()` after a successful API call.

```typescript
{
    eventName: 'consent_status_change';
    eventProperties: ConsentDTO;
    timestamp: Date;
}
```

#### `consent_modal_viewed` _(internal)_

Dispatched when the `sw-settings-usage-data-consent-modal` opens.

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

Dispatched when the user clicks a footer button to confirm or dismiss the consent modal.

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
        time_spent_on_modal: number; // milliseconds
    };
    timestamp: Date;
}
```

#### `consent_legal_link_clicked` _(internal)_

Dispatched when a privacy-policy or data-use link is clicked inside a consent UI component.

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

## Type Guards

Two helpers narrow the type of event bus payloads:

```typescript
import { isConsentEvent, isConsentEventType } from 'src/core/consent/events';

// Returns true if the value is a ConsentEvent instance
isConsentEvent(event);

// Returns true and narrows the event type to ConsentEvent<N>
isConsentEventType(event, 'consent_status_change');
```

## Event Ordering

`ConsentEvent` enforces strictly monotonically increasing timestamps: if multiple events are dispatched within the same millisecond, each subsequent event's timestamp is incremented by 1 ms. This guarantees that consumers can always sort events by `timestamp` to reconstruct the correct order.

## Cross-Tab Synchronization

Consent changes are broadcast to other open tabs via the `BroadcastChannel` API (`src/core/consent/broadcast-changes.ts`). Each tab listening to the `'consent'` channel receives the updated `ConsentDTO` and writes it into its local store without issuing another API request.

## Feature Flag

All event dispatching and broadcast-channel logic checks `Shopware.Feature.isActive('PRODUCT_ANALYTICS')` before executing. When the flag is off, the store still works as a plain data container but emits no events.
