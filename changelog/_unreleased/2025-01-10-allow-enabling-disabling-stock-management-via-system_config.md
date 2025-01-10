---
title: Allow enabling/disabling stock management via system_config
issue: NEXT-38035
---
# Core
* Changed `beforeWriteOrderItems` and `stateChanged` methods in `Shopware\Core\Content\Product\Stock\OrderStockSubscriber.php` to get the stock management state from the system configuration.
___
# Upgrade Information
## Stock Management Configuration
* The `shopware.stock.enable_stock_management` configuration in `shopware.yaml` has been deprecated.
* Stock management state is now retrieved from the system configuration table (`system_config`).
* Administrators can enable or disable stock management via the Shopware admin interface in Settings > products > Enable stock management.

## Breaking Changes
* The `shopware.stock.enable_stock_management` parameter in `shopware.yaml` is no longer used.
* You must now use the `core.listing.enableStockManagement` system configuration instead.
