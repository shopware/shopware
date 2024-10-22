---
title: Replace "marketing" Vuex store with Pinia store
issue: NEXT-38629
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: @Jannis Leifeld
---
# Administration
* Removed the `marketing` store written in Vuex (replaced with a Pinia store)
* Added a new `marketing` store written in Pinia
___
# Upgrade Information
## "marketing" Vuex store moved to Pinia

The marketing store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopware.Store.get('marketing')`.

### Before:
```js
Shopware.State.get('marketing');

Shopware.State.commit('marketing/setCampaign', campaign);
```

### After:
```js
Shopware.Store.get('marketing');

Shopware.Store.get('marketing').setCampaign(campaign);
```
