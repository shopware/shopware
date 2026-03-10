---
title: Improve shop id verification when used with atomic deployments
---
# Core
* Changed `\Shopware\Core\Framework\App\ShopId\Fingerprint\InstallationPath` to calculate the score based on how much of the path has changed to better support `/releases/1`, `/realeses/2` type deployments.
