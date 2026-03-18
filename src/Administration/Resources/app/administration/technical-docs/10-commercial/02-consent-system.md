# Consent System

The Administration includes a consent management system that allows reading and updating the consent states for shop-data processing in external services. The PHP core manages the consent records (see `Shopware\Core\System\Consent`); the Administration provides a Pinia store and an event bus integration to interact with them.

## ConsentDTO

Every consent is represented by a `ConsentDTO`:

```ts
type ConsentDTO = {
    readonly name: string;
    readonly identifier: string;
    readonly scopeName: 'system' | 'admin_user';
    readonly actor: string | null;
    readonly status: 'unset' | 'declined' | 'accepted' | 'revoked';
    readonly updated_at: string | null;
};
```

The `status` field reflects the lifecycle of a consent decision. `declined` means the user actively rejected the consent without it having been accepted first; `revoked` means a previously accepted consent was withdrawn.

## Reading consent state

Use `useConsentStore` to access the Pinia store:

```ts
import useConsentStore from 'src/core/consent/consent.store';

const consentStore = useConsentStore();

// reactive access
const status = computed(() => consentStore.consents?.your_consent.status ?? 'unset');

// one-off check
if (consentStore.isAccepted('your_consent')) {
    // consent is currently accepted
}
```

## Updating consent state

Use the store actions `accept` and `revoke`. Both call the backend API, update the local store state across tabs, and dispatch a `consent_status_change` event on the Admin event bus. Do not call `consentApiService` directly.

```ts
import useConsentStore from 'src/core/consent/consent.store';

const consentStore = useConsentStore();

await consentStore.accept('your_consent');
await consentStore.revoke('your_consent');
```

`accept` is a no-op when the consent is already `accepted`. `revoke` is a no-op when it is already `revoked` or `declined`.

## Consent events

All consent activity is emitted on the Admin global event bus under the `'consent'` channel. Events are only emitted when the `PRODUCT_ANALYTICS` feature flag is active.

```ts
import { isConsentEvent, isConsentEventType } from 'src/core/consent/events';

Shopware.Utils.EventBus.on('consent', (event: unknown) => {
    if (!isConsentEvent(event)) return;

    if (isConsentEventType(event, 'consent_status_change')) {
        console.log(event.eventProperties.name, event.eventProperties.status);
    }
});

// unsubscribe
Shopware.Utils.EventBus.off('consent', handler);
```

### Type guard helpers

| Function | Purpose |
|---|---|
| `isConsentEvent(event)` | Narrows `unknown` to `ConsentEvent<ConsentEventName>` |
| `isConsentEventType(event, name)` | Further narrows to the specific event type |

### Event reference

#### `consent_status_change`

Dispatched by the store whenever `accept()` or `revoke()` successfully completes. This is the primary event for reacting to consent changes.

```ts
{
    eventName: 'consent_status_change';
    eventProperties: ConsentDTO; // full updated consent record
    timestamp: Date;
}
```

#### `consent_modal_viewed` *(internal)*

Dispatched when the `sw-settings-usage-data-consent-modal` is shown.

```ts
{
    eventName: 'consent_modal_viewed';
    eventProperties: {
        consents_shown: Array<'backend_data' | 'product_analytics'>;
    };
    timestamp: Date;
}
```

#### `consent_modal_decision` *(internal)*

Dispatched when the user confirms their choices in the consent modal (Save, Share All, or Share Nothing). Contains the resulting status and whether each consent actually changed.

```ts
{
    eventName: 'consent_modal_decision';
    eventProperties: {
        backend_data?: {          // omitted when the setting is not visible
            status: 'unset' | 'declined' | 'accepted' | 'revoked';
            changed: boolean;
        };
        product_analytics: {
            status: 'unset' | 'declined' | 'accepted' | 'revoked';
            changed: boolean;
        };
        time_spent_on_modal: number; // seconds
    };
    timestamp: Date;
}
```

#### `consent_legal_link_clicked` *(internal)*

Dispatched when the user clicks on the privacy policy or data-use-details link.

```ts
{
    eventName: 'consent_legal_link_clicked';
    eventProperties: {
        link_target: 'privacy_policy' | 'data_use_details';
        source: 'modal' | 'setting' | 'user';
    };
    timestamp: Date;
}
```

## Amplitude integration

The `amplitude.init` post-initializer subscribes to the `'consent'` channel and forwards `consent_modal_viewed`, `consent_modal_decision`, `consent_status_change` (for `backend_data` and `product_analytics` only), and `consent_legal_link_clicked` events to Amplitude using the anonymous (pre-consent) client. The internal modal events are not part of any public API; only `consent_status_change` is intended for use by extensions in the future.

## Known consent names

| Name | Scope | Description |
|---|---|---|
| `backend_data` | `system` | Sharing of backend performance and diagnostic data |
| `product_analytics` | `admin_user` | Product analytics / user-interaction tracking |
