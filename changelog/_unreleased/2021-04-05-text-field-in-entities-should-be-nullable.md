---
title: Text field in entities should be nullable
issue: NEXT-14583
---
# Core
*  Update function `normalize` at class `TranslatedFieldSerializer` from `Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer` to return original `$data` when property name of the field does not exist in keys of `$data` 
