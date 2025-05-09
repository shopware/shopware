---
title: Add measurement settings into sales channel configuration
issue: #8538
---
# Administration
* Added new `sw-sales-channel-measurement` component to handle the measurement settings.
* Added `getMeasurementSystemConfig` method to fetch the measurement system config from the system config API in `src/module/sw-sales-channel/page/sw-sales-channel-create/index.js`.
* Added `salesChannel.defaultMeasurementSystemId` watch to update the length and mass unit if the measurement system is changed in `src/module/sw-sales-channel/page/sw-sales-channel-create/index.js`.
* Changed `createdComponent` method to initialize the measurement system config in `src/module/sw-sales-channel/page/sw-sales-channel-create/index.js`.
