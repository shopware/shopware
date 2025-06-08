---
title: Fix `sales_channel_analytics` definition
issue: NEXT-26845
---
# Core
* Changed `\Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsDefinition` to remove the parent definition, as the `sales_channel_analytics` definition has no ForeignKey to the sales channel definition, therefore configuring that as a parent definition is not possible.
