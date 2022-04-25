---
title: Fix AppUrlChangedModal if no apps are installed
issue: NEXT-21086
---
# Core
* Changed `\Shopware\Core\Framework\App\ShopId\ShopIdProvider::getShopId()` to not throw an `AppUrlChangeDetectedException` if no apps are installed, as it is no exception case in that case and the AppUrlChange modal won't be shown.
* Removed internal `Shopware\Core\Framework\App\Exception\NoAppUrlChangeDetectedException`
* Removed const `SHOP_DOMAIN_CHANGE_CONFIG_KEY` of internal `\Shopware\Core\Framework\App\ShopId\ShopIdProvider`.
