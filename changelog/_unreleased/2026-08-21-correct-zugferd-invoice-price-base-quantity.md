---
title: Correct ZUGFeRD invoice price base quantity
issue: 19289
author: Lars Kemper
author_email: l.kemper@shopware.com
---
# Core
* Changed the ZUGFeRD line position price base quantity (BT-149) in `Shopware\Core\Checkout\Document\Zugferd\ZugferdDocument` to always be `1` instead of the product's `purchaseUnit`. The purchase unit is the package content used for base price display, so writing it as BT-149 made every line whose product has a purchase unit other than 1 violate `PEPPOL-EN16931-R120`. The item net price (BT-146) is now written with 4 decimals instead of 2, so the line amount calculation stays within the rule's rounding tolerance for higher quantities.
