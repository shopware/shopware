---
title: Add inclusive-language eslint rule to admin
issue: NEXT-16185
author: Raoul Kramer
author_email: r.kramer@shopware.com 
author_github: djpogo
---
# Administration
* Added `eslint-plugin-inclusive-language` package to our eslint ruleset and applying it where it is non-breakable
* Deprecated computed property `maintenanceIpWhitelist` on `/src/module/sw-sales-channel/view/sw-sales-channel-detail-base/index.js` - instead use `maintenanceIpAllowlist` from version `6.6.0.0`
___
# Upgrade Information
## Replace computed property usage
Replace `maintenanceIpWhitelist` with `maintenanceIpAllowlist`