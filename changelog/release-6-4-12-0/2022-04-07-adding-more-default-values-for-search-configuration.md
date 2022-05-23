---
title: Adding more default values for search configuration
issue: NEXT-20962
---
# Administration
* Changed method `_groupFields` in `src/app/service/search-preferences.service.js` to rename media folder name field
* Changed method `__isEntitySearchable` in `src/app/service/search-ranking.service.js` to check searchable ability for modules
* Changed `default-search-configuration` file in these modules to add more default values for search configuration:
    * `sw-cms`
    * `sw-media`
    * `sw-newsletter-recipient`
    * `sw-promotion-v2`
    * `sw-property`
    * `sw-sales-channel`
    * `sw-settings-customer-group`
    * `sw-settings-payment`
    * `sw-settings-shipping`
    * `sw-manufacturer`
