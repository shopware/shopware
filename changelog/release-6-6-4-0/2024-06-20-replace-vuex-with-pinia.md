---
title: Replace Vuex with Pinia
issue: NEXT-36700
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Added `Shopware.Store` (Pinia) implementation
* Changed everything `Shopware.State` related to deprecated state
___
# Upgrade Information
## Transition Vuex states into Pinia Stores
1. In Pinia, there are no `mutations`. Place every mutation under `actions`.
2. `state` needs to be an arrow function returning an object: `state: () => ({})`.
3. `actions` and `getters` no longer need to use the `state` as an argument. They can access everything with correct type support via `this`.
4. Use `Shopware.Store.register` instead of `Shopware.State.registerModule`.
5. Use `Shopware.Store.unregister` instead of `Shopware.State.unregisterModule`.
6. Use `Shopware.Store.list` instead of `Shopware.State.list`.
7. Use `Shopware.Store.get` instead of `Shopware.State.get`.
___
# Next Major Version Changes
## All Vuex stores will be transitioned to Pinia
* All Shopware states will become Pinia Stores and will be available via `Shopware.Store`
