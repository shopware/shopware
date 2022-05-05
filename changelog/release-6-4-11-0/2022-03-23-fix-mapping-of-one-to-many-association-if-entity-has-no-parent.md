---
title: Fix mapping of one-to-many association if entity has no parent
issue: NEXT-20746
author: Fabian Boensch
author_email: f.boensch1@web.de
author_github: En0Ma1259
---
# Core
* Changed `Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader::loadOneToManyWithPagination()` to skip adding mappings if entity has no parent
