---
title: Added new batch import strategy for import/export
issue: NEXT-36420
author: Jozsef Damokos
author_email: j.damokos@shopware.com
author_github: @jozsefdamokos
---
# Core
* Added new batch import strategy for import/export
* Added new command option `--useBatchImport` to `bin/console import:entity` command to use the new batch import strategy. This strategy is faster, but it does not support rows that depend on each other (eg. a row is imported and an entity is created and then another row is imported which updates the entity created by the first row). This strategy is recommended for large imports where the rows are independent of each other.
