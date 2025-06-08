---
title: Replace Vuex with Pinia
date: 2024-06-17
area: admin
tags: [admin, vuex, pinia]
---

# ADR: Replace Vuex with Pinia
## Context
It was brought to our attention that the latest version of Vuex `4.1.0` contains a bug that destroys getter reactivity under specific circumstances. The proposed fix was to downgrade to `4.0.2`. However, downgrading was not possible as `4.0.2` contains other bugs that caused modules to fail.

## Decision
Pinia is the new documented standard with Vue 3; therefore, we will switch to Pinia.

## Consequences
### Removal of Vuex
Below you will find an overview of what will be removed on which Shopware Version.

#### 6.7
For Shopware 6.7 we want to transition all our modules but still leave the possibility for you to use Vuex for your own states.

- All `Shopware.State` functions will cause warnings to appear in the DevTools. For example `Shopware.State.registerModule is deprecated. Use Shopware.Store.register instead!`
- All Vuex state definitions will be transitioned to Pinia:
    - src/module/sw-bulk-edit/state/sw-bulk-edit.state.js
    - src/module/sw-product/page/sw-product-detail/state.js
    - src/module/sw-category/page/sw-category-detail/state.js
    - src/module/sw-extension/store/extensions.store.ts
    - src/module/sw-settings-payment/state/overview-cards.store.ts
    - src/module/sw-settings-seo/component/sw-seo-url/state.js
    - src/module/sw-settings-shipping/page/sw-settings-shipping-detail/state.js
    - src/app/state/notification.store.js
    - src/app/state/session.store.js
    - src/app/state/system.store.js
    - src/app/state/admin-menu.store.js
    - src/app/state/admin-help-center.store.ts
    - src/app/state/license-violation.store.js
    - src/app/state/context.store.ts
    - src/app/state/error.store.js
    - src/app/state/settings-item.store.js
    - src/app/state/shopware-apps.store.ts
    - src/app/state/extension-entry-routes.js
    - src/app/state/marketing.store.js
    - src/app/state/extension-component-sections.store.ts
    - src/app/state/extensions.store.ts
    - src/app/state/tabs.store.ts
    - src/app/state/menu-item.store.ts
    - src/app/state/extension-sdk-module.store.ts
    - src/app/state/modals.store.ts
    - src/app/state/main-module.store.ts
    - src/app/state/action-button.store.ts
    - src/app/state/rule-conditions-config.store.js
    - src/app/state/sdk-location.store.ts
    - src/app/state/usage-data.store.ts
    - src/module/sw-flow/state/flow.state.js
    - src/module/sw-order/state/order.store.ts
    - src/module/sw-order/state/order-detail.store.js
    - src/module/sw-profile/state/sw-profile.state.js
    - src/module/sw-promotion-v2/page/sw-promotion-v2-detail/state.js

#### 6.8
With Shopware 6.8 we will entirely remove everything Vuex related including the dependency.

- `Shopware.State` - Will be removed. Use `Shopware.Store` instead.
- `src/app/init-pre/state.init.ts` - Will be removed. Use `src/app/init-pre/store.init.ts` instead.
- `src/core/factory/state.factory.ts` - Will be removed without replacement.
- Interface `VuexRootState` will be removed from `global.types.ty`. Use `PiniaRootState` instead.
- Package `vuex` will be removed.


## Transition to Pinia
Pinia calls its state-holding entities `stores`. Therefore, we decided to hold everything Pinia-related under `Shopware.Store`.
The `Shopware.Store` implementation follows the Singleton pattern. The private constructor controls the creation of the Pinia root state.
This root state must be injected into Vue before the first store can be registered. The `init-pre/store.init.ts` takes care of this.

### Best practices
1. All Pinia Stores must be written in TypeScript
2. All Stores will export a type or interface like the `cms-page.state.ts`
3. The state property of the exported type must be reused for the state definition.

You can always orientate on the `cms-page.state.ts`. It contains all best practices. 

For now, we have decided to limit the public API of `Shopware.Store` to the following:

```typescript
/**
 * Returns a list of all registered Pinia store IDs.
 */
public list(): string[];

/**
 * Gets the Pinia store with the given ID.
 */
public get(id: keyof PiniaRootState): PiniaStore;

/**
 * Registers a new Pinia store. Works similarly to Vuex's registerModule.
 */
public register(options: DefineStoreOptions): void;

/**
 * Unregisters a Pinia store. Works similarly to Vuex's unregisterModule.
 */
public unregister(id: keyof PiniaRootState): void;
```

The rest of the previous Vuex (`Shopware.State`) public API is implemented into Pinia itself.

```typescript
// Setup
const piniaStore = Shopware.Store.get('...');

// From Vuex subscribe
Shopware.State.subscribe(...);
// To Pinia $subscribe
store.$subscribe(...);

// From Vuex commit
Shopware.State.commit(...);
// To Pinia action call
store.someAction(...);

// From Vuex dispatch
Shopware.State.dispatch(...);
// To Pinia action call
store.someAsyncAction(...);
```

### Example Implementation
To prove that Vuex and Pinia can co-exist during the transition period, we picked a private Vuex state and decided to transition it.
We chose the `cmsPageState`, which is heavily used in many components. The transition went smoothly without any major disturbances.

How to transition a Vuex module into a Pinia store:
1. In Pinia, there are no `mutations`. Place every mutation under `actions`.
2. `state` needs to be an arrow function returning an object: `state: () => ({})`.
3. `actions` no longer need to use the `state` argument. They can access everything with correct type support via `this`.
4. Point 3 also applies to `getters`.
5. Use `Shopware.Store.register` instead of `Shopware.State.registerModule`.

Let's look at a simple Vuex module and how to transition it:
```typescript
// Old Vuex implementation
Shopware.State.registerModule('example', {
    state: {
        id: '',
    },
    getters: {
        idStart(state) {
            return state.id.substring(0, 4);
        }
    },
    mutations: {
        setId(state, id) {
            state.id = id;
        }
    },
    actions: {
        async asyncFoo({ commit }, id) {
            // Do some async stuff
            return Promise.resolve(() => {
                commit('setId', id);
                
                return id;
            });
        }
    }
});

// New Pinia implementation
// Notice that the mutation setId was removed! You can directly modify a Pinia store state after retrieving it with Shopware.Store.get.
Shopware.Store.register({
    id: 'example',
    state: () => ({
        id: '',
    }),
    getters: {
        idStart: () => this.id.substring(0, 4),
    },
    actions: {
        async asyncFoo(id) {
            // Do some async stuff
            return Promise.resolve(() => {
                this.id = id;

                return id;
            });
        }
    }
});
```
