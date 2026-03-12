# Consent system in the Administration

Becoming a service-driven product, it becomes more and more important to request a consent to process shop data in external services.
In the PHP core we created a system to manage user's consent decisions.
In the Administration, we can now use this system to read and update the consent states of the users.

## Read a consent state

Everything you need to read a consent state and react to consent changes is available in the `useConsentStore` composable.
The pinia store returned from it, gives you access to read a consent's state.

```ts
import useConsentStore from 'src/core/consent/consent.store'

const consentStore = useConsentStore();

// create reactive state of your consent
const consentState = computed(() => consentStore.consents?.your_consent.status ?? false);

// do a single check on the consent
if (consentStore.isAccepted('your_consent')) {
    // do something
}
```

## Update a consent state

To update the current state of a consent, it is mandatory to use the actions `accept` and `revoke` from the store.
This will also update the state across all tabs in the browser. Don't use the api services directly.

```ts
import useConsentStore from 'src/core/consent/consent.store'

const consentStore = useConsentStore();

// accept a consent
consentStore.accept('your_consent');

// revoke or decline a consent
consentStore.revoke('your_consent');
```

## Further steps

In the future we want to make this system available to all Shopware Extensions via the Meteor Admin-SDK.

For our internal consents (backend_data and product_analytics) we currently dispatch events on consent changes, to react to these changes in our services.
This should be available for Shopware Extensions, too 