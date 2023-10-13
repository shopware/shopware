---
title: Add payment and shipping method technical_name
issue: NEXT-31048
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com
author_github: @lernhart
---
# Core
* Added `technicalName` property to `PaymentMethodEntity`
* Added `technicalName` property to `ShippingMethodEntity`
* Added migration to add the corresponding columns to the database. They are nullable for now.
* Added migration for v6.7.0.0 to make the columns non-nullable.
* Changed `PaymentMethodPersister` to automatically generate a `technicalName` based on the app name and the payment method identifier given in the `manifest.xml`
* Changed `ShippingMethodPersister` to automatically generate a `technicalName` based on the app name and the shipping method identifier given in the `manifest.xml`
___
# Administration
* Added required `technicalName` field to payment method detail page
* Added required `technicalName` field to shipping method detail page
___
# Upgrade Information
## Add shipping and payment method technical names
The technical name is only required in the Administration for now, but will be required in the API as well in the future (v6.7.0.0). 
It is used to identify the payment and shipping method in the API and in the administration.

To prevent issues with the upgrade to v6.7.0.0, please make sure to add a technical name to all payment and shipping methods:

**Merchants** should add a technical name to all custom created payment and shipping methods in the administration.

**Plugin developers** should add a technical name to all payment and shipping methods during plugin installation / update.

**App developers** do not need to do anything, as the technical name is automatically generated based on the app name and the payment or shipping method identifier given in the `manifest.xml`.
This includes existing app installations.
