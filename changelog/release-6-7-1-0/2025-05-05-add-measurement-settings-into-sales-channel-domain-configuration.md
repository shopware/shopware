---
title: Add measurement settings into sales channel domain configuration
issue: #8539
author: Le Nguyen
author_email: nguyenquocdaile@gmail.com
author_github: @Le Nguyen
---
# Administration
* Added `measurementSystemId`, `lengthUnitId` and `massUnitId` item to `currentDomainBackup` object to store the initial values of the measurement settings in `src/module/sw-sales-channel/component/sw-sales-channel-detail-domains/index.js`.
* Changed `setCurrentDomainBackup` method to set the initial values of the measurement settings to `currentDomainBackup` object in `src/module/sw-sales-channel/component/sw-sales-channel-detail-domains/index.js`.
* Changed `resetCurrentDomainToBackup` method to reset the measurement settings to the initial values in `src/module/sw-sales-channel/component/sw-sales-channel-detail-domains/index.js`.
* Added `setInitialMeasurementSystem` method to set the initial measurement system to `currentDomain` object in `src/module/sw-sales-channel/component/sw-sales-channel-detail-domains/index.js`.
