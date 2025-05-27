---
title: Fix sorting of countries in Storefront
issue: 5413
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
author_github: @mstegmeyer
---
# Core
* Deprecated `Shopware\Core\System\Country\CountryCollection::sortByPositionAndName()`, use SQL field sorting instead.
* Deprecated `Shopware\Core\System\Country\CountryCollection::sortCountryAndStates()`, use SQL field sorting instead.
* Deprecated `Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection::sortByPositionAndName()`, use SQL field sorting instead.
