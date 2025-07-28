---
title: Fix apps with duplicated custom field sets
issue: #10738
---
# Core
* Changed `\Shopware\Core\Framework\App\Lifecycle\Persister\CustomFieldPersister::upsertCustomFieldSets` to remove custom field sets and recreate them if their names are duplicated and we can not map them properly.
