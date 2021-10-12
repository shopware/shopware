---
title: Remove check of variable storageExists in CartWidgetPlugin
issue:
author: Sebastian König
author_email: s.koenig@tinect.de
author_github: @tinect
___
# Storefront
* Changed method `insertStoredContent` of `CartWidgetPlugin` to ignore variable `_storageExists` which never exists
