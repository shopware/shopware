---
title: Add default search configurations for each module
issue: NEXT-16170
flag: FEATURE_NEXT_6040
---
# Administration
* Added new service `search-ranking.service.js` in `/src/app/service` to define search ranking scores constans.
* Added a new file `default-search-configuration.js` to determine the list of the ranking fields of the entity with default configuration (score and searchable) into the following modules:
    * `/src/module/sw-category`
    * `/src/module/sw-cms`
    * `/src/module/sw-customer`
    * `/src/module/sw-landing-page`
    * `/src/module/sw-manufacturer`
    * `/src/module/sw-media`
    * `/src/module/sw-newsletter-recipient`
    * `/src/module/sw-order`
    * `/src/module/sw-product`
    * `/src/module/sw-product-stream`
    * `/src/module/sw-promotion-v2`
    * `/src/module/sw-property`
    * `/src/module/sw-sales-channel`
    * `/src/module/sw-settings-customers-group`
    * `/src/module/sw-settings-payment`
    * `/src/module/sw-settings-shipping`
* Added new property `defaultSearchConfigurations` with value imported from `default-search-configuration.js` into the following files:
    * `/src/module/sw-category/index.js`
    * `/src/module/sw-cms/index.js`
    * `/src/module/sw-customer/index.js`
    * `/src/module/sw-landing-page/index.js`
    * `/src/module/sw-manufacturer/index.js`
    * `/src/module/sw-media/index.js`
    * `/src/module/sw-newsletter-recipient/index.js`
    * `/src/module/sw-order/index.js`
    * `/src/module/sw-product/index.js`
    * `/src/module/sw-product-stream/index.js`
    * `/src/module/sw-promotion-v2/index.js`
    * `/src/module/sw-property/index.js`
    * `/src/module/sw-sales-channel/index.js`
    * `/src/module/sw-settings-customers-group/index.js`
    * `/src/module/sw-settings-payment/index.js`
    * `/src/module/sw-settings-shipping/index.js`
* Added new property `searchEntity` in `/src/module/sw-property/index.js` with value is `property_group` to overwrite the current `entity` without impacting to the previous function (just using for search ranking function)
___
# Upgrade Information
## Adding default search configuration
* Adding a new js file (**`default-search-configuration.js`**) with the same folder level of the index.js (which is located at `src/Administration/Resources/app/administration/src/module/sw-module-name/index.js`)
```
src
│
└───sw-module-name
│   │
│   └───component
│   │
│   └───page
│   │
│   └───service
│   │
│   └───....
│   │ 
│   │   default-search-configuration.js
│   │   index.js

```
to determine the list of the ranking fields of the entity with default configuration (score and searchable) and adding two new properties in each module definition (`index.js`),
- `defaultSearchConfigurations` (mandatory): import from `./default-search-configuration.js`
- `searchEntity` (optional): determine the entity name of the module (just in case the current entity name of the module does not match with the entity name from the Back-end side)
