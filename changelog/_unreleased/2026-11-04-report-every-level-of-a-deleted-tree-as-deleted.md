---
title: Report every level of a deleted tree as deleted
issue: 21414
---
# Core
* Changed `Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver` to keep the affected rows of every cascade level, as in 6.7. Deleting an entity whose cascade reaches the same entity on several levels, for example a category with nested child categories, now reports every removed row as deleted. Before, the levels between the root and the deepest level got no `EntityDeletedEvent`, no `*.deleted` webhook and no entry in the `deleted` part of the sync response. Webhook and app consumers now receive these additional deletes.
