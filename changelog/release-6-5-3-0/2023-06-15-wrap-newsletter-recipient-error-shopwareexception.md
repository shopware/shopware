---
title: Wrap Newsletter recipient error in ShopwareException
issue: NEXT-28566
---
# Storefront
* Added `Shopware\Core\Content\Newsletter\NewsletterException`.
* Changed `\Shopware\Storefront\Controller\NewsletterController::subscribeMail` to catch recipient error and redirect to frontpage.
