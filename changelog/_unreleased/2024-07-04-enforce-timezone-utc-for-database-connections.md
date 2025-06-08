---
title: Enforce timezone UTC for database connections
issue: NEXT-37117
flag: V6_7_0_0
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com
author_github: @lernhart
---
# Core
* Changed `Shopware\Core\Framework\Kernel` to always set the session timezone for database connections to `+00:00`.
___
# Next Major Version Changes
## Set +00:00 session timezone for database connections:
* Changed `Shopware\Core\Framework\Kernel` to always set the session timezone for database connections to `+00:00`. This works without importing timezone names to MySQL or MariaDB and can be applied without any further configuration.
* Removed `SHOPWARE_DBAL_TIMEZONE_SUPPORT_ENABLED` environment variable.
