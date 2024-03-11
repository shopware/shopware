---
title: Change path column in app definition to varchar(4096)
issue: NEXT-34028
---

# Core

* Changed `\Core\Framework\App\AppDefinition`, column `path` from `varchar(255)` to `varchar(4096)` to support longer paths.
* Added `\Core\Migration\V6_5\Migration1708685281ChangeAppPathColumnToLongerVarchar`
