# Consent system in the Administration

Becoming a service-driven product, it becomes more and more important to request a consent to process shop data in external services.
In the PHP core we created a system to manage user's consent decisions (see the `Shopware\Core\System\Consent` namespace).
In the Administration, we can now use this system to read and update the consent states of the users.

## Read a consent state

Everything you need to read a consent state and react to consent changes is available in the `useConsentStore` composable.
The pinia store returned from it, gives you access to read a consent's state.

Each consent entry contains revision metadata in addition to the raw status:

- `acceptedRevision`: the revision that was accepted last, or `null`
- `latestRevision`: the current revision defined by the backend, or `null`

`consentStore.isAccepted()` is revision-aware:

- if a consent has no revisions, `status === 'accepted'` is enough
- if a consent has revisions, `isAccepted()` only returns `true` when `acceptedRevision === latestRevision`

> **Note:** this differs from the PHP `ConsentState::isAccepted()`, which only checks the status and is not revision-aware. The JS `isAccepted()` is equivalent to the PHP `isCurrent()` method. If you need the raw status check without revision awareness, read `consent.status === 'accepted'` directly.

If you need to distinguish "accepted, but outdated" from "not accepted", use `consentStore.isStale()`.

```ts
import useConsentStore from 'src/core/consent/consent.store'

const consentStore = useConsentStore();

// create reactive state of your consent
const consentState = computed(() => consentStore.consents?.your_consent.status ?? false);

// do a single check on the consent
if (consentStore.isAccepted('your_consent')) {
    // do something
}

if (consentStore.isStale('your_consent')) {
    // consent was accepted before, but not for the current revision
}
```

## Update a consent state

To update the current state of a consent, it is mandatory to use the actions `accept` and `revoke` from the store.
This will also update the state across all tabs in the browser. Don't use the api services directly.

The store intentionally accepts without sending a cached revision from the client. The backend resolves the current latest revision server-side, which avoids avoidable failures when the latest revision changed after the last `list()` call.

```ts
import useConsentStore from 'src/core/consent/consent.store'

const consentStore = useConsentStore();

// accept a consent
consentStore.accept('your_consent');

// revoke or decline a consent
consentStore.revoke('your_consent');
```

## Consent Events

We dispatch events on consent changes, via the Admin's global event bus.
To listen to these events, you can use the `on` method of the event bus.

```ts
import type { ConsentEvent } from 'src/core/consent/events';

const eventHandler = (event: ConsentEvent) => { /* handle event */ };

// subscribe to consent events
Shopware.Utils.EventBus.on('consent', eventHandler);

// unsubscribe from events
Shopware.Utils.EventBus.off('consent', eventHandler);
```

### Event types

- **consent_status_change**: This event is dispatched whenever a consent's status changes. The event payload is the updated consent status:
```ts
{
    eventName: 'consent_status_change';
    {
        name: string;
        identifier: string;
        scopeName: 'system' | 'admin_user';
        actor: string | null;
        status: 'unset' | 'declined' | 'accepted' | 'revoked';
        updatedAt: string | null;
        acceptedRevision: string | null;
        latestRevision: string | null;
    };
    timestamp: Date;
}
```
* **consent_modal_viewed** (internal): This event is dispatched when the `sw-settings-usage-data-consent-modal` component is shown.
```ts
{
    eventName: 'consent_modal_viewed';
    eventProperties: {
        consents_shown:  Array<'backend_data' | 'product_analytics'>;
    };
    timestamp: Date;
}
```
- **consent_modal_decision** (internal): This event is dispatched when the `sw-settings-usage-data-consent-modal` component is closed with a click on the buttons of its footer. The event payload contains the consents that were accepted and declined in the modal.
```ts
{
    eventName: 'consent_modal_decision';
    eventProperties: {
        backend_data?: {
            status: ConsentAction;
            changed: boolean;
        };
        product_analytics: {
            status: ConsentAction;
            changed: boolean;
        };
        time_spent_on_modal: number;
    };
    timestamp: Date;
}
```
- **consent_legal_link_clicked** (internal): This event is dispatched when links to our data privacy page or our data sharing policy are clicked.
```ts
{
    eventName: 'consent_modal_decision';
    eventProperties: {
        link_target: 'privacy_policy' | 'data_use_details';
        source: 'modal' | 'setting' | 'user';
    };
    timestamp: Date;
}
```

## Further steps

In the future we want to make this system available to all Shopware Extensions via the Meteor Admin-SDK.

For our internal consents (backend_data and product_analytics) we currently dispatch events on consent changes, to react to these changes in our services.
This should be available for Shopware Extensions, too 
