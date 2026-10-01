---
title: Removed SQL_SET_DEFAULT_SESSION_VARIABLES and enforce MySQL performance tweaks
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com
author_github: @lernhart
---
# Core
* Removed `SQL_SET_DEFAULT_SESSION_VARIABLES` env variable. It has no effect anymore. The previously optional performance tweaks to MySQL are now enforced on connection buildup inside of the `\Shopware\Core\Framework\Adapter\Database\MySQLFactory`.
