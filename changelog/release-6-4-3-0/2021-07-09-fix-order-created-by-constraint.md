---
title: Fix order.created_by_id constraint
issue: NEXT-16139
---
# Core
* Added `\Shopware\Core\Migration\V6_4\Migration1625819412ChangeOrderCreatedByIdConstraint` to change the FK-Constraint on the `order.created_by_id` column and `order.updated_by_id` to `ON DELETE SET NULL`, thus allowing that users, who created or edited admin orders, can still be deleted.
