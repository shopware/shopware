---
title: Fix missing privileges error in admin extension sdk repository
issue: NEXT-29862
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Changed the context handling within the Admin Extension SDK repository's methods to prevent sending unnecessary entities, which were leading to "missing privileges" errors.
___
# Upgrade Information
The upcoming release includes a crucial breaking change aimed at resolving a major issue in how repository data is handled for the Admin Extension SDK. This change will be introduced in the next minor version.

The change only affects your app if you are utilizing properties within the context. With this modification, an empty context object will be returned within your Entity. You will receive a custom context object containing your specific context changes only when you also send a custom context object to the admin.

The final context will be merged with the default context in the administration. This allows you to use the default context to access all the necessary data.
