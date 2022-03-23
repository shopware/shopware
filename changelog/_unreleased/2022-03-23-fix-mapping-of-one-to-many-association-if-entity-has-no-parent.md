---
title: Fix mapping of one-to-many association if entity has no parent
issue: NEXT-20746
author: En0Ma1259
author_email: 
author_github: En0Ma1259
---
# Core
* Changed `Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader::loadOneToManyWithPagination()` to skip adding mappings if entity has no parent
