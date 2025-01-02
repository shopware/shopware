---
title: Improve SalesChannelContext/Context usage
issue: NEXT-00000
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerPasswordMatches` to validate the options before using them
* Changed `Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentMethodIndexer` to clone context instead of recreating it by struct
* Changed `Shopware\Core\Content\Seo\SeoUrlUpdater` to skip empty language chains which would cause errors in context creation
* Changed `Shopware\Core\Test\Generator` to allow a more dynamic usage of the `createSalesChannelContext` function
