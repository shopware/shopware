---
title: Update domain listing to show measurement information
issue: #8540
author: Le Nguyen
author_email: nguyenquocdaile@gmail.com
author_github: @Le Nguyen
---
# Administration
* Changed `getDomainColumns` method to show the measurement information in `src/module/sw-sales-channel/component/sw-sales-channel-detail-domains/index.js`.
* Added `column-measurementSystemId` value to show the measurement system information in `src/module/sw-sales-channel/component/sw-sales-channel-detail-domains/index.js`.
* Changed `getLoadSalesChannelCriteria` method to add the measurement system and domain association in `src/module/sw-sales-channel/page/sw-sales-channel-detail/index.js`.
