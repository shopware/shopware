---
title: Create a new customer rule conditions for flow builder
issue: NEXT-18200
---
# Core
* Added new rule conditions in `Shopware\Core\Checkout\Customer\Rule`:
  * `CustomerAgeRule`
  * `DaysSinceLastLoginRule`
  * `AffiliateCodeRule`
  * `CampaignCodeRule`
___
# Administration
* Added new rule conditions to the `condition-type-data-provider.decorator`:
  * `customerAge`
  * `customerDaysSinceLastLogin`
  * `customerAffiliateCode`
  * `customerCampaignCode`
