---
title: Remove country association in context factory
issue: NEXT-12815
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Removed `countries` association in sales channel criteria which is used to fetch the sales channel in the `\Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory`
___
# Upgrade Information
## context.salesChannel.countries removed
Previously, the sales channel object in the context contained all countries assigned to the sales channel. This data has now been removed. The access via `$context->getSalesChannel()->getCountries()` therefore no longer returns the previous result.
To load the countries of a sales channel, the class `\Shopware\Core\System\Country\SalesChannel\CountryRoute` should be used.
