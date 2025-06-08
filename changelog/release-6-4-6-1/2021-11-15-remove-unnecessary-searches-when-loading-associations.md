---
title: Remove unnecessary searches when loading associations
issue: NEXT-18709
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader` to not perform full reads when loading associations but no Ids are defined.
