---
title: Add toggle for time zone support in DateHistogram
issue: NEXT-16489
---
# Core
* Added new environment variable `SHOPWARE_DBAL_TIMEZONE_SUPPORT_ENABLED` to toggle time zone support in `DateHistogramAggregation`
  * This should be only activated when the MySQL database has time zones populated. See https://dev.mysql.com/doc/refman/8.0/en/time-zone-support.html#time-zone-installation for more information
