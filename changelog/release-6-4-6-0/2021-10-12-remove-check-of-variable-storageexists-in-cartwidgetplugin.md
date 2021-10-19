---
title: Remove check of variable storageExists in CartWidgetPlugin
issue: next-17937
author: Sebastian König
author_email: s.koenig@tinect.de
author_github: @tinect
---
# Storefront
* Changed method `insertStoredContent` of `CartWidgetPlugin` to ignore variable `_storageExists` which never exists
