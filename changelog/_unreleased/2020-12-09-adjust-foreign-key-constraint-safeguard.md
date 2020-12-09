---
title: Adjust foreign key constraint safeguard
issue: NEXT-12797
---
# Core
* Added `\Shopware\Core\Migration\Test\Migration1604502151AddThemePreviewMediaConstraintTest`
* Changed `\Shopware\Storefront\Migration\Migration1604502151AddThemePreviewMediaConstraint` so the migration calculates
  the invalid references and deletes them in one step.
