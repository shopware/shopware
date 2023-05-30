---
title: Moved admin dashboard statistics into separate component
issue: NEXT-18187
author: Eric Heinzl
author_email: e.heinzl@shopware.com  
---
# Core
* Added feature flag `FEATURE_NEXT_18187`
___
# Administration
* Added component `administration/src/module/sw-dashboard/component/sw-dashboard-statistics`
* Deprecated `historyOrderDataCount` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `historyOrderDataSum` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `todayOrderData` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `todayOrderDataLoaded` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `todayOrderDataSortBy` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `todayOrderDataSortDirection` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `statisticDateRanges` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `chartOptionsOrderCount` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `chartOptionsOrderSum` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `orderRepository` in `sw-dashboard-index`. Will be removed.
* Deprecated `orderCountMonthSeries` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `orderCountSeries` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `orderCountToday` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `orderSumMonthSeries` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `orderSumSeries` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `orderSumToday` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `hasOrderToday` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `hasOrderInMonth` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `dateAgo` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `today` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `todayBucket` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `createdComponent` in `sw-dashboard-index`. It won't call `getHistoryOrderData` nor `fetchTodayData` after `FEATURE_NEXT_18187` is removed
* Deprecated `getTimeUnitInterval` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `systemCurrencyISOCode` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `getHistoryOrderData` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `fetchHistoryOrderDataCount` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `fetchHistoryOrderDataSum` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `fetchTodayData` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `formatDate` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `orderGridColumns` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `getVariantFromOrderState` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated `parseDate` in `sw-dashboard-index`. Will be removed. Override `sw-dashboard-statistics` instead
* Deprecated block `sw_dashboard_index_content_intro_stats_headline` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
* Deprecated block `sw_dashboard_index_content_intro_stats_headline_title` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
* Deprecated block `sw_dashboard_index_content_intro_stats_headline_date` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
* Deprecated block `sw_dashboard_index_content_intro_stats_today` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
* Deprecated block `sw_dashboard_index_content_intro_stats_today_stats` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
* Deprecated block `sw_dashboard_index_content_intro_stats_today_stats_single_count` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
* Deprecated block `sw_dashboard_index_content_intro_stats_today_stats_single_sum` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
* Deprecated block `sw_dashboard_index_content_grid` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
* Deprecated block `sw_dashboard_index_content_grid_created_at` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
* Deprecated block `sw_dashboard_index_content_grid_first_name` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
* Deprecated block `sw_dashboard_index_content_grid_short_name` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
* Deprecated block `sw_dashboard_index_content_grid_state` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
* Deprecated block `sw_dashboard_index_content_grid_actions` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
* Deprecated block `sw_dashboard_index_content_grid_actions_view` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
* Deprecated block `sw_dashboard_index_content_statistics_headline` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
* Deprecated block `sw_dashboard_index_content_statistics_headline_title` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
* Deprecated block `sw_dashboard_index_content_statistics_headline_date` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
* Deprecated block `sw_dashboard_index_content_statistics_count` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
* Deprecated block `sw_dashboard_index_content_statistics_count_chart_count` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
* Deprecated block `sw_dashboard_index_content_statistics_sum` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
* Deprecated block `sw_dashboard_index_content_statistics_count_chart_sum` in `sw-dashboard-index.html.twig`. Override in `sw-dashboard-statistics` instead.
