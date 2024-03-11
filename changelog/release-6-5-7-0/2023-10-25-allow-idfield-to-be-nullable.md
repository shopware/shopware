---
title: Allow IdField to be nullable
issue: NEXT-31269
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\IdFieldSerializer` to allow nulls and only generate a UUID when the field is a primary key
