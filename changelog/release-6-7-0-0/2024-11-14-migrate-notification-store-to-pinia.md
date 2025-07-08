---
title: migrate-notification-store-to-pinia
issue: NEXT-38615
author: Iván Tajes Vidal
author_email: tajespasarela@gmail.com
author_github: @Iván Tajes Vidal
---
# Administration
* Removed the `notification` store written in Vuex (replaced with a Pinia store)
* Added a new `notification` store written in Pinia
___
# Upgrade Information
## "notification" Vuex store moved to Pinia

The notification store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopware.Store.get('notification')`.

### Before:
```js
Shopware.State.get('notification');

Shopware.State.commit('notification/createNotification', notification);
```

### After:
```js
Shopware.Store.get('notification');

Shopware.Store.get('notification').createNotification(notification);
```

## Removed `getNotificationsObject` getter from `notification` store

The `getNotificationsObject` getter has been removed from the `notification` store. Instead, you can now directly access the `notifications` state.

### Before:
```js
Shopware.State.getters['notification/getNotificationsObject']
```

### After:
```js
Shopware.Store.get('notification').notifications
```

## Removed `getGrowlNotificationsObject` getter from `notification` store

The `getGrowlNotificationsObject` getter has been removed from the `notification` store. Instead, you can now directly access the `growlNotifications` state.

### Before:
```js
Shopware.State.getters['notification/getGrowlNotificationsObject']
```

### After:
```js
Shopware.Store.get('notification').growlNotifications
```
