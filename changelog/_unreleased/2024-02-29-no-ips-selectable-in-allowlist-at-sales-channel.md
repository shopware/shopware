---
title: No ips selectable in allowlist at sales channel
issue: NEXT-34060
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: Niklas Limberg
---
# Administration
* Changed `sw-sales-channel-detail-domains/index.js` to only access `currentDomain.isNew()` in `currentDomainModalTitle` and `currentDomainModalButtonText` if it is defined
* Changed `sw-sales-channel-detail-base.html.twig` to use the renamed computed property `maintenanceIpAllowlist`
