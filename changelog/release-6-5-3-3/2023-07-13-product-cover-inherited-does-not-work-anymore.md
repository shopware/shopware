---
title: Product cover inherited does not work anymore
issue: NEXT-29278
---

# Core

* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\ManyToOneAssociationFieldResolver` to ignore the version field when the field is inherited and using the indexed column
