---
title: Show extension update notification if permissions to update those are given
issue: NEXT-25895
author: Moritz Krafeld
author_email: m.krafeld@shopware.com
author_github: Moritz Krafeld
---
# Administration
* Changed `src/core/service/plugin-update-listener` to typescript.
* Changed `src/core/service/plugin-update-listener` to only throw the notification if the user has the permission to update extensions.
