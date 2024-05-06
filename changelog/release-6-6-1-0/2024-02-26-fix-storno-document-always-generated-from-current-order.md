---
title: Fix Storno document always generated from current order
issue: NEXT-29601
---
# Core
* Changed `Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ReferenceVersionFieldSerializer::encode` to fix FK fallback for none versioned entities which reference to a version entity.
