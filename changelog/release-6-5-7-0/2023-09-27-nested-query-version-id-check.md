---
title: Check nested query for version id field
issue: NEXT-30929     
author: Fabian Boensch
author_github: @En0Ma1259
---
 # Core
 * Changed `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\CriteriaPartResolver` to check if association has entity_version_id field before using it
