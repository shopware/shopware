---
title: Added serialization of the itemRounding and totalRounding property of an order entity
issue: NEXT-33868
author: Simon Fiebranz
author_email: s.fiebranz@shopware.com
author_github: CR0YD
---
# Core
* Added serialization of `itemRounding` and `totalRounding` in `OrderSerializer`, which will now be converted to their json representation when serializing an order entity. This will affect the export of these values as they will now also be exported as json objects.
