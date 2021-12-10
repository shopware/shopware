---
title: Add form plugin to push to browser history before submitting
issue: NEXT-15291
author: Jakob Kruse
author_email: j.kruse@shopware.com 
author_github: jakob-kruse
---
# API
* Changed `allowGuest` to `true` in route `frontend.account.order.page`
___
# Storefront
* Added new `FormAddHistoryPlugin` form plugin `src/Storefront/Resources/app/storefront/src/plugin/forms/form-add-history.plugin.js`
* Changed checkout form in `src/Storefront/Resources/views/storefront/page/checkout/confirm/index.html.twig` to push `account/order` in history

