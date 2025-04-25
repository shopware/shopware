---
title: Add measurement settings into sales channel configuration
issue: #8538
---
# Administration
* Added three `sw-entity-single-select` fields for measurement system, length unit and mass unit in `src/module/sw-sales-channel/view/sw-sales-channel-detail-base/sw-sales-channel-detail-base.html.twig`.
* Added `onMeasurementSystemChange` method to handle the change of measurement system and reset length and mass unit if the new measurement system is not the default one in `src/module/sw-sales-channel/view/sw-sales-channel-detail-base/index.js`.
* Added `defaultMeasurementSystemId`, `defaultLengthUnitId` and `defaultMassUnitId` data to store the default measurement system ID, length unit ID and mass unit ID in `src/module/sw-sales-channel/view/sw-sales-channel-detail-base/index.js`.
* Added `lengthUnitCriteria` and `massUnitCriteria` computed properties to match values with the selected measurement system in `src/module/sw-sales-channel/view/sw-sales-channel-detail-base/index.js`.
* Changed `created` lifecycle to initialize the default measurement system ID, length unit ID and mass unit ID in `src/module/sw-sales-channel/view/sw-sales-channel-detail-base/index.js`.

* Added `getMeasurementSystemConfig` method to fetch the measurement system config from the system config API in `src/module/sw-sales-channel/page/sw-sales-channel-create/index.js`.
* Added `salesChannel.defaultMeasurementSystemId` watch to update the length and mass unit if the measurement system is changed in `src/module/sw-sales-channel/page/sw-sales-channel-create/index.js`.
* Changed `createdComponent` method to initialize the measurement system config in `src/module/sw-sales-channel/page/sw-sales-channel-create/index.js`.
