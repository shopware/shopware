---
title: Replaced link in payment warning on sales channel detail page to point to payment settings instead of payment detail page
issue: NEXT-21126
author: Patrick Stahl
author_email: p.stahl@shopware.com
author_github: PaddyS
---
# Administration
* Changed the link `sw-sales-channel-detail-base.html.twig` to no longer point to a payment detail page, but to the payment listing instead
* Added method `buildDisabledShippingAlert` in `sw-sales-channel-detail-base/index.js` to properly build the alert for inactive shipping methods
