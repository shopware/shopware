---
title: Make Entrypoints Configurable
issue: NEXT-38707
author: runelaenen
author_email: rune@laenen.me
author_github: @runelaenen
---
# Core
* Added SalesChannelEntrypointService which uses the new SalesChannelEntrypointEvent to look for custom entrypoints and read configuration from the given SalesChannelEntity.
* Added SalesChannelEntrypointRoute to read custom entrypoints from active Sales Channel
* Added `entrypointIds` JSON field to the SalesChannel entity
* Changed known usages of core category ids in
  * CategoryBreadcrumbBuilder
  * CategoryListRoute
  * CategoryUrlProvider
  * NavigationPageSeoUrlRoute
  * NavigationRoute
  * TreeBuildingNavigationRoute
___
# API
* Added SalesChannelEntrypointController `/api/_action/sales-channel/{salesChannelId}/entrypoint` to add admin API to return the list of known custom entrypoint.
___
# Administration
* Added `entryPointService` to consume SalesChannelEntrypointController
* Added category selector for each known custom entrypoint in `sw-sales-channel-detail-base`
___
# Storefront
* Added automatical loading of all known and configured custom entrypoints into HeaderPagelet
