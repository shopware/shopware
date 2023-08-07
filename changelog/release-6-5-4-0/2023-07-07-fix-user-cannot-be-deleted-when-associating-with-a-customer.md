---
title: Fix user cannot be deleted when associating with a customer
issue: NEXT-28517
---
# Core
* Added new migration `Migration1688717599UpdateCreatedByIdAndUpdatedByIdInOrderAndCustomer` to alter `fk.customer.created_by_id`, `fk.customer.updated_by_id`, `fk.order.created_by_id` and `fk.order.updated_by_id` constraints
