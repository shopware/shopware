---
title: Improve handling of disabled associations of Sales Channels
issue: NEXT-17047
---
# Administration
* Added criteria for `countries` in `sw-sales-channel-detail-base` to correctly sort countries
* Added alert for `countries`, `paymentMethods` and `shippingMethods` for disabled associations
* Added tooltip for disabled `countries`, `paymentMethods` and `shippingMethods` to be not selectable as default associations
* Added property `selectionDisablingMethod` for method for disabling entries in `sw-entity-single-select`
* Added property `disabledSelectionTooltip` for showing a tooltip for disabled entries in `sw-entity-single-select`
