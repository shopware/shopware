---
title: Check if currentUser is loaded when checking acls on dashboard
issue: NEXT-15389
author: Raoul Kramer
author_email: r.kramer@shopware.com 
author_github: @djpogo
---
# Administration
* Added a `userPending` flag to `session.sotre.js` to see whether an `acl.can()` check runs against a `null`ish `currentUser`, which leads to a faulty `false` return, or if the `currentUser` is loaded properly and `cal.can()` returns correct `true` or `false` values.
* Changed `SwDashboard` `createdComponent()` function, to double check a falsy `acl.can()` return for _real_ falsyness or _false_ falsyness if `currentUser` is null in `Shopware.State.get('session')` via `userPending` flag.
