---
title: Reduce downtime while theme change
issue: https://github.com/shopware/shopware/issues/7768
author: Michael Telgmann
author_github: @mitelg
---
# Storefront

* Added a database transaction around the calls in `\Shopware\Storefront\Theme\ThemeService::assignTheme` to ensure the required database changes are committed at the same time to reduce the time the system is in an inconsistent state.
