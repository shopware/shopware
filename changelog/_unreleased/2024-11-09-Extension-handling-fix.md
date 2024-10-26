---
title: Extension handling fix
issue: NEXT-00000
author: Gandalf Volker
author_email: gandalf@nuonic.de
author_github: g-volker
---
# Core
* Changed `\Shopware\Core\Checkout\Promotion\Cart\PromotionDeliveryCalculator::calculate` to retrieves the extension from the old promotion line item and adds it to the new promotion line item in order for it to not be created again later.
