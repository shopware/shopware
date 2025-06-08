---
title: Fix many to many filtered join to same table
issue: NEXT-37991
---

# Core

* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader` to join correct columns on a many-to-many filtered join to the same table.
