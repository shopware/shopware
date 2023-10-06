---
title: Implement new statistics chart design
issue: NEXT-21224
---
# Administration
* Deprecated the property `statisticDateRanges` in `sw-dashboard-statistics/index.js`
* Deprecated the block `sw_dashboard_statistics_headline` in `sw-dashboard-statistics.html.twig`. It will be removed
* Deprecated the block `sw_dashboard_index_statistics_headline_title` in `sw-dashboard-statistics.html.twig`. Use block `sw_dashboard_statistics_count_title` and `sw_dashboard_statistics_sum_title` instead
* Deprecated the block `sw_dashboard_statistics_headline_date` in `sw-dashboard-statistics.html.twig`. Use block `sw_dashboard_statistics_count_range_select` and `sw_dashboard_statistics_sum_range_select` instead
* Deprecated the block `sw_dashboard_statistics_count_chart` in `sw-dashboard-statistics.html.twig`. Use the parent block `sw_dashboard_statistics_count` instead
* Deprecated the block `sw_dashboard_statistics_sum_chart` in `sw-dashboard-statistics.html.twig`. Use the parent block `sw_dashboard_statistics_sum` instead
