---
title: Generate EXISTS conditions instead of left joins for nested filter groups in DAL criteria builder
issue: 13707
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\CriteriaPartResolver` to generate exists queries for multi join groups instead of left joins, thus fixing a performance issue where the number of joins exploded when you have multiple join filters on the same entity.
