---
title: Fix sorting of countries in Storefront
issue: 5413
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
author_github: @mstegmeyer
---
# Core
* Deprecated `Shopware\Core\System\Country\CountryCollection::sortByPositionAndName()`, use a FieldSorting via the DAL or direct SQL instead.
* Deprecated `Shopware\Core\System\Country\CountryCollection::sortCountryAndStates()`, use a FieldSorting via the DAL or direct SQL instead.
* Deprecated `Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection::sortByPositionAndName()`, use a FieldSorting via the DAL or direct SQL instead.
