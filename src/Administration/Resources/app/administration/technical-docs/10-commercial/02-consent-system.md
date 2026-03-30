# Consent System

The consent system manages user consent for data collection features (e.g. product analytics, backend diagnostics). It persists decisions server-side and broadcasts them within the Administration via a typed event bus.

**Source files:**
- `src/core/consent/consent.store.ts` — Pinia store, server sync, `isAccepted()` guard
- `src/core/consent/events.ts` — `ConsentEvent`, `dispatchConsentEvent`, type guards

---

## Data Model — `ConsentDTO`

```typescript
type ConsentDTO = {
    readonly name: string;
    readonly identifier: string;
    readonly scopeName: 'system' | 'admin_user';
    readonly actor: string | null;
    readonly status: 'unset' | 'declined' | 'accepted' | 'revoked';
    readonly updated_at: string | null;
};
```

| Field | Description |
|---|---|
| `name` | Logical identifier used throughout the front-end (e.g. `'product_analytics'`, `'backend_data'`) |
| `identifier` | Unique server-side key |
| `scopeName` | `'system'` — shared across all admin users; `'admin_user'` — per-user |
| `actor` | User or system that last changed the consent; `null` if unset |
| `status` | Lifecycle state; `'unset'` is the initial state before any decision is made |

---

## Consent Store (`useConsentStore()`)

Registered as the Pinia store `'consent'`. Access via `useConsentStore()` (composable import) or `Shopware.Store.get('consent')` in service context.

### State

```typescript
type ConsentStoreState = {
    consents: Record<string, ConsentDTO>;
};
```

Keys in `consents` are the `name` field of each `ConsentDTO`.

### Actions

#### `update(): Promise<void>`

Fetches the current consent list from the server via `consentApiService.list()` and replaces the local state entirely. Called during application initialisation and after cross-tab updates.

#### `accept(name: string): Promise<void>`

- Throws if `name` is not in the store.
- No-ops if already `'accepted'`.
- Calls `consentApiService.accept(name)`, updates local state, dispatches `consent_status_change`.

#### `revoke(name: string): Promise<void>`

- Throws if `name` is not in the store.
- No-ops if already `'revoked'` or `'declined'`.
- Calls `consentApiService.revoke(name)`, updates local state, dispatches `consent_status_change`.

#### `isAccepted(name: string): boolean`

- Throws if `name` is not in the store.
- Returns `true` only when `status === 'accepted'`.

Used as a reactive guard in the product-analytics init:
```typescript
const isTelemetryConsentAccepted = computed(() => {
    try { return consentStore.isAccepted('product_analytics'); }
    catch { return false; }
});
```

---

## Consent Events

All consent events are emitted on `Shopware.Utils.EventBus` under the `'consent'` channel, wrapped in a `ConsentEvent<N>` envelope.

> **No feature flag gate**: Prior to `#15736`, event dispatch was gated behind `Feature.isActive('PRODUCT_ANALYTICS')`. This check has been removed. Consent events are now always dispatched.

### Event types

```typescript
type ModalConsents = 'backend_data' | 'product_analytics';
type ConsentAction = ConsentDTO['status'];

type ConsentEvents = {
    consent_modal_viewed: {
        consents_shown: ModalConsents[];
    };
    consent_modal_decision: {
        backend_data?: { status: ConsentAction; changed: boolean };
        product_analytics: { status: ConsentAction; changed: boolean };
        time_spent_on_modal: number;
    };
    consent_status_change: ConsentDTO;
    consent_legal_link_clicked: {
        link_target: 'privacy_policy' | 'data_use_details';
        source: 'modal' | 'setting' | 'user';
    };
};
```

| Event | Emitted by | Description |
|---|---|---|
| `consent_modal_viewed` | Consent modal component | Fired when the modal is shown; lists which consents the user sees |
| `consent_modal_decision` | Consent modal component | Fired when the user confirms their choices; includes per-consent deltas and time on modal |
| `consent_status_change` | `consentStore.accept()` / `consentStore.revoke()` | Fired after any server-confirmed status change; payload is the updated `ConsentDTO` |
| `consent_legal_link_clicked` | Modal and settings | Fired when the user clicks a legal link |

### `ConsentEvent` envelope

```typescript
class ConsentEvent<N extends ConsentEventName> {
    readonly eventName: N;
    readonly eventProperties: ConsentEvents[N];
    readonly timestamp: Date; // monotonically increasing: no two events share the same ms
}
```

Timestamps are guaranteed unique via a static `#lastConsentEventTimestamp` ceiling.

### Type guards

```typescript
isConsentEvent(event: unknown): event is ConsentEvent<ConsentEventName>
isConsentEventType<N>(event: ConsentEvent<any>, name: N): event is ConsentEvent<N>
```

Used by `createConsentEventHandler` to narrow event types before forwarding to the gateway.

---

## Consent metrics routing

Consent events that need to be tracked anonymously (before user consent is granted) are forwarded by `createConsentEventHandler` (`src/core/telemetry/product-analytics/consent-event-handler.ts`) to `GatewayClient.trackConsentMetric()`, which posts to `{gatewayUrl}/v1/event/anonymous` without user identity.

Consent types that trigger this anonymous tracking: `consent_modal_viewed`, `consent_modal_decision`, `consent_status_change` (for `backend_data` and `product_analytics` only), `consent_legal_link_clicked`.

---

## See Also

- [Shopware.Telemetry](../05-global-object/04-telemetry.md)
- [Commercial Plugin](./01-commercial-plugin.md)
