---
title: Fix web installer with custom db port
issue: NEXT-33165
---
# Core
* Changed `\Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation` to cast request values to correct types, so custom port config is possible.
