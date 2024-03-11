---
title: Fix many to many serializer check
issue: NEXT-33362
---
# Core
* Changed the data structure check to be in the correct place in `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToManyAssociationFieldSerializer`
* Deprecated `\Shopware\Core\Framework\DataAbstractionLayer\Exception\DecodeByHydratorException` use `\Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException::decodeHandledByHydrator` instead
* Changed the various association field serializers to throw `DataAbstractionLayerException`'s instead of `\RuntimeException`'s
