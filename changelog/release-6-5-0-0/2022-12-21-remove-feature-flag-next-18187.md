---
title: Remove Feature Flag NEXT-18187
issue: NEXT-24646
author: Sebastian Franze
author_email: s.franze@shopware.com
---
# Core
* Removed Feature Flag FEATURE_NEXT_18187
___
# Administration
* Changed component `sw-dashboard-statistics` to be private.
* Removed data property `statisticDateRanges` in `sw-dashboard-statistics`.
* Removed computed property `todayBucket` in `sw-dashboard-statistics`.
* Removed method `formatDate` in `sw-dashboard-statistics`
* Removed `historyOrderDataCount` in `sw-dashboard-index`.
* Removed `historyOrderDataSum` in `sw-dashboard-index`.
* Removed `todayOrderData` in `sw-dashboard-index`.
* Removed `todayOrderDataLoaded` in `sw-dashboard-index`.
* Removed `todayOrderDataSortBy` in `sw-dashboard-index`.
* Removed `todayOrderDataSortDirection` in `sw-dashboard-index`.
* Removed `statisticDateRanges` in `sw-dashboard-index`.
* Removed `chartOptionsOrderCount` in `sw-dashboard-index`.
* Removed `chartOptionsOrderSum` in `sw-dashboard-index`.
* Removed `orderRepository` in `sw-dashboard-index`.
* Removed `orderCountMonthSeries` in `sw-dashboard-index`.
* Removed `orderCountSeries` in `sw-dashboard-index`.
* Removed `orderCountToday` in `sw-dashboard-index`.
* Removed `orderSumMonthSeries` in `sw-dashboard-index`.
* Removed `orderSumSeries` in `sw-dashboard-index`.
* Removed `orderSumToday` in `sw-dashboard-index`.
* Removed `hasOrderToday` in `sw-dashboard-index`.
* Removed `hasOrderInMonth` in `sw-dashboard-index`.
* Removed `dateAgo` in `sw-dashboard-index`.
* Removed `today` in `sw-dashboard-index`.
* Removed `todayBucket` in `sw-dashboard-index`.
* Removed `createdComponent` in `sw-dashboard-index`.
* Removed `getTimeUnitInterval` in `sw-dashboard-index`.
* Removed `systemCurrencyISOCode` in `sw-dashboard-index`.
* Removed `getHistoryOrderData` in `sw-dashboard-index`.
* Removed `fetchHistoryOrderDataCount` in `sw-dashboard-index`.
* Removed `fetchHistoryOrderDataSum` in `sw-dashboard-index`.
* Removed `fetchTodayData` in `sw-dashboard-index`.
* Removed `formatDate` in `sw-dashboard-index`.
* Removed `orderGridColumns` in `sw-dashboard-index`.
* Removed `getVariantFromOrderState` in `sw-dashboard-index`.
* Removed `parseDate` in `sw-dashboard-index`.
* Removed block `sw_dashboard_index_content_intro_stats_headline` in `sw-dashboard-index.html.twig`.
* Removed block `sw_dashboard_index_content_intro_stats_headline_title` in `sw-dashboard-index.html.twig`.
* Removed block `sw_dashboard_index_content_intro_stats_headline_date` in `sw-dashboard-index.html.twig`.
* Removed block `sw_dashboard_index_content_intro_stats_today` in `sw-dashboard-index.html.twig`.
* Removed block `sw_dashboard_index_content_intro_stats_today_stats` in `sw-dashboard-index.html.twig`.
* Removed block `sw_dashboard_index_content_intro_stats_today_stats_single_count` in `sw-dashboard-index.html.twig`.
* Removed block `sw_dashboard_index_content_intro_stats_today_stats_single_sum` in `sw-dashboard-index.html.twig`.
* Removed block `sw_dashboard_index_content_grid` in `sw-dashboard-index.html.twig`.
* Removed block `sw_dashboard_index_content_grid_created_at` in `sw-dashboard-index.html.twig`.
* Removed block `sw_dashboard_index_content_grid_first_name` in `sw-dashboard-index.html.twig`.
* Removed block `sw_dashboard_index_content_grid_short_name` in `sw-dashboard-index.html.twig`.
* Removed block `sw_dashboard_index_content_grid_state` in `sw-dashboard-index.html.twig`.
* Removed block `sw_dashboard_index_content_grid_actions` in `sw-dashboard-index.html.twig`.
* Removed block `sw_dashboard_index_content_grid_actions_view` in `sw-dashboard-index.html.twig`.
* Removed block `sw_dashboard_index_content_statistics_headline` in `sw-dashboard-index.html.twig`.
* Removed block `sw_dashboard_index_content_statistics_headline_title` in `sw-dashboard-index.html.twig`.
* Removed block `sw_dashboard_index_content_statistics_headline_date` in `sw-dashboard-index.html.twig`.
* Removed block `sw_dashboard_index_content_statistics_count` in `sw-dashboard-index.html.twig`.
* Removed block `sw_dashboard_index_content_statistics_count_chart_count` in `sw-dashboard-index.html.twig`.
* Removed block `sw_dashboard_index_content_statistics_sum` in `sw-dashboard-index.html.twig`.
* Removed block `sw_dashboard_index_content_statistics_count_chart_sum` in `sw-dashboard-index.html.twig`.
