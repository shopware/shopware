---
title: Fix DeliveryPosition taxes override LineItem taxes
issue: NEXT-25062
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Core
* Changed `DeliveryBuilder` to clone line item prices instead of reference them to allow independent price changes
