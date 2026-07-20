---
title: Fixed promotion deletion cart error
issue: 8285
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Core
* Changed `src/Core/Checkout/Promotion/Cart/PromotionCollector.php` to only use line items of type promotion for deletion notices.
