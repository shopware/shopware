---
title: Add seller country to basic information settings
issue: 12438
---
# Core
* Added the `sellerCountryId` country select to `src/Core/System/Resources/config/basicInformation.xml`, stored per sales channel as `core.basicInformation.sellerCountryId`.
___
# Upgrade Information
## Seller country in the basic information settings
`Settings > Basic information` offers a new "Shop owner's country" select above the shop owner's address, readable via `SystemConfigService::get('core.basicInformation.sellerCountryId', $salesChannelId)`.
Nothing evaluates it yet, so setting or leaving it empty changes neither tax calculation, document rendering nor storefront output.
