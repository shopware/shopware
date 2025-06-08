---
title: Add locking during version merge
issue: NEXT-19878
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\VersionManager` to lock execution of version merge, to prevent race conditions during parallel executions.
