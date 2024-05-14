---
title: Notification titles are pre-defined and make use of the global namespace
date: 2020-08-21
area: administration
tags: [administration, notification]
---

## Context

* Creating notification messages in the administration caused the effort of making up not only a title but a message too.
This has led to inconsistent notification appearances. In some cases, the notification message simply duplicated the title; 
others wore the module's name as a title and so on.

* Now, since it is a set design decision to use the following four types of notification as titles at the same time, 
it is just logical to make use of the global namespace and manage notification titles centrally.
                                                                                     
    * `Success` (green outline)
    * `Error` (red outline)
    * `Info` (blue outline)
    * `Warning` (orange outline)

## Decision

* Implement a global default title for all notifications types in

`/platform/src/Administration/Resources/app/administration/src/app/mixin/notification.mixin.js` 

* Remove the superfluous title definitions and snippets

## Consequences

* By introducing the global namespace as early as in the `notification.mixin.js`
it is now unnecessary to define individual titles when implementing notifications within a module.
* Notifications from now on only require a "notification message" and thus the creation of only snippet within each snippet file (en-GB and de-DE).
* Consequently, a bunch of unused snippets have been removed.
For more information on snippets deleted in this course see CHANGELOG-6.3.md

### Examples

* Create error notification
```js
this.createNotificationError({
    message: this.$tc('sw-module.messageError')
});
```
* Create error message snippets (DE/EN)
```json
    "messageError": "Meaningful error message.",
```

* Avoid cheap solutions like 
```js
this.createNotificationError({
    message: err
});
```
### Best practice

* Messages should be translatable, precise and not redundant. An error notification's title literally says: "Error" - no need in repeating that. 
Better find and present information on what exactly went wrong.

* Make use of success notifications, but make them carry useful information, by e.g., including counters.

* Make use of info and warning notifications to keep users informed about things that are ongoing or need a closer look!

* As it is still possible to override the mixin presets with these individual settings, it is theoretically still possible to define individual titles. 
It would cross the design idea of unified titles though and should only be considered for very good reasons!

## tl;dr

> *When creating notifications, just decide on the correct type of notification, 
 add a meaningful message, don't waste even a thought on creating a title...
 And you're done!*
