---
title: Login guests with their ID
issue: NEXT-17934
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: fschmtt
---
# Storefront
* Changed `Shopware\Storefront\Page\Account\Order\AccountOrderPageLoader::load()` to login guests using `Shopware\Core\Checkout\Customer\SalesChannel\AccountService::loginById()` rather than their email address
