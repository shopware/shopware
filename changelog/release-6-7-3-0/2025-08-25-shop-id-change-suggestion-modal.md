---
title: Shop ID change suggestion modal
issue: #6749
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: @fschmtt
---
# Core
* Changed command `app:url-change:resolve` to `app:shop-id:change`, adding and deprecating `app:url-change:resolve` as an alias.
* Added command `app:shop-id:check` to inspect the current shop identifier and check if a change is suggested.
___
# Administration
* Changed component `sw-app-app-url-changed-modal` to `sw-app-shop-id-change-modal`.
___
# Next Major Version Changes
## Removal of `app:url-change:resolve` command alias
* Use `app:shop-id:change` instead of `app:url-change:resolve`
