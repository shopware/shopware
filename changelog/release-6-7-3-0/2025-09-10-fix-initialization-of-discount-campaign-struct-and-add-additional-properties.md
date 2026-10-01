---
title: Fix initialization of DiscountCampaignStruct and add additional properties 
author: Kai Gossel
author_email: k.gossel@shopware.com
author_github: kaigossel
---
# Core
* Changed `\Shopware\Core\Framework\Store\Struct\VariantStruct::fromArray` to initialize discount campaign objects by calling `\Shopware\Core\Framework\Store\Struct\DiscountCampaignStruct::fromArray`.
* Changed `\Shopware\Core\Framework\Store\Struct\DiscountCampaignStruct::fromArray` to correctly initialize `\Shopware\Core\Framework\Store\Struct\DiscountCampaignStruct::$startDate` and `\Shopware\Core\Framework\Store\Struct\DiscountCampaignStruct::$endDate` as `DateTimeImmutable`.
* Added additional `\Shopware\Core\Framework\Store\Struct\VariantStruct` properties `$duration`, `$netPricePerMonth` for future use
* Added additional `\Shopware\Core\Framework\Store\Struct\DiscountCampaignStruct` property `$discountedPricePerMonth` for future use
___
# Administration
* Changed TypeScript interfaces in `module/sw-extension/service/extension-store-action.service.ts` to match properties for `\Shopware\Core\Framework\Store\Struct\VariantStruct`, `\Shopware\Core\Framework\Store\Struct\DiscountCampaignStruct`
